<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Airalo Partner API (v2) client: the primary eSIM catalogue and fulfilment provider.
 *
 * Every call returns an envelope — {ok, data, raw} on success, {ok:false, status, error} on failure —
 * and never throws. The catalogue is built from a cached snapshot of all package pages because
 * Airalo has no cheap "list countries" endpoint.
 */
class AiraloService
{
    /** Airalo's SM-DP+ host; the API doesn't return it per SIM. */
    private const SMDP_ADDRESS = 'rsp.airalo.com';

    protected string $baseUrl;

    protected string $clientId;

    protected string $clientSecret;

    protected string $currency;

    protected bool $isSandbox;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.airalo.base_url', 'https://partners-api.airalo.com/v2');
        $this->clientId = (string) config('services.airalo.client_id');
        $this->clientSecret = (string) config('services.airalo.client_secret');
        $this->currency = (string) config('services.airalo.currency', 'USD');
        $this->isSandbox = (bool) config('services.airalo.sandbox', true);
    }

    // ─── Catalogue ──────────────────────────────────────────────────────────────

    public function popularCountries(int $limit = 6): array
    {
        return array_slice($this->allCountriesWithPrices(), 0, $limit);
    }

    public function popularRegions(int $limit = 7): array
    {
        return array_slice($this->allRegionsWithPrices(), 0, $limit);
    }

    /** Autocomplete index of every country and region (plus "Global"). */
    public function searchableAssets(): array
    {
        return $this->rememberNonEmpty('airalo.searchable_assets.v1', now()->addHours(24), function () {
            $rows = [
                ...array_map(fn ($c) => ['type' => 'country', 'id' => $c['id'], 'name' => $c['name']], $this->countries()),
                ...array_map(fn ($r) => ['type' => 'region', 'id' => $r['id'], 'name' => $r['name']], $this->continents()),
                ['type' => 'region', 'id' => 'global', 'name' => 'Global'],
            ];

            return array_values(array_filter($rows, fn ($row) => trim((string) $row['id']) !== '' && trim((string) $row['name']) !== ''));
        });
    }

    /** Every country with its cheapest package across all operators. */
    public function allCountriesWithPrices(string $packageType = 'DATA-ONLY'): array
    {
        return $this->rememberNonEmpty("airalo.all_countries_prices.v2.{$packageType}", now()->addHours(6), function () {
            return array_map(function ($item) {
                $prices = [];
                foreach ($item['operators'] ?? [] as $operator) {
                    foreach ($operator['packages'] ?? [] as $p) {
                        if (isset($p['price']) && is_numeric($p['price'])) {
                            $prices[] = (float) $p['price'];
                        }
                    }
                }

                $min = $prices ? min($prices) : 0;
                $slug = (string) ($item['slug'] ?? '');

                return [
                    'id' => $slug,
                    'name' => $item['title'],
                    'code' => strtoupper($slug),
                    'flag' => $this->flagEmoji($slug),
                    'flag_url' => $item['image']['url'] ?? null,
                    'starting_price' => (float) $min,
                    'starting_price_formatted' => '$'.number_format($min, 2),
                    'subtext' => $this->countrySubtext($slug),
                    'tag' => $this->countryTag($slug),
                ];
            }, $this->packageSnapshot('local'));
        });
    }

    /** Regional/global plans. Prices are a display placeholder; the detail page shows real ones. */
    public function allRegionsWithPrices(): array
    {
        return $this->rememberNonEmpty('airalo.all_regions_prices.v1', now()->addHours(6), function () {
            return array_map(fn ($item) => [
                'id' => $item['slug'] ?? $item['id'],
                'name' => $item['title'],
                'code' => 'UN',
                'flag' => '🌐',
                'flag_url' => $item['image']['url'] ?? null,
                'starting_price' => 5.00,
                'starting_price_formatted' => '$5.00',
            ], $this->packageSnapshot('global'));
        });
    }

    /** Drop and rebuild the cached catalogue lists (scheduled hourly via `airalo:sync-catalog`). */
    public function refreshCatalog(): void
    {
        foreach (['airalo.all_countries_prices.v2.DATA-ONLY', 'airalo.all_regions_prices.v1', 'airalo.searchable_assets.v1'] as $key) {
            Cache::forget($key);
        }

        $this->allCountriesWithPrices('DATA-ONLY');
        $this->allRegionsWithPrices();
        $this->searchableAssets();
    }

    public function countries(): array
    {
        return array_map(fn ($item) => $this->assetSummary($item), $this->packageSnapshot('local'));
    }

    /** Regional plans only: the "world" plan is surfaced separately as Global. */
    public function continents(): array
    {
        $regional = array_filter($this->packageSnapshot('global'), fn ($item) => ($item['slug'] ?? '') !== 'world');

        return array_map(fn ($item) => $this->assetSummary($item), array_values($regional));
    }

    /** Country or region detail with every operator's packages flattened into bundles. */
    public function getAssetDetails(string $type, string $id, ?string $packageType = 'DATA-ONLY'): ?array
    {
        $cacheKey = "airalo.asset_details.v3.{$type}.{$id}.{$packageType}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $hasOperators = fn ($asset) => $asset && ! empty($asset['operators']);

        // Countries with a known ISO code can be fetched directly; everything else comes from the snapshot.
        $asset = null;
        if ($type === 'country' && ($code = $this->countryCodeForSlug($id))) {
            $asset = $this->fetchCountryAsset($id, $code);
        }

        if (! $hasOperators($asset)) {
            $snapshot = $this->packageSnapshot($type === 'region' ? 'global' : 'local');
            if ($snapshot === []) {
                return null;
            }
            $asset = collect($snapshot)->first(fn ($item) => ($item['slug'] ?? '') === $id);
        }

        // Last resort for unmapped countries: treat the slug itself as the ISO code.
        if ($type === 'country' && ! $hasOperators($asset)) {
            $asset = $this->fetchCountryAsset($id, $this->countryCodeForSlug($id) ?? strtoupper($id)) ?? $asset;
        }

        if (! $asset) {
            return null;
        }

        $packages = [];
        foreach ($asset['operators'] ?? [] as $operator) {
            foreach ($operator['packages'] ?? [] as $pkg) {
                $packages[] = $pkg + [
                    '_operator_title' => $operator['title'] ?? '',
                    '_plan_type' => $operator['plan_type'] ?? 'data',
                ];
            }
        }

        $countryCode = $asset['country_code'] ?? $asset['slug'];
        $details = [
            'name' => $asset['title'],
            'code' => strtoupper($countryCode),
            'flag' => $this->flagEmoji($countryCode),
            'flag_url' => $asset['image']['url'] ?? null,
            'type' => $type === 'region' ? 'Regional Plan' : 'Country eSIM',
            'bundles' => $this->formatPackages($packages, $packageType),
        ];

        Cache::put($cacheKey, $details, now()->addMinutes(30));

        return $details;
    }

    public function packagesByCountryId(string $slug, ?string $packageType = null): array
    {
        return $this->packagesForSlug(['filter[country]' => strtoupper($slug)], $slug, $packageType);
    }

    public function packagesByContinentId(string $slug, ?string $packageType = null): array
    {
        return $this->packagesForSlug(['filter[type]' => 'global'], $slug, $packageType);
    }

    public function packagesGlobal(?string $packageType = null): array
    {
        return $this->packagesForSlug(['filter[type]' => 'global'], 'world', $packageType);
    }

    /** Admin diagnostics: one raw page of packages plus request context. */
    public function getRawPackages(string $type = 'local'): array
    {
        $query = ['filter[type]' => $type === 'regional' ? 'global' : 'local'];
        $resp = $this->request('GET', 'packages', $query);

        return [
            'status' => $resp['status'] ?? ($resp['ok'] ? 200 : 500),
            'ok' => $resp['ok'],
            'url' => "{$this->baseUrl}/packages",
            'query' => $query,
            'is_sandbox' => $this->isSandbox,
            'data_count' => count($resp['data'] ?? []),
            'raw_body' => $resp['raw'] ?? null,
            'error' => $resp['error'] ?? null,
        ];
    }

    // ─── Orders ─────────────────────────────────────────────────────────────────

    /** Buy one eSIM. Airalo delivers synchronously, so success always carries the SIM. */
    public function fulfillEsim(string $packageId, string $reference, string $customerEmail, string $packageType = 'DATA-ONLY'): array
    {
        $resp = $this->request('POST', 'orders', ['package_id' => $packageId, 'quantity' => 1]);

        if (! $resp['ok']) {
            Log::error('Airalo Purchase Failed', [
                'status' => $resp['status'] ?? 500,
                'error' => $resp['error'] ?? 'Unknown error',
                'package_id' => $packageId,
                'reference' => $reference,
            ]);

            return ['ok' => false, 'error' => $resp['error'] ?? 'Purchase failed'];
        }

        $order = $resp['data'];
        $sim = $order['sims'][0] ?? null;

        if (! $sim) {
            Log::error('Airalo Order Success but No SIM', ['package_id' => $packageId, 'reference' => $reference, 'response' => $order]);

            return ['ok' => false, 'error' => 'Order created but no SIM returned'];
        }

        return [
            'ok' => true,
            'package_id' => $packageId,
            'package_type' => $packageType,
            'reference' => $reference,
            'esim_id' => $sim['id'],
            'order_id' => $order['id'],
            'iccid' => $sim['iccid'],
            'activation_code' => $sim['lpa'], // Airalo returns the activation code as `lpa`
            'lpa' => $sim['lpa'],
            'qr_code_url' => $sim['qrcode_url'],
            'smdp_address' => self::SMDP_ADDRESS,
            'direct_installation_link_ios' => $sim['installation_guides']['ios'] ?? null,
            'direct_installation_link_android' => $sim['installation_guides']['android'] ?? null,
            'raw' => $order,
        ];
    }

    /** Top up an existing eSIM. Airalo identifies the target SIM by ICCID. */
    public function topupEsim(string $iccid, string $packageId, string $reference, string $customerEmail): array
    {
        $iccid = trim($iccid);
        if (! $this->isValidIccid($iccid)) {
            return ['ok' => false, 'error' => 'Invalid ICCID format'];
        }

        $resp = $this->request('POST', 'orders', ['package_id' => $packageId, 'quantity' => 1, 'iccid' => $iccid]);

        if ($resp['ok']) {
            return [
                'ok' => true,
                'reference' => $reference,
                'esim_id' => $iccid,
                'order_id' => $resp['data']['id'],
                'iccid' => $iccid,
                'raw' => $resp['data'],
            ];
        }

        $status = $resp['status'] ?? 500;
        $error = $status === 404 ? "eSIM (ICCID: {$iccid}) not found in Airalo's system" : ($resp['error'] ?? 'Top-up failed');

        Log::error('Airalo Topup Failed', ['status' => $status, 'error' => $error, 'iccid' => $iccid, 'package_id' => $packageId, 'reference' => $reference]);

        return ['ok' => false, 'status' => $status, 'error' => $error];
    }

    public function getEsimDetails(string $iccid): array
    {
        $iccid = trim($iccid);
        if (! $this->isValidIccid($iccid)) {
            return ['ok' => false, 'error' => 'Invalid ICCID format'];
        }

        $resp = $this->get("sims/{$iccid}");
        if (! $resp['ok']) {
            return ($resp['status'] ?? 0) === 404 ? ['ok' => false, 'status' => 404, 'error' => 'eSIM not found'] : $resp;
        }

        $sim = $resp['data'];

        return [
            'ok' => true,
            'esim_id' => $sim['id'],
            'iccid' => $sim['iccid'],
            'can_renew' => true,
            'activation_code' => $sim['lpa'],
            'lpa' => $sim['lpa'],
            'qr_code_url' => $sim['qrcode_url'],
            'smdp_address' => self::SMDP_ADDRESS,
            'esim_status' => $sim['status'],
            'raw' => $sim,
        ];
    }

    public function syncEsim(string $iccid): array
    {
        return $this->getEsimDetails($iccid);
    }

    /** ICCIDs are 18–22 digits. */
    public function isValidIccid(string $iccid): bool
    {
        return preg_match('/^[0-9]{18,22}$/', $iccid) === 1;
    }

    /** ISO alpha-2 → flag emoji; globe for anything else (including slugs). */
    public function flagEmoji(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        if (strlen($code) !== 2) {
            return '🌐';
        }

        return mb_chr(ord($code[0]) + 127397).mb_chr(ord($code[1]) + 127397);
    }

    // ─── HTTP ───────────────────────────────────────────────────────────────────

    /** OAuth2 client-credentials token, cached for 50 of its 60 minutes. */
    protected function getToken(bool $force = false): ?string
    {
        $cacheKey = 'airalo_auth_token';
        if ($force) {
            Cache::forget($cacheKey);
        }

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && trim($cached) !== '') {
            return $cached;
        }

        try {
            $response = Http::asForm()
                ->withHeaders($this->headers())
                ->post("{$this->baseUrl}/token", [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'client_credentials',
                ]);

            $token = $response->successful() ? $response->json('data.access_token') : null;
            if (is_string($token) && trim($token) !== '') {
                Cache::put($cacheKey, $token, now()->addMinutes(50));

                return $token;
            }

            Log::error('Airalo Auth Failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'url' => "{$this->baseUrl}/token",
                'is_sandbox' => $this->isSandbox,
            ]);
        } catch (\Exception $e) {
            Log::error('Airalo Auth Exception: '.$e->getMessage());
        }

        return null;
    }

    /** Authenticated request; a 401 refreshes the token and retries once. */
    protected function request(string $method, string $endpoint, array $data = [], bool $isRetry = false): array
    {
        $token = $this->getToken($isRetry);
        if (! $token) {
            return ['ok' => false, 'error' => 'Authentication failed'];
        }

        $method = strtolower($method);
        $url = "{$this->baseUrl}/{$endpoint}";

        try {
            $http = Http::withToken($token)
                ->withHeaders($this->headers())
                ->timeout(30)
                ->retry(2, 100, fn ($e) => $e instanceof ConnectionException, throw: false);

            $response = $method === 'get' ? $http->get($url, $data) : $http->post($url, $data);

            Log::info("Airalo API Request: {$method} {$endpoint}", [
                'url' => $url,
                'status' => $response->status(),
                'is_retry' => $isRetry,
                'payload' => $method === 'post' ? $this->maskSensitiveData($data) : null,
                'response_meta' => $response->json('meta') ?? [],
            ]);

            if ($response->status() === 401 && ! $isRetry) {
                Log::warning('Airalo Token Expired (401), Retrying...', ['endpoint' => $endpoint]);

                return $this->request($method, $endpoint, $data, true);
            }

            if ($response->successful()) {
                return ['ok' => true, 'data' => $response->json('data'), 'raw' => $response->json()];
            }

            return [
                'ok' => false,
                'status' => $response->status(),
                'error' => $response->serverError()
                    ? 'Airalo service is currently unavailable. Please try again later.'
                    : ($response->json('meta.message') ?? $response->json('message') ?? 'Request failed'),
                'raw' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error("Airalo Request Exception ({$method} {$endpoint}): ".$e->getMessage());

            $error = 'A connection error occurred while communicating with Airalo.';
            if (app()->environment('local', 'testing')) {
                $error .= ' Error: '.$e->getMessage();
            }

            return ['ok' => false, 'error' => $error, 'status' => 500];
        }
    }

    protected function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, $query);
    }

    /** Follow `meta.last_page` until the end (or `$maxPages`); stops quietly on the first failed page. */
    protected function getAllPages(string $endpoint, array $query = [], int $maxPages = 20): array
    {
        $items = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            $resp = $this->get($endpoint, [...$query, 'page' => $page]);
            $data = is_array($resp['data'] ?? null) ? $resp['data'] : [];

            if (! ($resp['ok'] ?? false) || $data === []) {
                break;
            }

            array_push($items, ...$data);

            $lastPage = (int) ($resp['raw']['meta']['last_page'] ?? $page);
            if ($page >= max($lastPage, 1)) {
                break;
            }
        }

        return $items;
    }

    /** Full package catalogue for `local` (countries) or `global` (regions), cached 6h. */
    protected function packageSnapshot(string $type): array
    {
        $type = $type === 'global' ? 'global' : 'local';

        return $this->rememberNonEmpty("airalo.packages_snapshot.v2.{$type}", now()->addHours(6),
            fn () => $this->getAllPages('packages', ['filter[type]' => $type]));
    }

    private function headers(): array
    {
        return ['Accept' => 'application/json'] + ($this->isSandbox ? ['airalo-sandbox' => 'true'] : []);
    }

    // ─── Formatting ─────────────────────────────────────────────────────────────

    /** Normalise Airalo packages to the bundle shape the storefront and apps expect. */
    protected function formatPackages(array $packages, ?string $filterType = null): array
    {
        $formatted = array_map(function ($p) {
            $price = (float) ($p['price'] ?? 0);
            // Voice plans are flagged per package (`voice`) or per operator (`_plan_type`).
            $hasVoice = ($p['voice'] ?? null) !== null || str_contains(strtolower($p['_plan_type'] ?? 'data'), 'voice');

            return [
                'id' => $p['id'],
                'data' => $p['data'] ?? '0 GB',
                'validity' => (int) ($p['day'] ?? $p['validity'] ?? 0).' days',
                'price' => $price,
                'price_formatted' => '$'.number_format($price, 2),
                'package_type' => $hasVoice ? 'DATA-VOICE-SMS' : 'DATA-ONLY',
                'can_renew' => true,
                'features' => [
                    'Hotspot' => 'Supported',
                    'Network' => 'LTE/5G',
                    'Activation' => 'Instant',
                    'Renewal' => 'Yes',
                ],
            ];
        }, $packages);

        return $filterType
            ? array_values(array_filter($formatted, fn ($f) => $f['package_type'] === $filterType))
            : $formatted;
    }

    /** Packages of the catalogue entry with `$slug` from one `packages` query. */
    private function packagesForSlug(array $query, string $slug, ?string $packageType): array
    {
        $resp = $this->get('packages', $query);
        if (! $resp['ok']) {
            return [];
        }

        $entry = collect($resp['data'])->first(fn ($e) => ($e['slug'] ?? '') === $slug);

        return $this->formatPackages($entry['packages'] ?? [], $packageType);
    }

    /** The country entry matching `$slug` (or the first result) from a country-filtered query. */
    private function fetchCountryAsset(string $slug, string $code): ?array
    {
        $resp = $this->get('packages', ['filter[country]' => $code]);

        if (! ($resp['ok'] ?? false) || empty($resp['data'])) {
            return null;
        }

        return collect($resp['data'])->first(fn ($item) => ($item['slug'] ?? '') === $slug) ?? $resp['data'][0];
    }

    private function assetSummary(array $item): array
    {
        return [
            'id' => $item['slug'] ?? $item['id'],
            'name' => $item['title'],
            'image' => $item['image']['url'] ?? null,
        ];
    }

    protected function countryCodeForSlug(string $slug): ?string
    {
        return [
            'united-states' => 'US',
            'united-kingdom' => 'GB',
            'nigeria' => 'NG',
            'france' => 'FR',
            'germany' => 'DE',
            'italy' => 'IT',
            'spain' => 'ES',
            'turkey' => 'TR',
            'china' => 'CN',
            'japan' => 'JP',
            'united-arab-emirates' => 'AE',
            'saudi-arabia' => 'SA',
        ][$slug] ?? null;
    }

    protected function countrySubtext(string $slug): string
    {
        return [
            'united-states' => 'Best for travel & business',
            'united-kingdom' => 'Great coverage in cities',
            'united-arab-emirates' => 'Fast setup for trips',
            'france' => 'Reliable data options',
            'turkey' => 'High speed connectivity',
            'spain' => 'Explore with ease',
            'italy' => 'Stay connected in Italy',
            'china' => 'Seamless data for China',
        ][$slug] ?? 'Flexible plans available';
    }

    protected function countryTag(string $slug): ?string
    {
        return [
            'united-states' => 'Top pick',
            'united-kingdom' => 'Popular',
            'united-arab-emirates' => 'Trending',
            'france' => 'Value',
            'germany' => 'Hot',
            'saudi-arabia' => 'Hot',
        ][$slug] ?? null;
    }

    /** Redact credentials and activation secrets before logging a payload. */
    protected function maskSensitiveData(array $data): array
    {
        $sensitive = ['client_id', 'client_secret', 'access_token', 'activation_code', 'lpa'];

        foreach ($data as $key => $value) {
            if (in_array($key, $sensitive, true)) {
                $data[$key] = '********';
            } elseif (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            }
        }

        return $data;
    }

    // ─── Utilities ──────────────────────────────────────────────────────────────

    /** Cache a computed list, but never cache an empty one (a provider outage shouldn't stick). */
    private function rememberNonEmpty(string $key, \DateTimeInterface $ttl, callable $compute): array
    {
        $cached = Cache::get($key);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $value = $compute();
        if ($value !== []) {
            Cache::put($key, $value, $ttl);
        }

        return $value;
    }
}
