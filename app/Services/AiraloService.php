<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiraloService
{
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

    /**
     * Get OAuth2 Token
     */
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
            $headers = [
                'Accept' => 'application/json',
            ];

            if ($this->isSandbox) {
                $headers['airalo-sandbox'] = 'true';
            }

            $response = Http::asForm()
                ->withHeaders($headers)
                ->post("{$this->baseUrl}/token", [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->successful()) {
                $token = $response->json('data.access_token');
                if (is_string($token) && trim($token) !== '') {
                    Cache::put($cacheKey, $token, now()->addMinutes(50));
                    return $token;
                }
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

    /**
     * Internal request helper with automatic re-authentication
     */
    protected function request(string $method, string $endpoint, array $data = [], bool $isRetry = false): array
    {
        $token = $this->getToken($isRetry);
        if (!$token) return ['ok' => false, 'error' => 'Authentication failed'];

        try {
            $headers = [
                'Accept' => 'application/json',
            ];
            
            if ($this->isSandbox) {
                $headers['airalo-sandbox'] = 'true';
            }

            $http = Http::withToken($token)
                ->withHeaders($headers)
                ->timeout(30)
                ->retry(2, 100, function ($exception) {
                    return $exception instanceof \Illuminate\Http\Client\ConnectionException;
                }, throw: false);
            
            $url = "{$this->baseUrl}/{$endpoint}";
            $method = strtolower($method);
            
            $response = ($method === 'get') 
                ? $http->get($url, $data) 
                : $http->post($url, $data);

            // Detailed logging for debugging and audit
            Log::info("Airalo API Request: {$method} {$endpoint}", [
                'url' => $url,
                'status' => $response->status(),
                'is_retry' => $isRetry,
                'payload' => ($method === 'post') ? $this->maskSensitiveData($data) : null,
                'response_meta' => $response->json('meta') ?? [],
            ]);

            // Handle token expiration (401)
            if ($response->status() === 401 && !$isRetry) {
                Log::warning('Airalo Token Expired (401), Retrying...', ['endpoint' => $endpoint]);
                return $this->request($method, $endpoint, $data, true);
            }

            if ($response->successful()) {
                return ['ok' => true, 'data' => $response->json('data'), 'raw' => $response->json()];
            }

            $errorMsg = $response->json('meta.message') ?? $response->json('message') ?? 'Request failed';
            if ($response->serverError()) {
                $errorMsg = 'Airalo service is currently unavailable. Please try again later.';
            }

            return [
                'ok' => false,
                'status' => $response->status(),
                'error' => $errorMsg,
                'raw' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error("Airalo Request Exception ({$method} {$endpoint}): ".$e->getMessage());
            $errorMsg = 'A connection error occurred while communicating with Airalo.';
            if (app()->environment('local', 'testing')) {
                $errorMsg .= ' Error: '.$e->getMessage();
            }
            return ['ok' => false, 'error' => $errorMsg, 'status' => 500];
        }
    }

    /**
     * Generic GET request with Auth
     */
    protected function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, $query);
    }

    protected function getAllPages(string $endpoint, array $query = [], int $maxPages = 20): array
    {
        $page = 1;
        $lastPage = 1;
        $items = [];

        while ($page <= $lastPage && $page <= $maxPages) {
            $resp = $this->get($endpoint, array_merge($query, ['page' => $page]));
            if (! ($resp['ok'] ?? false)) {
                break;
            }

            $data = $resp['data'] ?? [];
            if (! is_array($data)) {
                $data = [];
            }
            if (count($data) === 0) {
                break;
            }

            $items = array_merge($items, $data);

            $meta = is_array($resp['raw']['meta'] ?? null) ? (array) $resp['raw']['meta'] : [];
            $lp = isset($meta['last_page']) ? (int) $meta['last_page'] : $page;
            $lastPage = $lp > 0 ? $lp : $page;

            if ($page >= $lastPage) {
                break;
            }

            $page++;
        }

        return $items;
    }

    protected function packageSnapshot(string $type): array
    {
        $type = $type === 'global' ? 'global' : 'local';
        $cacheKey = "airalo.packages_snapshot.v2.{$type}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && count($cached) > 0) {
            return $cached;
        }

        $list = $this->getAllPages('packages', ['filter[type]' => $type]);
        if (count($list) > 0) {
            Cache::put($cacheKey, $list, now()->addHours(6));
        }

        return $list;
    }

    protected function countryCodeForSlug(string $slug): ?string
    {
        $map = [
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
        ];

        return $map[$slug] ?? null;
    }

    /**
     * Catalog: Popular Countries
     */
    public function popularCountries(int $limit = 6): array
    {
        $countries = $this->allCountriesWithPrices();
        return array_slice($countries, 0, $limit);
    }

    /**
     * Catalog: Popular Regions
     */
    public function popularRegions(int $limit = 7): array
    {
        $regions = $this->allRegionsWithPrices();
        return array_slice($regions, 0, $limit);
    }

    /**
     * Searchable Assets (for autocomplete/search)
     */
    public function searchableAssets(): array
    {
        $cacheKey = 'airalo.searchable_assets.v1';
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && count($cached) > 0) {
            return $cached;
        }

        $countries = array_map(fn ($c) => ['type' => 'country', 'id' => $c['id'], 'name' => $c['name']], $this->countries());
        $regions = array_map(fn ($r) => ['type' => 'region', 'id' => $r['id'], 'name' => $r['name']], $this->continents());
        $regions[] = ['type' => 'region', 'id' => 'global', 'name' => 'Global'];

        $assets = array_values(array_filter(array_merge($countries, $regions), function ($row) {
            if (! is_array($row)) {
                return false;
            }
            return trim((string) ($row['id'] ?? '')) !== '' && trim((string) ($row['name'] ?? '')) !== '';
        }));

        if (count($assets) > 0) {
            Cache::put($cacheKey, $assets, now()->addHours(24));
        }

        return $assets;
    }

    /**
     * All Countries with their starting prices
     */
    public function allCountriesWithPrices(string $packageType = 'DATA-ONLY'): array
    {
        $cacheKey = "airalo.all_countries_prices.v2.{$packageType}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && count($cached) > 0) {
            return $cached;
        }

        $list = $this->packageSnapshot('local');
        if (empty($list)) {
            return [];
        }

        $results = [];

        foreach ($list as $item) {
            $operators = $item['operators'] ?? [];
            $prices = [];
            foreach ($operators as $op) {
                $pkgs = $op['packages'] ?? [];
                foreach ($pkgs as $p) {
                    if (isset($p['price']) && is_numeric($p['price'])) {
                        $prices[] = (float) $p['price'];
                    }
                }
            }

            $minPrice = ! empty($prices) ? min($prices) : 0;
            $slug = (string) ($item['slug'] ?? '');

            $results[] = [
                'id' => $slug,
                'name' => $item['title'],
                'code' => strtoupper($slug),
                'flag' => $this->flagEmoji($slug),
                'flag_url' => $item['image']['url'] ?? null,
                'starting_price' => (float) $minPrice,
                'starting_price_formatted' => '$'.number_format($minPrice, 2),
                'subtext' => $this->getCountrySubtext($slug),
                'tag' => $this->getCountryTag($slug),
            ];
        }

        if (count($results) > 0) {
            Cache::put($cacheKey, $results, now()->addHours(6));
        }

        return $results;
    }

    /**
     * Helper: Get descriptive subtext for countries
     */
    protected function getCountrySubtext(string $slug): string
    {
        $texts = [
            'united-states' => 'Best for travel & business',
            'united-kingdom' => 'Great coverage in cities',
            'united-arab-emirates' => 'Fast setup for trips',
            'france' => 'Reliable data options',
            'germany' => 'Flexible plans available',
            'saudi-arabia' => 'Flexible plans available',
            'turkey' => 'High speed connectivity',
            'spain' => 'Explore with ease',
            'italy' => 'Stay connected in Italy',
            'china' => 'Seamless data for China',
        ];

        return $texts[$slug] ?? 'Flexible plans available';
    }

    /**
     * Helper: Get marketing tag for countries
     */
    protected function getCountryTag(string $slug): ?string
    {
        $tags = [
            'united-states' => 'Top pick',
            'united-kingdom' => 'Popular',
            'united-arab-emirates' => 'Trending',
            'france' => 'Value',
            'germany' => 'Hot',
            'saudi-arabia' => 'Hot',
        ];

        return $tags[$slug] ?? null;
    }

    /**
     * All Regions with starting prices
     */
    public function allRegionsWithPrices(): array
    {
        $cacheKey = 'airalo.all_regions_prices.v1';
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && count($cached) > 0) {
            return $cached;
        }

        $list = $this->packageSnapshot('global');
        if (empty($list)) {
            return [];
        }

        $results = [];

        foreach ($list as $item) {
            $results[] = [
                'id' => $item['slug'] ?? $item['id'],
                'name' => $item['title'],
                'code' => 'UN',
                'flag' => '🌐',
                'flag_url' => $item['image']['url'] ?? null,
                'starting_price' => 5.00,
                'starting_price_formatted' => '$5.00',
            ];
        }

        if (count($results) > 0) {
            Cache::put($cacheKey, $results, now()->addHours(6));
        }

        return $results;
    }

    /**
     * Get Asset Details (Country or Region)
     */
    public function getAssetDetails(string $type, string $id, ?string $packageType = 'DATA-ONLY'): ?array
    {
        $cacheKey = "airalo.asset_details.v3.{$type}.{$id}.{$packageType}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && count($cached) > 0) {
            return $cached;
        }

        $assetInfo = null;

        if ($type === 'country') {
            $code = $this->countryCodeForSlug($id);
            if ($code) {
                $detailResp = $this->get('packages', ['filter[country]' => $code]);

                if (($detailResp['ok'] ?? false) && ! empty($detailResp['data'])) {
                    $assetInfo = collect($detailResp['data'])->first(fn($item) => ($item['slug'] ?? '') === $id) ?? $detailResp['data'][0];
                }
            }
        }

        // Fall back to the cached catalog snapshot for regions or countries that
        // do not have a direct slug-to-ISO mapping yet.
        if (! $assetInfo || empty($assetInfo['operators'])) {
            $filterType = ($type === 'region') ? 'global' : 'local';
            $list = $this->packageSnapshot($filterType);
            if (empty($list)) return null;

            $assetInfo = collect($list)->first(fn($item) => ($item['slug'] ?? '') === $id);
        }

        if ($type === 'country' && (! $assetInfo || empty($assetInfo['operators']))) {
            $code = $this->countryCodeForSlug($id) ?? strtoupper($id);
            $detailResp = $this->get('packages', ['filter[country]' => $code]);

            if (($detailResp['ok'] ?? false) && !empty($detailResp['data'])) {
                $assetInfo = collect($detailResp['data'])->first(fn($item) => ($item['slug'] ?? '') === $id) ?? $detailResp['data'][0];
            }
        }

        if (!$assetInfo) return null;

        // Flatten packages from all operators
        $allPackages = [];
        $operators = $assetInfo['operators'] ?? [];
        foreach ($operators as $operator) {
            $opPackages = $operator['packages'] ?? [];
            foreach ($opPackages as $pkg) {
                // Attach operator info if needed
                $pkg['_operator_title'] = $operator['title'] ?? '';
                $pkg['_plan_type'] = $operator['plan_type'] ?? 'data';
                $allPackages[] = $pkg;
            }
        }

        $countryCode = $assetInfo['country_code'] ?? $assetInfo['slug'];

        $details = [
            'name' => $assetInfo['title'],
            'code' => strtoupper($countryCode),
            'flag' => $this->flagEmoji($countryCode),
            'flag_url' => $assetInfo['image']['url'] ?? null,
            'type' => ($type === 'region') ? 'Regional Plan' : 'Country eSIM',
            'bundles' => $this->formatPackages($allPackages, $packageType),
        ];

        Cache::put($cacheKey, $details, now()->addMinutes(30));

        return $details;
    }

    /**
     * Helper: Flag Emoji
     */
    public function flagEmoji(?string $code): string
    {
        if (!$code) return '🌐';
        $code = strtoupper(trim($code));
        if (strlen($code) !== 2) return '🌐';

        $chars = array_map(function ($c) {
            return mb_chr(ord($c) + 127397);
        }, str_split($code));

        return implode('', $chars);
    }

    /**
     * Diagnostic: Get Raw Packages (Public for debugging)
     */
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

    /**
     * Catalog: Countries
     */
    public function countries(): array
    {
        $list = $this->packageSnapshot('local');
        if (empty($list)) {
            return [];
        }

        return array_map(function ($item) {
            return [
                'id' => $item['slug'] ?? $item['id'],
                'name' => $item['title'],
                'image' => $item['image']['url'] ?? null,
            ];
        }, $list);
    }

    /**
     * Catalog: Continents (Regional)
     */
    public function continents(): array
    {
        $list = $this->packageSnapshot('global');
        if (empty($list)) {
            return [];
        }

        // Filter out the 'world' slug to only show regional
        $regional = array_filter($list, fn($item) => ($item['slug'] ?? '') !== 'world');
        
        return array_map(function ($item) {
            return [
                'id' => $item['slug'] ?? $item['id'],
                'name' => $item['title'],
                'image' => $item['image']['url'] ?? null,
            ];
        }, array_values($regional));
    }

    /**
     * Packages for a specific country
     */
    public function packagesByCountryId(string $slug, ?string $packageType = null): array
    {
        $resp = $this->get('packages', ['filter[country]' => strtoupper($slug)]);
        if (!$resp['ok']) return [];

        // Airalo returns a list of countries/operators, we need to find the specific one
        $country = collect($resp['data'])->first(fn($c) => ($c['slug'] ?? '') === $slug);
        return $this->formatPackages($country['packages'] ?? [], $packageType);
    }

    /**
     * Packages for a specific region
     */
    public function packagesByContinentId(string $slug, ?string $packageType = null): array
    {
        $resp = $this->get('packages', ['filter[type]' => 'global']);
        if (!$resp['ok']) return [];

        $region = collect($resp['data'])->first(fn($r) => ($r['slug'] ?? '') === $slug);
        return $this->formatPackages($region['packages'] ?? [], $packageType);
    }

    /**
     * Global Packages
     */
    public function packagesGlobal(?string $packageType = null): array
    {
        $resp = $this->get('packages', ['filter[type]' => 'global']);
        if (!$resp['ok']) return [];

        $world = collect($resp['data'])->first(fn($w) => ($w['slug'] ?? '') === 'world');
        return $this->formatPackages($world['packages'] ?? [], $packageType);
    }

    /**
     * Format Airalo packages to match frontend expectations
     */
    protected function formatPackages(array $packages, ?string $filterType = null): array
    {
        $formatted = array_map(function ($p) {
            $dataGb = (float) ($p['data'] ?? 0);
            $validityDays = (int) ($p['day'] ?? $p['validity'] ?? 0);
            $price = (float) ($p['price'] ?? 0);

            // Airalo v2: plan_type is on operator level (passed as _plan_type)
            // or check if 'voice'/'text' fields exist in the package
            $planType = $p['_plan_type'] ?? 'data';
            $hasVoice = ($p['voice'] ?? null) !== null || str_contains(strtolower($planType), 'voice');
            $type = $hasVoice ? 'DATA-VOICE-SMS' : 'DATA-ONLY';

            return [
                'id' => $p['id'],
                'data' => $p['data'] ?? '0 GB',
                'validity' => $validityDays . ' days',
                'price' => $price,
                'price_formatted' => '$'.number_format($price, 2),
                'package_type' => $type,
                'can_renew' => true,
                'features' => [
                    'Hotspot' => 'Supported',
                    'Network' => 'LTE/5G',
                    'Activation' => 'Instant',
                    'Renewal' => 'Yes',
                ],
            ];
        }, $packages);

        if ($filterType) {
            // Be lenient: if the package doesn't have voice, it's data-only.
            // If the user wants data-only, they should get everything that isn't explicitly data+calls.
            return array_values(array_filter($formatted, function($f) use ($filterType) {
                if ($filterType === 'DATA-ONLY') {
                    return $f['package_type'] === 'DATA-ONLY';
                }
                return $f['package_type'] === $filterType;
            }));
        }

        return $formatted;
    }

    /**
     * Purchase eSIM
     */
    public function fulfillEsim(string $packageId, string $reference, string $customerEmail, string $packageType = 'DATA-ONLY'): array
    {
        $resp = $this->request('POST', 'orders', [
            'package_id' => $packageId,
            'quantity' => 1,
        ]);

        if ($resp['ok']) {
            $order = $resp['data'];
            $sim = $order['sims'][0] ?? null;

            if (!$sim) {
                Log::error('Airalo Order Success but No SIM', [
                    'package_id' => $packageId,
                    'reference' => $reference,
                    'response' => $order,
                ]);
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
                'activation_code' => $sim['lpa'], // Airalo uses lpa field
                'lpa' => $sim['lpa'],
                'qr_code_url' => $sim['qrcode_url'],
                'smdp_address' => 'rsp.airalo.com', // Common Airalo SM-DP+
                'direct_installation_link_ios' => $sim['installation_guides']['ios'] ?? null,
                'direct_installation_link_android' => $sim['installation_guides']['android'] ?? null,
                'raw' => $order,
            ];
        }

        Log::error('Airalo Purchase Failed', [
            'status' => $resp['status'] ?? 500,
            'error' => $resp['error'] ?? 'Unknown error',
            'package_id' => $packageId,
            'reference' => $reference,
        ]);

        return [
            'ok' => false,
            'error' => $resp['error'] ?? 'Purchase failed',
        ];
    }

    /**
     * Topup existing eSIM
     */
    public function topupEsim(string $iccid, string $packageId, string $reference, string $customerEmail): array
    {
        $iccid = trim($iccid);
        if (!$this->isValidIccid($iccid)) {
            return ['ok' => false, 'error' => 'Invalid ICCID format'];
        }

        $resp = $this->request('POST', 'orders', [
            'package_id' => $packageId,
            'quantity' => 1,
            'iccid' => $iccid,
        ]);

        if ($resp['ok']) {
            $order = $resp['data'];
            return [
                'ok' => true,
                'reference' => $reference,
                'esim_id' => $iccid, // In Airalo, ICCID is the primary identifier for topups
                'order_id' => $order['id'],
                'iccid' => $iccid,
                'raw' => $order,
            ];
        }

        $status = $resp['status'] ?? 500;
        $error = $resp['error'] ?? 'Top-up failed';
        
        if ($status === 404) {
            $error = "eSIM (ICCID: {$iccid}) not found in Airalo's system";
        }

        Log::error('Airalo Topup Failed', [
            'status' => $status,
            'error' => $error,
            'iccid' => $iccid,
            'package_id' => $packageId,
            'reference' => $reference,
        ]);

        return [
            'ok' => false,
            'status' => $status,
            'error' => $error,
        ];
    }

    /**
     * Get SIM details
     */
    public function getEsimDetails(string $iccid): array
    {
        $iccid = trim($iccid);
        if (!$this->isValidIccid($iccid)) {
            return ['ok' => false, 'error' => 'Invalid ICCID format'];
        }

        $resp = $this->get("sims/{$iccid}");
        if (!$resp['ok']) {
            if (($resp['status'] ?? 0) === 404) {
                return ['ok' => false, 'status' => 404, 'error' => 'eSIM not found'];
            }
            return $resp;
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
            'smdp_address' => 'rsp.airalo.com',
            'esim_status' => $sim['status'],
            'raw' => $sim,
        ];
    }

    /**
     * Sync/Refresh SIM
     */
    public function syncEsim(string $iccid): array
    {
        return $this->getEsimDetails($iccid);
    }

    /**
     * Helper: Validate ICCID format (18-22 digits)
     */
    public function isValidIccid(string $iccid): bool
    {
        return preg_match('/^[0-9]{18,22}$/', $iccid) === 1;
    }

    /**
     * Helper: Mask sensitive data for logs
     */
    protected function maskSensitiveData(array $data): array
    {
        $sensitiveFields = ['client_id', 'client_secret', 'access_token', 'activation_code', 'lpa'];
        foreach ($data as $key => $value) {
            if (in_array($key, $sensitiveFields)) {
                $data[$key] = '********';
            } elseif (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            }
        }
        return $data;
    }
}
