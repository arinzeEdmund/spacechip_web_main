<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * GloEsim dealer API client: catalogue browsing, eSIM purchase/top-up and delivery lookups.
 *
 * The dealer API is loosely specified: response shapes, key casing and even endpoint names vary between
 * environments, so most readers probe several candidate paths and keep the first non-null value.
 * Catalogue reads never throw; they degrade to an empty list (or a static fallback for marketing tiles).
 */
class GloEsimService
{
    private const UN_FLAG = 'https://flagcdn.com/w80/un.png';

    private const PURCHASE_ENDPOINT = '/developer/dealer/package/purchase';

    private const VOICE_PURCHASE_ENDPOINT = '/developer/dealer/package/data_voice_sms/purchase';

    /** Endpoint aliases seen across dealer environments for "list my eSIMs". */
    private const MY_ESIMS_ENDPOINTS = ['/developer/dealer/my_esims', '/developer/dealer/my-esims', '/developer/dealer/my_esims/list'];

    /** Candidate paths for each delivery field, in priority order (purchase response and details lookups). */
    private const DELIVERY_PATHS = [
        'iccid' => ['data.iccid', 'iccid', 'data.items.0.iccid', 'data.esim.iccid'],
        'activation_code' => ['data.activation_code', 'activation_code', 'data.items.0.activation_code', 'data.esim.activation_code', 'data.esim.activationCode'],
        'lpa' => ['data.lpa', 'lpa', 'data.lpa_code', 'lpa_code', 'data.lpa_string', 'lpa_string', 'data.qr_payload', 'qr_payload'],
        'qr_code_url' => ['data.qr_code_url', 'qr_code_url', 'data.items.0.qr_code_url', 'data.esim.qr_code_url', 'data.esim.qrCodeUrl', 'data.qr_code'],
        'puk_code' => ['data.puk_code', 'puk_code', 'data.puk', 'puk'],
        'number' => ['data.number', 'number', 'data.msisdn', 'msisdn'],
        'esim_status' => ['data.status', 'status'],
        'ios' => ['data.direct_installation_link_ios', 'direct_installation_link_ios', 'data.installation_link_ios', 'installation_link_ios', 'data.ios_installation_link', 'ios_installation_link', 'data.ios_install_link', 'ios_install_link'],
        'android' => ['data.direct_installation_link_android', 'direct_installation_link_android', 'data.installation_link_android', 'installation_link_android', 'data.android_installation_link', 'android_installation_link', 'data.android_install_link', 'android_install_link'],
        'smdp_address' => ['data.smdp_address', 'smdp_address', 'data.smdpAddress', 'smdpAddress', 'data.smdp_plus_address', 'smdp_plus_address', 'data.smdpPlusAddress', 'smdpPlusAddress', 'data.smdp', 'smdp'],
    ];

    /** Extra paths only the my-esims/details endpoints use (list rows and universal links). */
    private const DETAILS_EXTRA_PATHS = [
        'iccid' => ['data.0.iccid'],
        'activation_code' => ['data.0.activation_code'],
        'lpa' => ['data.qr_code_text', 'qr_code_text', 'data.0.qr_code_text', 'data.items.0.qr_code_text'],
        'qr_code_url' => ['data.0.qr_code_url'],
        'number' => ['data.0.number', 'data.0.msisdn'],
        'esim_status' => ['data.0.status', 'data.items.0.status'],
        'ios' => ['data.universal_link', 'universal_link', 'data.0.universal_link', 'data.items.0.universal_link'],
        'android' => ['data.android_universal_link', 'android_universal_link', 'data.0.android_universal_link', 'data.items.0.android_universal_link'],
        'smdp_address' => ['data.0.smdp_address', 'data.items.0.smdp_address'],
    ];

    // ─── Marketing tiles ────────────────────────────────────────────────────────

    public function popularCountries(int $limit = 6): array
    {
        $limit = max(1, min(24, $limit));

        return $this->rememberNonEmpty("gloesim.popular_countries.v3.{$limit}", 15, fn () => $this->popularCountriesFromApi($limit))
            ?: $this->fallbackPopularCountries($limit);
    }

    public function popularRegions(int $limit = 7): array
    {
        $limit = max(1, min(24, $limit));

        return $this->rememberNonEmpty("gloesim.popular_regions.v1.{$limit}", 30, fn () => $this->popularRegionsFromApi($limit))
            ?: $this->fallbackPopularRegions($limit);
    }

    private function popularCountriesFromApi(int $limit): array
    {
        if (! $this->liveCatalogueEnabled()) {
            return [];
        }

        try {
            $byName = $this->countriesIndexByNormalizedName();
            $byId = $this->countriesIndexById();

            $preferred = ['United States', 'United Kingdom', 'United Arab Emirates', 'France', 'Germany', 'Turkey', 'Saudi Arabia', 'Egypt', 'Nigeria', 'India', 'Canada', 'Australia', 'Japan', 'Thailand', 'South Africa', 'Brazil', 'Mexico', 'Italy', 'Spain'];

            $selected = collect($preferred)
                ->map(fn ($name) => $byName[$this->normalizeCountryName($name)] ?? null)
                ->filter(fn ($node) => is_array($node))
                ->take($limit)
                ->values();

            $currency = $this->currency();

            return $selected->map(function (array $country, int $rank) use ($byId, $currency) {
                $id = $country['id'] ?? null;
                $code = $byId[$id]['code'] ?? null;
                $min = ($id !== null && $id !== '') ? $this->cheapest($this->packagesByCountryId($id, 'DATA-ONLY')) : null;

                return [
                    'id' => $id,
                    'code' => $code,
                    'name' => is_string($country['name'] ?? null) ? $country['name'] : null,
                    'flag' => $this->flagEmoji($code),
                    'flag_url' => is_string($country['image_url'] ?? null) ? $country['image_url'] : null,
                    'note' => $this->noteForRank($rank),
                    'badge' => $this->badgeForRank($rank),
                    'starting_price' => $min,
                    'starting_price_formatted' => $this->formatMoney($min, $currency),
                ];
            })->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** "Global" first, then the preferred continents (by alias), topped up with whatever else the API lists. */
    private function popularRegionsFromApi(int $limit): array
    {
        if (! $this->liveCatalogueEnabled()) {
            return [];
        }

        try {
            $byName = [];
            foreach ($this->continents() as $continent) {
                $id = data_get($continent, 'id');
                $name = data_get($continent, 'name');

                if (is_array($continent) && is_scalar($id) && is_string($name) && trim($name) !== '') {
                    $byName[$this->normalizeCountryName($name)] = ['id' => trim((string) $id), 'name' => trim($name)];
                }
            }

            $currency = $this->currency();
            $globalMin = $this->cheapest($this->globalPackages('DATA-ONLY', 1, 200));

            $ranked = [[
                'id' => 'global',
                'name' => 'Global',
                'slug' => 'global',
                'note' => 'Best for international travel',
                'badge' => 'World wide',
                'starting_price' => $globalMin,
                'starting_price_formatted' => $this->formatMoney($globalMin, $currency),
                'flag' => '🌐',
                'flag_url' => self::UN_FLAG,
            ]];

            $slots = max(0, $limit - 1);
            $preferred = [
                'North America' => ['North America', 'N. America'],
                'Asia' => ['Asia'],
                'Europe' => ['Europe'],
                'Latin America' => ['Latin America', 'Latin America & Caribbean', 'Latin America and Caribbean', 'South America', 'Central America'],
                'Middle East' => ['Middle East', 'Middle East & Africa', 'Middle East and Africa', 'MENA'],
                'Oceania' => ['Oceania', 'Australia & Oceania', 'Australia and Oceania'],
            ];

            $selected = [];
            foreach ($preferred as $label => $aliases) {
                if (count($selected) >= $slots) {
                    break;
                }
                foreach ($aliases as $alias) {
                    $key = $this->normalizeCountryName($alias);
                    if (isset($byName[$key]) && ! isset($selected[$key])) {
                        $selected[$key] = ['label' => $label] + $byName[$key];
                        break;
                    }
                }
            }
            foreach ($byName as $key => $node) {
                if (count($selected) >= $slots) {
                    break;
                }
                $selected[$key] ??= $node;
            }

            foreach ($selected as $node) {
                $display = $node['label'] ?? $node['name'];
                $min = $node['id'] !== '' ? $this->cheapest($this->packagesByContinentId($node['id'], 'DATA-ONLY')) : null;

                $ranked[] = [
                    'id' => $node['id'],
                    'name' => $display,
                    'slug' => $this->slugify($display),
                    'note' => $this->noteForRegion($display),
                    'badge' => $this->badgeForRegion($display),
                    'starting_price' => $min,
                    'starting_price_formatted' => $this->formatMoney($min, $currency),
                    'flag' => '🌐',
                    'flag_url' => self::UN_FLAG,
                ];
            }

            return array_slice($ranked, 0, $limit);
        } catch (\Throwable) {
            return [];
        }
    }

    // ─── Catalogue ──────────────────────────────────────────────────────────────

    public function balance(): ?array
    {
        return $this->getJson('/developer/dealer/balance');
    }

    public function packages(string $packageType = 'DATA-ONLY'): array
    {
        return $this->extractList($this->getJson('/developer/dealer/packages', ['package_type' => $packageType]));
    }

    public function countries(): array
    {
        return $this->extractList($this->getJson('/developer/dealer/packages/country'));
    }

    public function continents(): array
    {
        return $this->extractList($this->getJson('/developer/dealer/packages/continent'));
    }

    /**
     * Country packages, filtered client-side because the endpoint sometimes ignores `package_type`.
     * Voice/SMS bundles are often only listed globally, so for that type we fall back to scanning
     * global packages that cover the country.
     */
    public function packagesByCountryId(int|string $countryId, ?string $packageType = null): array
    {
        $packages = $this->extractList($this->getJson(
            '/developer/dealer/packages/country/'.urlencode((string) $countryId),
            $packageType ? ['package_type' => $packageType] : [],
        ));

        if (! $packageType) {
            return $packages;
        }

        $wanted = $this->normalizePackageType($packageType);
        $filtered = $this->filterByType($packages, $wanted);

        if ($filtered !== [] || $wanted !== 'DATA-VOICE-SMS') {
            return $filtered;
        }

        return array_values(array_filter(
            $this->allGlobalPackages($packageType),
            fn ($p) => is_array($p) && collect((array) data_get($p, 'countries', []))
                ->contains(fn ($c) => ($id = (string) data_get($c, 'id')) !== '' && $id === (string) $countryId),
        ));
    }

    public function packagesByContinentId(int|string $continentId, ?string $packageType = null): array
    {
        $packages = $this->extractList($this->getJson(
            '/developer/dealer/packages/continent/'.urlencode((string) $continentId),
            $packageType ? ['package_type' => $packageType] : [],
        ));

        return $packageType ? $this->filterByType($packages, $this->normalizePackageType($packageType)) : $packages;
    }

    public function globalPackages(?string $packageType = 'DATA-ONLY', int $page = 1, int $perPage = 100): array
    {
        $query = array_filter([
            'package_type' => $packageType ?: null,
            'page' => max(1, $page),
            'per_page' => max(1, min(200, $perPage)),
        ]);

        $packages = $this->extractList($this->getJson('/developer/dealer/packages/global', $query, 6));

        if (! $packageType) {
            return $packages;
        }

        $wanted = $this->normalizePackageType($packageType);

        // Untyped global packages are kept: the global endpoint already honours the filter.
        return array_values(array_filter($packages, function ($p) use ($wanted) {
            $type = is_array($p) ? $this->normalizePackageType(data_get($p, 'package_type')) : null;

            return $type !== null && ($type === '' || $type === $wanted || str_starts_with($type, $wanted));
        }));
    }

    /** Every country with its cheapest global price. Countries without a price are hidden unless none are priced. */
    public function allCountriesWithPrices(string $packageType = 'DATA-ONLY'): array
    {
        $cacheKey = 'gloesim.all_countries_prices.v2.'.$packageType;
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $countries = $this->countries();
        if ($countries === []) {
            return [];
        }

        // Cheapest price per country id across all global packages (time-boxed: this runs on page load).
        $prices = [];
        foreach ($this->allGlobalPackages($packageType, deadline: microtime(true) + 6.0) as $package) {
            $amount = is_array($package) ? $this->extractAmount($package) : null;
            if ($amount === null) {
                continue;
            }
            foreach ((array) data_get($package, 'countries', []) as $c) {
                $id = (string) data_get($c, 'id');
                $prices[$id] = min($prices[$id] ?? INF, $amount);
            }
        }

        $currency = $this->currency();
        $rows = [];
        foreach ($countries as $country) {
            $id = (string) (data_get($country, 'id') ?? '');
            if ($id === '') {
                continue;
            }
            $code = $this->extractCountryCode($country);
            $price = $prices[$id] ?? null;

            $rows[] = [
                'id' => $id,
                'name' => (string) data_get($country, 'name'),
                'code' => $code,
                'flag' => $this->flagEmoji($code),
                'flag_url' => data_get($country, 'image_url'),
                'starting_price' => $price,
                'starting_price_formatted' => $price !== null ? $this->formatMoney($price, $currency) : null,
            ];
        }
        usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $priced = array_values(array_filter($rows, fn ($r) => $r['starting_price'] !== null));
        if ($priced === []) {
            return $rows;
        }

        Cache::put($cacheKey, $priced, now()->addHours(6));

        return $priced;
    }

    public function allRegionsWithPrices(): array
    {
        return $this->popularRegions(20);
    }

    public function allVirtualNumbers(): array
    {
        return Cache::remember('gloesim.all_virtual_numbers.v1', now()->addHours(6), function () {
            $currency = $this->currency();

            return array_map(function ($package) use ($currency) {
                $amount = $this->extractAmount($package);
                $first = Arr::first((array) data_get($package, 'countries', []));
                $code = $first ? $this->extractCountryCode($first) : null;

                return [
                    'id' => data_get($package, 'id'),
                    'name' => data_get($package, 'name'),
                    'description' => data_get($package, 'description'),
                    'price' => $amount,
                    'price_formatted' => $this->formatMoney($amount, $currency),
                    'flag' => $this->flagEmoji($code),
                    'flag_url' => data_get($first, 'image_url'),
                ];
            }, $this->packages('VIRTUAL-PHONE-NUMBER'));
        });
    }

    /** Detail page payload for a country, a continent, or the special "global" region. */
    public function getAssetDetails(string $type, string $id, ?string $packageType = 'DATA-ONLY'): ?array
    {
        return Cache::remember("gloesim.asset_details.{$type}.{$id}.{$packageType}.v3", now()->addMinutes(15), function () use ($type, $id, $packageType) {
            $currency = $this->currency();

            if ($type === 'country') {
                $country = $this->countriesIndexById()[$id] ?? null;

                return $country ? [
                    'name' => $country['name'],
                    'code' => $country['code'],
                    'flag' => $this->flagEmoji($country['code']),
                    'flag_url' => $country['image_url'] ?? null,
                    'type' => 'Country eSIM',
                    'bundles' => $this->formatBundles($this->packagesByCountryId($id, $packageType), $currency),
                ] : null;
            }

            if ($type !== 'region') {
                return null;
            }

            if ($id === 'global') {
                $name = 'Global';
                $code = 'UN';
                $packages = $this->globalPackages($packageType, 1, 100);
            } else {
                $continent = collect($this->continents())->first(fn ($c) => (string) data_get($c, 'id') === $id);
                if (! $continent) {
                    return null;
                }
                $name = data_get($continent, 'name');
                $code = null;
                $packages = $this->packagesByContinentId($id, $packageType);
            }

            return [
                'name' => $name,
                'code' => $code,
                'flag' => '🌐',
                'flag_url' => self::UN_FLAG,
                'type' => 'Regional Plan',
                'bundles' => $this->formatBundles($packages, $currency),
            ];
        });
    }

    /** Search index: every country and continent, plus "Global". */
    public function searchableAssets(): array
    {
        return Cache::remember('gloesim.searchable_assets.v1', now()->addHours(6), function () {
            $results = [];

            foreach ($this->countries() as $country) {
                $id = $this->pick($country, ['id', 'country_id', 'countryId']);
                $name = $this->pick($country, ['name', 'country_name', 'countryName']);
                if ($id && $name) {
                    $results[] = ['id' => (string) $id, 'name' => (string) $name, 'type' => 'country'];
                }
            }

            foreach ($this->continents() as $continent) {
                $id = data_get($continent, 'id');
                $name = data_get($continent, 'name');
                if ($id && $name) {
                    $results[] = ['id' => (string) $id, 'name' => (string) $name, 'type' => 'region'];
                }
            }

            $results[] = ['id' => 'global', 'name' => 'Global', 'type' => 'region'];

            return $results;
        });
    }

    // ─── Orders ─────────────────────────────────────────────────────────────────

    /**
     * Buy a package. A purchase without an ICCID (or with sim_applied=false) is reported as
     * `pending`: the order exists and delivery details must be polled with getEsimDetails().
     */
    public function fulfillEsim(string $packageTypeId, string $reference, string $customerEmail, string $packageType = 'DATA-ONLY', array $customerDetails = []): array
    {
        $type = $this->normalizePackageType($packageType) ?: 'DATA-ONLY';
        $isVoice = $type === 'DATA-VOICE-SMS';

        $payload = ['package_type_id' => $packageTypeId, 'email' => $customerEmail];
        if ($isVoice) {
            $payload += $this->voiceCustomerFields($customerDetails);
        }

        $response = $this->postForm($isVoice ? self::VOICE_PURCHASE_ENDPOINT : self::PURCHASE_ENDPOINT, $payload);
        if (! $response['ok'] || ! is_array($response['json'])) {
            return ['ok' => false, 'package_id' => $packageTypeId, 'error' => $this->describeError($response, 'Unable to fulfill eSIM.')];
        }

        $result = $response['json'];
        $f = $this->deliveryFields($result);
        $common = [
            'package_id' => $packageTypeId,
            'package_type' => $type,
            'reference' => $reference,
            'esim_id' => $this->pick($result, ['data.esim_id', 'esim_id', 'data.sim_id', 'sim_id']),
            'order_id' => $this->pick($result, ['data.id', 'data.order_id', 'order_id', 'id']),
        ];

        if (! $this->isDelivered($f['iccid'], $this->pick($result, ['data.sim_applied', 'sim_applied']))) {
            return ['ok' => false, 'pending' => true] + $common + [
                'smdp_address' => $this->str($f['smdp_address']),
                'lpa' => $this->str($f['lpa']),
                'activation_code' => $this->str($f['activation_code']),
                'error' => 'Purchased, but eSIM details are not ready yet.',
                'raw' => $result,
            ];
        }

        return ['ok' => true] + $common + $this->deliveryResult($f) + ['raw' => $result];
    }

    /**
     * Add a bundle to an existing, renewable eSIM. The dealer API has used several names for the
     * top-up endpoint, so each is tried before falling back to a plain purchase carrying the eSIM ids.
     */
    public function topupEsim(string $targetEsimId, string $packageTypeId, string $reference, string $customerEmail, string $packageType = 'DATA-ONLY', array $customerDetails = []): array
    {
        $targetEsimId = trim($targetEsimId);
        if ($targetEsimId === '') {
            return ['ok' => false, 'error' => 'Missing target esim_id.'];
        }

        $type = $this->normalizePackageType($packageType) ?: 'DATA-ONLY';
        $isVoice = $type === 'DATA-VOICE-SMS';

        $details = $this->getEsimDetails($targetEsimId);
        if (($details['ok'] ?? false) !== true) {
            return ['ok' => false, 'pending' => true, 'error' => 'Unable to fetch target eSIM details yet.', 'esim_id' => $targetEsimId];
        }

        if (! ($this->toBool($details['can_renew'] ?? data_get($details, 'gloesim_item.can_renew')) ?? false)) {
            return ['ok' => false, 'error' => 'This eSIM cannot be renewed.', 'esim_id' => $targetEsimId];
        }

        $targetIccid = trim((string) ($details['iccid'] ?? ''));

        // Every id spelling the API has accepted, so whichever endpoint answers can match the eSIM.
        $payload = [
            'package_type_id' => $packageTypeId,
            'email' => $customerEmail,
            'esim_id' => $targetEsimId,
            'esimId' => $targetEsimId,
            'sim_id' => $targetEsimId,
            'simId' => $targetEsimId,
        ];
        if ($targetIccid !== '') {
            $payload['iccid'] = $targetIccid;
        }
        if ($isVoice) {
            $payload += $this->voiceCustomerFields($customerDetails);
        }

        $response = null;
        foreach (['/developer/dealer/package/topup', '/developer/dealer/package/renew', '/developer/dealer/package/extension'] as $endpoint) {
            $attempt = $this->postForm($endpoint, $payload);
            if ($attempt['ok'] && is_array($attempt['json'])) {
                $response = $attempt;
                break;
            }
        }

        if ($response === null) {
            $response = $this->postForm($isVoice ? self::VOICE_PURCHASE_ENDPOINT : self::PURCHASE_ENDPOINT, $payload);
            if (! $response['ok'] || ! is_array($response['json'])) {
                return ['ok' => false, 'error' => $this->describeError($response, 'Unable to top up eSIM.')];
            }
        }

        $result = $response['json'];
        $orderId = $this->pick($result, ['data.id', 'data.order_id', 'order_id', 'id']);
        $esimId = $this->pick($result, ['data.esim_id', 'esim_id', 'data.sim_id', 'sim_id']) ?? $targetEsimId;
        $iccid = $this->pick($result, ['data.iccid', 'iccid']) ?? $targetIccid;

        if (! $this->isDelivered($iccid, $this->pick($result, ['data.sim_applied', 'sim_applied']))) {
            return [
                'ok' => false,
                'pending' => true,
                'reference' => $reference,
                'esim_id' => $esimId,
                'order_id' => $orderId,
                'iccid' => $this->str($iccid),
                'error' => 'Purchased, but top-up details are not ready yet.',
                'raw' => $result,
            ];
        }

        // Guard against the fallback purchase having minted a brand-new eSIM instead of topping up.
        if (trim((string) $esimId) !== $targetEsimId && $targetIccid !== '' && trim((string) $iccid) !== $targetIccid) {
            return [
                'ok' => false,
                'error' => 'Top-up did not apply to the expected eSIM.',
                'order_id' => $orderId,
                'esim_id' => $esimId,
                'iccid' => $iccid,
                'target_esim_id' => $targetEsimId,
                'target_iccid' => $targetIccid,
            ];
        }

        return [
            'ok' => true,
            'reference' => $reference,
            'esim_id' => $targetEsimId,
            'order_id' => $orderId,
            'iccid' => $this->str($iccid),
            'raw' => $result,
        ];
    }

    /** Delivery details for one eSIM. `pending` until the provider has assigned an ICCID. */
    public function getEsimDetails(string $esimId): array
    {
        $id = trim($esimId);
        if ($id === '') {
            return ['ok' => false, 'error' => 'Missing esim_id.'];
        }

        $payload = $this->firstJson([
            ['/developer/dealer/my_esims', ['esim_id' => $id]],
            ['/developer/dealer/my-esims', ['esim_id' => $id]],
            ['/developer/dealer/esims/'.urlencode($id), []],
            ['/developer/dealer/esim/'.urlencode($id), []],
            ['/developer/dealer/my_esims/'.urlencode($id), []],
        ]);

        if ($payload === null) {
            return ['ok' => false, 'error' => 'Unable to fetch eSIM details yet.'];
        }

        $row = Arr::first(
            [data_get($payload, 'data.0'), data_get($payload, 'data.items.0'), data_get($payload, 'data.esim'), data_get($payload, 'data')],
            fn ($candidate) => is_array($candidate),
        );

        $item = is_array($row) ? [
            'id' => (string) (data_get($row, 'id') ?? ''),
            'iccid' => (string) (data_get($row, 'iccid') ?? ''),
            'number' => data_get($row, 'number'),
            'status' => (string) (data_get($row, 'status') ?? ''),
            'can_renew' => data_get($row, 'can_renew'),
            'created_at' => (string) (data_get($row, 'created_at') ?? ''),
            'last_bundle' => (string) (data_get($row, 'last_bundle') ?? ''),
            'matching_id' => (string) (data_get($row, 'matching_id') ?? ''),
            'sim_applied' => data_get($row, 'sim_applied'),
            'qr_code_text' => (string) (data_get($row, 'qr_code_text') ?? ''),
            'smdp_address' => (string) (data_get($row, 'smdp_address') ?? ''),
            'total_bundles' => data_get($row, 'total_bundles'),
            'universal_link' => (string) (data_get($row, 'universal_link') ?? ''),
            'android_universal_link' => (string) (data_get($row, 'android_universal_link') ?? ''),
        ] : null;

        $f = $this->deliveryFields($payload, details: true);
        $common = [
            'esim_id' => (string) ($this->pick($row, ['id', 'esim_id']) ?? $this->pick($payload, ['data.id', 'id']) ?? $id),
            'can_renew' => $item['can_renew'] ?? null,
        ];

        if (! is_string($f['iccid']) || trim($f['iccid']) === '') {
            return ['ok' => false, 'pending' => true] + $common + [
                'smdp_address' => $this->str($f['smdp_address']),
                'lpa' => $this->str($f['lpa']),
                'activation_code' => $this->str($f['activation_code']),
                'error' => 'eSIM exists but delivery details are not available yet.',
                'raw' => $payload,
                'gloesim_item' => $item,
            ];
        }

        return ['ok' => true] + $common + $this->deliveryResult($f) + ['raw' => $payload, 'gloesim_item' => $item];
    }

    public function listMyEsims(): array
    {
        return Cache::remember('gloesim.my_esims.v1', now()->addMinutes(5), function () {
            $payload = $this->dealerMyEsimsRaw();
            $rows = data_get($payload, 'data.items');
            $rows = is_array($rows) ? $rows : data_get($payload, 'data');

            return is_array($rows) ? array_values(array_filter($rows, fn ($v) => is_array($v) || is_object($v))) : [];
        });
    }

    public function dealerMyEsimsRaw(array $query = []): ?array
    {
        $query = array_filter($query, fn ($v) => $v !== null && $v !== '');

        return $this->firstJson(array_map(fn ($path) => [$path, $query], self::MY_ESIMS_ENDPOINTS), timeout: 6);
    }

    // ─── HTTP ───────────────────────────────────────────────────────────────────

    private function hasDealerCredentials(): bool
    {
        foreach (['base_url', 'dealer_email', 'dealer_password'] as $key) {
            $value = config("services.gloesim.{$key}");
            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    /** Live catalogue calls are skipped in tests and when the dealer account isn't configured. */
    private function liveCatalogueEnabled(): bool
    {
        return ! app()->environment('testing') && $this->hasDealerCredentials();
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.gloesim.base_url'), '/'))->acceptJson();
    }

    /** Send, and on 401 drop the cached dealer token and send once more with a fresh one. */
    private function withFreshTokenOn401(callable $send): Response
    {
        $response = $send();

        if ($response->status() === 401) {
            Cache::forget($this->dealerTokenCacheKey());
            $response = $send();
        }

        return $response;
    }

    /** GET returning decoded JSON, or null on any failure (network, non-2xx, non-JSON). */
    private function getJson(string $path, array $query = [], int $timeoutSeconds = 30): ?array
    {
        if (! $this->hasDealerCredentials()) {
            return null;
        }

        try {
            $response = $this->withFreshTokenOn401(fn () => $this->http()
                ->retry(1, 150, null, false)
                ->timeout($timeoutSeconds)
                ->withToken($this->dealerToken())
                ->get($path, $query));
        } catch (\Throwable) {
            return null;
        }

        $json = $response->successful() ? $response->json() : null;

        return is_array($json) ? $json : null;
    }

    /** First successful JSON body from a list of [path, query] fallbacks; stops at the first hit. */
    private function firstJson(array $candidates, int $timeout = 30): ?array
    {
        foreach ($candidates as [$path, $query]) {
            if (($json = $this->getJson($path, $query, $timeout)) !== null) {
                return $json;
            }
        }

        return null;
    }

    /**
     * Form POST for purchase endpoints. Returns {ok, status, json, error} rather than null so callers
     * can surface the provider's message. Transport exceptions propagate to the caller.
     */
    private function postForm(string $path, array $payload): array
    {
        if (! $this->hasDealerCredentials()) {
            return ['ok' => false, 'status' => null, 'json' => null, 'error' => 'Missing dealer credentials.'];
        }

        $response = $this->withFreshTokenOn401(fn () => $this->http()
            ->asForm()
            ->retry(1, 200, null, false)
            ->timeout(60)
            ->withToken($this->dealerToken())
            ->post($path, $payload));

        $json = is_array($response->json()) ? $response->json() : null;
        $message = (string) (data_get($json, 'message') ?? '');

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'json' => $json,
            'error' => $response->successful() ? null : trim($message !== '' ? $message : $response->reason()),
        ];
    }

    private function describeError(array $response, string $default): string
    {
        $message = (string) ($response['error'] ?? $default);
        $status = $response['status'] ?? null;

        return is_numeric($status) ? "HTTP {$status}: {$message}" : $message;
    }

    private function dealerToken(): ?string
    {
        return Cache::remember($this->dealerTokenCacheKey(), now()->addMinutes(55), fn () => $this->loginAndGetToken());
    }

    private function dealerTokenCacheKey(): string
    {
        return 'gloesim.dealer_token.v1.'.sha1((string) config('services.gloesim.base_url'));
    }

    private function loginAndGetToken(): ?string
    {
        $response = $this->http()
            ->retry(2, 250, null, false)
            ->timeout(30)
            ->post('/developer/dealer/login', [
                'email' => (string) config('services.gloesim.dealer_email'),
                'password' => (string) config('services.gloesim.dealer_password'),
            ]);

        $json = $response->successful() ? $response->json() : null;
        $token = is_array($json) ? $this->pick($json, ['token', 'access_token', 'data.token', 'data.access_token']) : null;

        return is_string($token) && trim($token) !== '' ? trim($token) : null;
    }

    // ─── Response parsing ───────────────────────────────────────────────────────

    /** The list inside a payload, wherever the endpoint chose to put it. */
    private function extractList(?array $payload): array
    {
        if ($payload === null) {
            return [];
        }

        foreach (['data', 'items', 'results', 'packages', 'countries'] as $key) {
            $candidate = data_get($payload, $key);
            if (is_array($candidate) && Arr::isList($candidate)) {
                return $candidate;
            }
        }

        return Arr::isList($payload) ? $payload : [];
    }

    /** First non-null value among the given dot-paths. */
    private function pick(mixed $source, array $paths): mixed
    {
        foreach ($paths as $path) {
            if (($value = data_get($source, $path)) !== null) {
                return $value;
            }
        }

        return null;
    }

    private function deliveryFields(array $payload, bool $details = false): array
    {
        $fields = [];
        foreach (self::DELIVERY_PATHS as $field => $paths) {
            $fields[$field] = $this->pick($payload, $details ? [...$paths, ...(self::DETAILS_EXTRA_PATHS[$field] ?? [])] : $paths);
        }

        return $fields;
    }

    /** Public shape of a delivered eSIM, shared by purchase and details responses. */
    private function deliveryResult(array $f): array
    {
        return [
            'iccid' => $f['iccid'],
            'activation_code' => $this->str($f['activation_code']),
            'lpa' => $this->str($f['lpa']),
            'qr_code_url' => $f['qr_code_url'],
            'smdp_address' => $this->str($f['smdp_address']),
            'puk_code' => $this->str($f['puk_code']),
            'number' => $this->str($f['number']),
            'esim_status' => $this->str($f['esim_status']),
            'direct_installation_link_ios' => $this->str($f['ios']),
            'direct_installation_link_android' => $this->str($f['android']),
        ];
    }

    private function isDelivered(mixed $iccid, mixed $simApplied): bool
    {
        return is_string($iccid) && trim($iccid) !== '' && $simApplied !== false;
    }

    /** Voice/SMS bundles require a subscriber profile; placeholders satisfy the API when we lack one. */
    private function voiceCustomerFields(array $d): array
    {
        $name = trim((string) ($d['name'] ?? ''));
        $parts = $name !== '' ? preg_split('/\s+/', $name) : [];

        return [
            'first_name' => (string) ($d['first_name'] ?? ($parts[0] ?? 'Customer')),
            'last_name' => (string) ($d['last_name'] ?? implode(' ', array_slice($parts, 1))),
            'street_name' => (string) ($d['street_name'] ?? 'Unknown'),
            'street_number' => (string) ($d['street_number'] ?? '0'),
            'street_direction' => (string) ($d['street_direction'] ?? ''),
            'city' => (string) ($d['city'] ?? 'Unknown'),
            'state' => (string) ($d['state'] ?? 'Unknown'),
            'zipcode' => (string) ($d['zipcode'] ?? '00000'),
            'contact_number' => (string) ($d['contact_number'] ?? '0000000000'),
            'imei' => (string) ($d['imei'] ?? '000000000000000'),
        ];
    }

    private function formatBundles(array $packages, string $currency): array
    {
        return array_map(function ($p) use ($currency) {
            $amount = $this->extractAmount($p);
            $canRenew = $this->toBool(data_get($p, 'can_renew') ?? data_get($p, 'canRenew'));

            $quantity = data_get($p, 'data_quantity');
            $unit = data_get($p, 'data_unit');
            $data = ($quantity !== null && $unit !== null)
                ? "{$quantity} {$unit}"
                : (data_get($p, 'data_amount_formatted') ?? data_get($p, 'data'));

            $vQuantity = data_get($p, 'package_validity');
            $vUnit = data_get($p, 'package_validity_unit');
            if ($vQuantity !== null && $vUnit !== null) {
                $days = (int) $vQuantity;
                $validity = strtolower((string) $vUnit) === 'day'
                    ? $this->validityLabel($days)
                    : "{$days} {$vUnit}".($days > 1 ? 's' : '');
            } else {
                $validity = data_get($p, 'validity_formatted') ?? data_get($p, 'validity');
                if (is_numeric($validity)) {
                    $validity = $this->validityLabel((int) $validity);
                }
            }

            return [
                'id' => data_get($p, 'id'),
                'name' => data_get($p, 'name'),
                'data' => $data,
                'validity' => $validity,
                'price' => $amount,
                'price_formatted' => $this->formatMoney($amount, $currency),
                'package_type' => data_get($p, 'package_type'),
                'can_renew' => $canRenew,
                'features' => [
                    'Hotspot' => data_get($p, 'hotspot') ? 'Supported' : 'Check coverage',
                    'Network' => data_get($p, 'connectivity') ?? '4G/5G',
                    'Activation' => 'Instant',
                    'Renewal' => $canRenew === true ? 'Yes' : 'No',
                ],
            ];
        }, $packages);
    }

    private function validityLabel(int $days): string
    {
        return match (true) {
            $days === 1 => '1 Day',
            $days === 7 => '1 Week',
            $days === 14 => '2 Weeks',
            $days === 30 => '30 Days',
            $days > 30 && $days % 30 === 0 => ($days / 30).' Months',
            default => "{$days} Days",
        };
    }

    // ─── Package helpers ────────────────────────────────────────────────────────

    /** All pages of global packages, optionally stopping early once `$deadline` (microtime) passes. */
    private function allGlobalPackages(?string $packageType, ?float $deadline = null): array
    {
        $perPage = 200;
        $all = [];

        for ($page = 1; $page <= 10; $page++) {
            if ($deadline !== null && microtime(true) > $deadline) {
                break;
            }

            $batch = $this->globalPackages($packageType, $page, $perPage);
            array_push($all, ...$batch);

            if (count($batch) < $perPage) {
                break;
            }
        }

        return $all;
    }

    /**
     * Keep packages matching the wanted type. Untyped packages are classified by whether they
     * include voice minutes or SMS.
     */
    private function filterByType(array $packages, string $wanted): array
    {
        return array_values(array_filter($packages, function ($p) use ($wanted) {
            if (! is_array($p)) {
                return false;
            }

            $type = $this->normalizePackageType(data_get($p, 'package_type'));
            if ($type !== '') {
                return $type === $wanted || str_starts_with($type, $wanted);
            }

            $hasVoiceOrSms = collect(['voice', 'sms'])->contains(fn ($k) => is_numeric($v = data_get($p, $k)) && (float) $v > 0);

            return match ($wanted) {
                'DATA-VOICE-SMS' => $hasVoiceOrSms,
                'DATA-ONLY' => ! $hasVoiceOrSms,
                default => false,
            };
        }));
    }

    /** "data only" / "Data_Only" → "DATA-ONLY". */
    private function normalizePackageType(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim(preg_replace('/[^A-Z0-9]+/', '-', strtoupper(trim($value))) ?? '', '-');
    }

    private function cheapest(array $packages): ?float
    {
        $min = null;
        foreach ($packages as $package) {
            $amount = is_array($package) ? $this->extractAmount($package) : null;
            if ($amount !== null && ($min === null || $amount < $min)) {
                $min = $amount;
            }
        }

        return $min;
    }

    private function extractAmount(array $item): ?float
    {
        $amount = $this->pick($item, ['starting_price', 'min_price', 'Price', 'price.amount', 'price', 'amount']);

        if (is_string($amount)) {
            $amount = str_replace([',', ' '], '', $amount);
        }

        return is_numeric($amount) ? (float) $amount : null;
    }

    // ─── Country helpers ────────────────────────────────────────────────────────

    /** @return array<string, array{name: ?string, code: ?string, image_url: ?string}> */
    private function countriesIndexById(): array
    {
        return Cache::remember('gloesim.countries_index.v1', now()->addHours(6), function () {
            $index = [];
            foreach ($this->countries() as $country) {
                $id = is_array($country) ? $this->pick($country, ['id', 'country_id', 'countryId']) : null;
                if (! is_scalar($id)) {
                    continue;
                }

                $index[(string) $id] = [
                    'name' => $this->trimmed($this->pick($country, ['name', 'country_name', 'countryName'])),
                    'code' => $this->extractCountryCode($country),
                    'image_url' => $this->trimmed(data_get($country, 'image_url')),
                ];
            }

            return $index;
        });
    }

    /** @return array<string, array{id: string, name: string, image_url: ?string}> keyed by normalizeCountryName() */
    private function countriesIndexByNormalizedName(): array
    {
        return Cache::remember('gloesim.countries_index_by_name.v1', now()->addHours(6), function () {
            $index = [];
            foreach ($this->countries() as $country) {
                if (! is_array($country)) {
                    continue;
                }

                $id = $this->pick($country, ['id', 'country_id', 'countryId']);
                $name = $this->trimmed($this->pick($country, ['name', 'country_name', 'countryName']));
                if (! is_scalar($id) || $name === null) {
                    continue;
                }

                $index[$this->normalizeCountryName($name)] = [
                    'id' => trim((string) $id),
                    'name' => $name,
                    'image_url' => $this->trimmed(data_get($country, 'image_url')),
                ];
            }

            return $index;
        });
    }

    private function extractCountryCode(array $item): ?string
    {
        $code = $this->pick($item, ['country_code', 'countryCode', 'iso2', 'country.iso2', 'country.code']);
        $code = is_string($code) ? strtoupper(trim($code)) : '';

        return preg_match('/^[A-Z]{2}\z/', $code) ? $code : null;
    }

    private function normalizeCountryName(string $name): string
    {
        $name = preg_replace('/\s+/', ' ', trim(mb_strtolower($name))) ?? $name;

        return trim(preg_replace('/[^a-z0-9 ]/u', '', $name) ?? $name);
    }

    private function slugify(string $value): string
    {
        $value = trim(preg_replace('/[^a-z0-9]+/u', '-', trim(mb_strtolower($value))) ?? $value, '-');

        return $value === '' ? 'region' : $value;
    }

    /** ISO-3166 alpha-2 → regional-indicator flag emoji; globe for anything else. */
    private function flagEmoji(?string $countryCode): string
    {
        $code = strtoupper((string) $countryCode);

        if (! preg_match('/^[A-Z]{2}\z/', $code)) {
            return '🌐';
        }

        return mb_chr(0x1F1E6 + ord($code[0]) - 65).mb_chr(0x1F1E6 + ord($code[1]) - 65);
    }

    // ─── Presentation ───────────────────────────────────────────────────────────

    private function currency(): string
    {
        return (string) config('services.gloesim.currency', 'USD');
    }

    private function formatMoney(?float $amount, ?string $currency): ?string
    {
        if ($amount === null) {
            return null;
        }

        $currency = $currency ? strtoupper($currency) : null;
        $formatted = number_format($amount, 2, '.', '');
        $symbol = match ($currency) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => null,
        };

        return match (true) {
            $symbol !== null => $symbol.$formatted,
            $currency !== null => $currency.' '.$formatted,
            default => '$'.$formatted,
        };
    }

    private function badgeForRank(int $rank): string
    {
        return ['Top pick', 'Popular', 'Trending', 'Value'][$rank] ?? 'Hot';
    }

    private function noteForRank(int $rank): string
    {
        return ['Best for travel & business', 'Great coverage in cities', 'Fast setup for trips', 'Reliable data options'][$rank] ?? 'Flexible plans available';
    }

    private function badgeForRegion(string $name): string
    {
        return [
            'global' => 'World wide',
            'north america' => 'High speed',
            'europe' => 'Best value',
            'asia' => 'Top coverage',
        ][strtolower($name)] ?? 'Popular';
    }

    private function noteForRegion(string $name): string
    {
        return [
            'global' => 'Best for international travel',
            'north america' => 'Great for USA & Canada',
            'europe' => 'Seamless EU roaming',
            'asia' => 'Top networks in Asia',
        ][strtolower($name)] ?? 'Regional data coverage';
    }

    /** Static tiles shown when the API is unreachable, so the landing page never renders empty. */
    private function fallbackPopularCountries(int $limit): array
    {
        $tiles = [
            ['US', 'USA', 'Best for travel & business', 'Top pick', '$5.99'],
            ['TR', 'Turkey', 'Great coverage in cities', 'Popular', '$4.49'],
            ['SA', 'Saudi Arabia', 'Fast setup for trips', 'Trending', '$6.29'],
            ['EG', 'Egypt', 'Reliable data options', 'Value', '$3.99'],
            ['FR', 'France', 'EU-ready packages', 'Best seller', '$5.19'],
            ['AE', 'United Arab Emirates', 'Premium network options', 'High speed', '$6.99'],
        ];

        return array_map(fn ($t) => [
            'code' => $t[0],
            'name' => $t[1],
            'note' => $t[2],
            'badge' => $t[3],
            'starting_price_formatted' => $t[4],
            'flag' => $this->flagEmoji($t[0]),
            'starting_price' => null,
        ], array_slice($tiles, 0, $limit));
    }

    private function fallbackPopularRegions(int $limit): array
    {
        $names = ['Global', 'North America', 'Asia', 'Europe', 'Latin America', 'Middle East', 'Oceania'];

        return array_map(fn ($name) => [
            'id' => $name === 'Global' ? 'global' : null,
            'name' => $name,
            'slug' => $this->slugify($name),
            'note' => $this->noteForRegion($name),
            'badge' => $this->badgeForRegion($name),
            'starting_price' => null,
            'starting_price_formatted' => null,
            'flag' => '🌐',
            'flag_url' => self::UN_FLAG,
        ], array_slice($names, 0, $limit));
    }

    // ─── Small utilities ────────────────────────────────────────────────────────

    /** Cache::remember that refuses to cache an empty result, so a provider blip isn't pinned. */
    private function rememberNonEmpty(string $key, int $minutes, callable $compute): array
    {
        $cached = Cache::get($key);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $value = $compute();
        if ($value !== []) {
            Cache::put($key, $value, now()->addMinutes($minutes));
        }

        return $value;
    }

    /** Loose boolean from the API (true/1/"yes"); null when absent or unrecognised. */
    private function toBool(mixed $raw): ?bool
    {
        return match (true) {
            is_bool($raw) => $raw,
            is_numeric($raw) => (int) $raw === 1,
            is_string($raw) && trim($raw) !== '' => in_array(mb_strtolower(trim($raw)), ['1', 'true', 'yes'], true),
            default => null,
        };
    }

    private function str(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function trimmed(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
