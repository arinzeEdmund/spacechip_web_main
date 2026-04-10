<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GloEsimService
{
    public function popularCountries(int $limit = 6): array
    {
        $limit = max(1, min(24, $limit));

        $cacheKey = "gloesim.popular_countries.v3.{$limit}";

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $countries = $this->popularCountriesFromApi($limit);

        if ($countries !== []) {
            Cache::put($cacheKey, $countries, now()->addMinutes(15));

            return $countries;
        }

        return $this->fallbackPopularCountries($limit);
    }

    public function popularRegions(int $limit = 7): array
    {
        $limit = max(1, min(24, $limit));

        $cacheKey = "gloesim.popular_regions.v1.{$limit}";

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $regions = $this->popularRegionsFromApi($limit);

        if ($regions !== []) {
            Cache::put($cacheKey, $regions, now()->addMinutes(30));

            return $regions;
        }

        return $this->fallbackPopularRegions($limit);
    }

    private function popularCountriesFromApi(int $limit): array
    {
        try {
            if (app()->environment('testing')) {
                return [];
            }

            if (! $this->hasDealerCredentials()) {
                return [];
            }

            $countriesByName = $this->countriesIndexByNormalizedName();
            $countriesById = $this->countriesIndexById();

            $preferred = [
                'United States',
                'United Kingdom',
                'United Arab Emirates',
                'France',
                'Germany',
                'Turkey',
                'Saudi Arabia',
                'Egypt',
                'Nigeria',
                'India',
                'Canada',
                'Australia',
                'Japan',
                'Thailand',
                'South Africa',
                'Brazil',
                'Mexico',
                'Italy',
                'Spain',
            ];

            $selected = [];
            foreach ($preferred as $name) {
                $key = $this->normalizeCountryName($name);
                $node = $countriesByName[$key] ?? null;

                if (! is_array($node)) {
                    continue;
                }

                $selected[] = $node;

                if (count($selected) >= $limit) {
                    break;
                }
            }

            if ($selected === []) {
                return [];
            }

            $ranked = [];
            foreach ($selected as $i => $country) {
                $countryId = $country['id'] ?? null;
                $countryName = is_string($country['name'] ?? null) ? $country['name'] : null;
                $flagUrl = is_string($country['image_url'] ?? null) ? $country['image_url'] : null;

                $minAmount = null;
                if ($countryId !== null && $countryId !== '') {
                    $packages = $this->packagesByCountryId($countryId, 'DATA-ONLY');

                    foreach ($packages as $package) {
                        if (! is_array($package)) {
                            continue;
                        }

                        $amount = $this->extractAmount($package);

                        if ($amount === null) {
                            continue;
                        }

                        if ($minAmount === null || $amount < $minAmount) {
                            $minAmount = $amount;
                        }
                    }
                }

                $currency = (string) config('services.gloesim.currency', 'USD');

                $ranked[] = [
                    'id' => $countryId,
                    'code' => $countriesById[$countryId]['code'] ?? null,
                    'name' => $countryName,
                    'flag' => $this->flagEmoji($countriesById[$countryId]['code'] ?? null),
                    'flag_url' => $flagUrl,
                    'note' => $this->noteForRank($i),
                    'badge' => $this->badgeForRank($i),
                    'starting_price' => $minAmount,
                    'starting_price_formatted' => $this->formatMoney($minAmount, $currency),
                ];
            }

            return $ranked;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function popularRegionsFromApi(int $limit): array
    {
        try {
            if (app()->environment('testing')) {
                return [];
            }

            if (! $this->hasDealerCredentials()) {
                return [];
            }

            $continents = $this->continents();

            $byName = [];
            $ordered = [];
            foreach ($continents as $continent) {
                if (! is_array($continent)) {
                    continue;
                }

                $id = data_get($continent, 'id');
                $name = data_get($continent, 'name');

                if (! is_scalar($id) || ! is_string($name) || trim($name) === '') {
                    continue;
                }

                $node = [
                    'id' => trim((string) $id),
                    'name' => trim($name),
                ];

                $key = $this->normalizeCountryName($node['name']);
                $byName[$key] = $node;
                $ordered[] = ['key' => $key] + $node;
            }

            $currency = (string) config('services.gloesim.currency', 'USD');

            $ranked = [];
            $globalMinAmount = null;

            $globalPackages = $this->globalPackages('DATA-ONLY', 1, 200);
            foreach ($globalPackages as $package) {
                if (! is_array($package)) {
                    continue;
                }

                $amount = $this->extractAmount($package);

                if ($amount === null) {
                    continue;
                }

                if ($globalMinAmount === null || $amount < $globalMinAmount) {
                    $globalMinAmount = $amount;
                }
            }

            $ranked[] = [
                'id' => 'global',
                'name' => 'Global',
                'slug' => 'global',
                'note' => 'Best for international travel',
                'badge' => 'World wide',
                'starting_price' => $globalMinAmount,
                'starting_price_formatted' => $this->formatMoney($globalMinAmount, $currency),
                'flag' => '🌐',
                'flag_url' => 'https://flagcdn.com/w80/un.png', // UN flag or globe placeholder
            ];

            $selected = [];
            $selectedKeys = [];

            $preferredGroups = [
                ['label' => 'North America', 'aliases' => ['North America', 'N. America']],
                ['label' => 'Asia', 'aliases' => ['Asia']],
                ['label' => 'Europe', 'aliases' => ['Europe']],
                ['label' => 'Latin America', 'aliases' => ['Latin America', 'Latin America & Caribbean', 'Latin America and Caribbean', 'South America', 'Central America']],
                ['label' => 'Middle East', 'aliases' => ['Middle East', 'Middle East & Africa', 'Middle East and Africa', 'MENA']],
                ['label' => 'Oceania', 'aliases' => ['Oceania', 'Australia & Oceania', 'Australia and Oceania']],
            ];

            foreach ($preferredGroups as $group) {
                $aliases = $group['aliases'] ?? [];

                foreach ($aliases as $alias) {
                    $node = $byName[$this->normalizeCountryName($alias)] ?? null;

                    if (! is_array($node)) {
                        continue;
                    }

                    $key = $this->normalizeCountryName($node['name']);

                    if (isset($selectedKeys[$key])) {
                        continue;
                    }

                    $selected[] = ['key' => $key, 'label' => $group['label'] ?? null] + $node;
                    $selectedKeys[$key] = true;
                    break;
                }

                if (count($selected) >= max(0, $limit - count($ranked))) {
                    break;
                }
            }

            if (count($selected) < max(0, $limit - count($ranked))) {
                foreach ($ordered as $node) {
                    $key = (string) ($node['key'] ?? '');

                    if ($key === '' || isset($selectedKeys[$key])) {
                        continue;
                    }

                    $selected[] = $node;
                    $selectedKeys[$key] = true;

                    if (count($selected) >= max(0, $limit - count($ranked))) {
                        break;
                    }
                }
            }

            foreach ($selected as $node) {
                $continentId = $node['id'] ?? null;
                $continentName = (string) $node['name'];
                $displayName = is_string($node['label'] ?? null) ? (string) $node['label'] : $continentName;

                $packages = ($continentId !== null && $continentId !== '') ? $this->packagesByContinentId($continentId, 'DATA-ONLY') : [];

                $minAmount = null;
                foreach ($packages as $package) {
                    if (! is_array($package)) {
                        continue;
                    }

                    $amount = $this->extractAmount($package);

                    if ($amount === null) {
                        continue;
                    }

                    if ($minAmount === null || $amount < $minAmount) {
                        $minAmount = $amount;
                    }
                }

                $ranked[] = [
                    'id' => $continentId,
                    'name' => $displayName,
                    'slug' => $this->slugify($displayName),
                    'note' => $this->noteForRegion($displayName),
                    'badge' => $this->badgeForRegion($displayName),
                    'starting_price' => $minAmount,
                    'starting_price_formatted' => $this->formatMoney($minAmount, $currency),
                    'flag' => '🌐',
                    'flag_url' => 'https://flagcdn.com/w80/un.png',
                ];
            }

            return array_slice($ranked, 0, $limit);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function balance(): ?array
    {
        return $this->getJson('/developer/dealer/balance');
    }

    public function packages(string $packageType = 'DATA-ONLY'): array
    {
        return $this->extractList($this->getJson('/developer/dealer/packages', [
            'package_type' => $packageType,
        ]));
    }

    public function countries(): array
    {
        return $this->extractList($this->getJson('/developer/dealer/packages/country'));
    }

    public function continents(): array
    {
        return $this->extractList($this->getJson('/developer/dealer/packages/continent'));
    }

    public function packagesByCountryId(int|string $countryId, ?string $packageType = null): array
    {
        $query = $packageType ? ['package_type' => $packageType] : [];

        $packages = $this->extractList($this->getJson('/developer/dealer/packages/country/'.urlencode((string) $countryId), $query));

        if (! $packageType) {
            return $packages;
        }

        $wanted = $this->normalizePackageType($packageType);

        $filtered = array_values(array_filter($packages, function ($p) use ($wanted) {
            if (! is_array($p)) {
                return false;
            }

            $rawType = data_get($p, 'package_type');
            $normalized = $this->normalizePackageType(is_string($rawType) ? $rawType : null);

            if ($normalized !== '') {
                return $normalized === $wanted || str_starts_with($normalized, $wanted);
            }

            $voice = data_get($p, 'voice');
            $sms = data_get($p, 'sms');
            $hasVoice = is_numeric($voice) && (float) $voice > 0;
            $hasSms = is_numeric($sms) && (float) $sms > 0;

            if ($wanted === 'DATA-VOICE-SMS') {
                return $hasVoice || $hasSms;
            }

            if ($wanted === 'DATA-ONLY') {
                return ! $hasVoice && ! $hasSms;
            }

            return false;
        }));

        if ($filtered !== []) {
            return $filtered;
        }

        if ($wanted !== 'DATA-VOICE-SMS') {
            return $filtered;
        }

        $perPage = 200;
        $global = [];
        for ($page = 1; $page <= 10; $page++) {
            $pagePackages = $this->globalPackages($packageType, $page, $perPage);
            if ($pagePackages === []) {
                break;
            }

            foreach ($pagePackages as $p) {
                if (! is_array($p)) {
                    continue;
                }

                $countries = data_get($p, 'countries', []);
                if (! is_array($countries)) {
                    continue;
                }

                $matched = false;
                foreach ($countries as $c) {
                    $id = (string) data_get($c, 'id');
                    if ($id !== '' && $id === (string) $countryId) {
                        $global[] = $p;
                        $matched = true;
                        break;
                    }
                }

                if ($matched) {
                    continue;
                }
            }

            if (count($pagePackages) < $perPage) {
                break;
            }
        }

        return $global;
    }

    public function packagesByContinentId(int|string $continentId, ?string $packageType = null): array
    {
        $query = $packageType ? ['package_type' => $packageType] : [];

        $packages = $this->extractList($this->getJson('/developer/dealer/packages/continent/'.urlencode((string) $continentId), $query));

        if (! $packageType) {
            return $packages;
        }

        $wanted = $this->normalizePackageType($packageType);

        return array_values(array_filter($packages, function ($p) use ($wanted) {
            if (! is_array($p)) {
                return false;
            }

            $rawType = data_get($p, 'package_type');
            $normalized = $this->normalizePackageType(is_string($rawType) ? $rawType : null);

            if ($normalized !== '') {
                return $normalized === $wanted || str_starts_with($normalized, $wanted);
            }

            $voice = data_get($p, 'voice');
            $sms = data_get($p, 'sms');
            $hasVoice = is_numeric($voice) && (float) $voice > 0;
            $hasSms = is_numeric($sms) && (float) $sms > 0;

            if ($wanted === 'DATA-VOICE-SMS') {
                return $hasVoice || $hasSms;
            }

            if ($wanted === 'DATA-ONLY') {
                return ! $hasVoice && ! $hasSms;
            }

            return false;
        }));
    }

    public function globalPackages(?string $packageType = 'DATA-ONLY', int $page = 1, int $perPage = 100): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));

        $query = array_filter([
            'package_type' => $packageType ?: null,
            'page' => $page,
            'per_page' => $perPage,
        ]);

        $packages = $this->extractList($this->getJson('/developer/dealer/packages/global', $query, 6));

        if (! $packageType) {
            return $packages;
        }

        $wanted = $this->normalizePackageType($packageType);

        return array_values(array_filter($packages, function ($p) use ($wanted) {
            if (! is_array($p)) {
                return false;
            }

            $rawType = data_get($p, 'package_type');
            $normalized = $this->normalizePackageType(is_string($rawType) ? $rawType : null);

            return $normalized === '' ? true : ($normalized === $wanted || str_starts_with($normalized, $wanted));
        }));
    }

    private function normalizePackageType(?string $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value;
    }

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

        $currency = (string) config('services.gloesim.currency', 'USD');
        $fallback = [];
        foreach ($countries as $country) {
            $id = (string) (data_get($country, 'id') ?? '');
            if ($id === '') {
                continue;
            }
            $code = $this->extractCountryCode($country);
            $fallback[] = [
                'id' => $id,
                'name' => (string) data_get($country, 'name'),
                'code' => $code,
                'flag' => $this->flagEmoji($code),
                'flag_url' => data_get($country, 'image_url'),
                'starting_price' => null,
                'starting_price_formatted' => null,
            ];
        }
        usort($fallback, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $perPage = 200;
        $globalPackages = [];
        $deadline = microtime(true) + 6.0;

        for ($page = 1; $page <= 10; $page++) {
            if (microtime(true) > $deadline) {
                break;
            }

            $pagePackages = $this->globalPackages($packageType, $page, $perPage);

            if ($pagePackages === []) {
                break;
            }

            $globalPackages = array_merge($globalPackages, $pagePackages);

            if (count($pagePackages) < $perPage) {
                break;
            }
        }

        if ($globalPackages === []) {
            return $fallback;
        }

            $pricesByCountryId = [];
            foreach ($globalPackages as $package) {
                $packageCountries = data_get($package, 'countries', []);
                $amount = $this->extractAmount($package);
                if ($amount === null) {
                    continue;
                }

                foreach ($packageCountries as $c) {
                    $id = (string) data_get($c, 'id');
                    if (! isset($pricesByCountryId[$id]) || $amount < $pricesByCountryId[$id]) {
                        $pricesByCountryId[$id] = $amount;
                    }
                }
            }

            $results = [];

            foreach ($countries as $country) {
                $id = (string) (data_get($country, 'id') ?? '');
                if ($id === '') {
                    continue;
                }

                $name = (string) data_get($country, 'name');
                $code = $this->extractCountryCode($country);

                $results[] = [
                    'id' => $id,
                    'name' => $name,
                    'code' => $code,
                    'flag' => $this->flagEmoji($code),
                    'flag_url' => data_get($country, 'image_url'),
                    'starting_price' => $pricesByCountryId[$id] ?? null,
                    'starting_price_formatted' => isset($pricesByCountryId[$id]) ? $this->formatMoney($pricesByCountryId[$id], $currency) : null,
                ];
            }

            // Filter out countries with no plans for this type
            $results = array_filter($results, fn ($r) => $r['starting_price'] !== null);

            usort($results, fn ($a, $b) => strcmp($a['name'], $b['name']));

            $final = array_values($results);
            if ($final !== []) {
                Cache::put($cacheKey, $final, now()->addHours(6));
                return $final;
            }

            return $fallback;
    }

    public function allRegionsWithPrices(): array
    {
        return $this->popularRegions(20);
    }

    public function allVirtualNumbers(): array
    {
        return Cache::remember('gloesim.all_virtual_numbers.v1', now()->addHours(6), function () {
            $packages = $this->packages('VIRTUAL-PHONE-NUMBER');
            if (empty($packages)) {
                return [];
            }

            $currency = (string) config('services.gloesim.currency', 'USD');
            $results = [];

            foreach ($packages as $package) {
                $amount = $this->extractAmount($package);
                $name = data_get($package, 'name');
                $countries = data_get($package, 'countries', []);
                $firstCountry = count($countries) > 0 ? reset($countries) : null;
                $code = $firstCountry ? $this->extractCountryCode($firstCountry) : null;

                $results[] = [
                    'id' => data_get($package, 'id'),
                    'name' => $name,
                    'description' => data_get($package, 'description'),
                    'price' => $amount,
                    'price_formatted' => $this->formatMoney($amount, $currency),
                    'flag' => $this->flagEmoji($code),
                    'flag_url' => data_get($firstCountry, 'image_url'),
                ];
            }

            return $results;
        });
    }

    public function getAssetDetails(string $type, string $id, ?string $packageType = 'DATA-ONLY'): ?array
    {
        return Cache::remember("gloesim.asset_details.{$type}.{$id}.{$packageType}.v3", now()->addMinutes(15), function () use ($type, $id, $packageType) {
            $currency = (string) config('services.gloesim.currency', 'USD');

            if ($type === 'country') {
                $countries = $this->countriesIndexById();
                $country = $countries[$id] ?? null;
                if (! $country) {
                    return null;
                }

                $packages = $this->packagesByCountryId($id, $packageType);

                return [
                    'name' => $country['name'],
                    'code' => $country['code'],
                    'flag' => $this->flagEmoji($country['code']),
                    'flag_url' => $country['image_url'] ?? null,
                    'type' => 'Country eSIM',
                    'bundles' => $this->formatBundles($packages, $currency),
                ];
            }

            if ($type === 'region') {
                // Special case for Global
                if ($id === 'global') {
                    $packages = $this->globalPackages($packageType, 1, 100);

                    return [
                        'name' => 'Global',
                        'code' => 'UN',
                        'flag' => '🌐',
                        'flag_url' => 'https://flagcdn.com/w80/un.png',
                        'type' => 'Regional Plan',
                        'bundles' => $this->formatBundles($packages, $currency),
                    ];
                }

                // Continent/Region
                $continents = $this->continents();
                $continent = collect($continents)->first(fn ($c) => (string) data_get($c, 'id') === $id);
                if (! $continent) {
                    return null;
                }

                $packages = $this->packagesByContinentId($id, $packageType);

                return [
                    'name' => data_get($continent, 'name'),
                    'code' => null,
                    'flag' => '🌐',
                    'flag_url' => 'https://flagcdn.com/w80/un.png',
                    'type' => 'Regional Plan',
                    'bundles' => $this->formatBundles($packages, $currency),
                ];
            }

            return null;
        });
    }

    private function formatBundles(array $packages, string $currency): array
    {
        return array_map(function ($p) use ($currency) {
            $amount = $this->extractAmount($p);

            // Get data amount from API response
            $dataQuantity = data_get($p, 'data_quantity');
            $dataUnit = data_get($p, 'data_unit');
            $dataAmount = ($dataQuantity !== null && $dataUnit !== null) ? "{$dataQuantity} {$dataUnit}" : (data_get($p, 'data_amount_formatted') ?? data_get($p, 'data'));

            // Get validity from API response
            $vQuantity = data_get($p, 'package_validity');
            $vUnit = data_get($p, 'package_validity_unit');

            if ($vQuantity !== null && $vUnit !== null) {
                $days = (int) $vQuantity;
                $unit = (string) $vUnit;

                if (strtolower($unit) === 'day') {
                    if ($days === 1) {
                        $validity = '1 Day';
                    } elseif ($days === 7) {
                        $validity = '1 Week';
                    } elseif ($days === 14) {
                        $validity = '2 Weeks';
                    } elseif ($days === 30) {
                        $validity = '30 Days';
                    } elseif ($days >= 30 && $days % 30 === 0) {
                        $validity = ($days / 30).' Months';
                    } else {
                        $validity = "{$days} Days";
                    }
                } else {
                    $validity = "{$days} {$unit}".($days > 1 ? 's' : '');
                }
            } else {
                $validity = data_get($p, 'validity_formatted') ?? data_get($p, 'validity');
                // If validity is just a number, append 'Days'
                if (is_numeric($validity)) {
                    $days = (int) $validity;
                    if ($days === 1) {
                        $validity = '1 Day';
                    } elseif ($days === 7) {
                        $validity = '1 Week';
                    } elseif ($days === 14) {
                        $validity = '2 Weeks';
                    } elseif ($days === 30) {
                        $validity = '30 Days';
                    } elseif ($days >= 30 && $days % 30 === 0) {
                        $validity = ($days / 30).' Months';
                    } else {
                        $validity = $days.' Days';
                    }
                }
            }

            return [
                'id' => data_get($p, 'id'),
                'name' => data_get($p, 'name'),
                'data' => $dataAmount,
                'validity' => $validity,
                'price' => $amount,
                'price_formatted' => $this->formatMoney($amount, $currency),
                'package_type' => data_get($p, 'package_type'),
                'features' => [
                    'Hotspot' => data_get($p, 'hotspot') ? 'Supported' : 'Check coverage',
                    'Network' => data_get($p, 'connectivity') ?? '4G/5G',
                    'Activation' => 'Instant',
                ],
            ];
        }, $packages);
    }

    public function searchableAssets(): array
    {
        return Cache::remember('gloesim.searchable_assets.v1', now()->addHours(6), function () {
            $countries = $this->countries();
            $continents = $this->continents();

            $results = [];

            foreach ($countries as $country) {
                $id = data_get($country, 'id') ?? data_get($country, 'country_id') ?? data_get($country, 'countryId');
                $name = data_get($country, 'name') ?? data_get($country, 'country_name') ?? data_get($country, 'countryName');
                if ($id && $name) {
                    $results[] = [
                        'id' => (string) $id,
                        'name' => (string) $name,
                        'type' => 'country',
                    ];
                }
            }

            foreach ($continents as $continent) {
                $id = data_get($continent, 'id');
                $name = data_get($continent, 'name');
                if ($id && $name) {
                    $results[] = [
                        'id' => (string) $id,
                        'name' => (string) $name,
                        'type' => 'region',
                    ];
                }
            }

            // Add "Global" manually as it's a special region
            $results[] = [
                'id' => 'global',
                'name' => 'Global',
                'type' => 'region',
            ];

            return $results;
        });
    }

    private function hasDealerCredentials(): bool
    {
        $baseUrl = config('services.gloesim.base_url');
        $email = config('services.gloesim.dealer_email');
        $password = config('services.gloesim.dealer_password');

        return is_string($baseUrl) && trim($baseUrl) !== ''
            && is_string($email) && trim($email) !== ''
            && is_string($password) && trim($password) !== '';
    }

    private function getJson(string $path, array $query = [], int $timeoutSeconds = 30): ?array
    {
        if (! $this->hasDealerCredentials()) {
            return null;
        }

        $baseUrl = rtrim((string) config('services.gloesim.base_url'), '/');

        $doGet = function () use ($baseUrl, $path, $query, $timeoutSeconds) {
            return Http::baseUrl($baseUrl)
                ->acceptJson()
                ->retry(1, 150, null, false)
                ->timeout($timeoutSeconds)
                ->withToken($this->dealerToken())
                ->get($path, $query);
        };

        try {
            $response = $doGet();
        } catch (\Throwable) {
            return null;
        }

        if ($response->status() === 401) {
            Cache::forget($this->dealerTokenCacheKey());

            try {
                $response = $doGet();
            } catch (\Throwable) {
                return null;
            }
        }

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    private function postJson(string $path, array $payload = []): ?array
    {
        if (! $this->hasDealerCredentials()) {
            return null;
        }

        $baseUrl = rtrim((string) config('services.gloesim.base_url'), '/');

        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->retry(2, 250, null, false)
            ->timeout(45)
            ->withToken($this->dealerToken())
            ->post($path, $payload);

        if ($response->status() === 401) {
            Cache::forget($this->dealerTokenCacheKey());

            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->retry(2, 250, null, false)
                ->timeout(45)
                ->withToken($this->dealerToken())
                ->post($path, $payload);
        }

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    private function postFormDetailed(string $path, array $payload = [], array $query = []): array
    {
        if (! $this->hasDealerCredentials()) {
            return [
                'ok' => false,
                'status' => null,
                'json' => null,
                'error' => 'Missing dealer credentials.',
            ];
        }

        $baseUrl = rtrim((string) config('services.gloesim.base_url'), '/');
        $urlPath = $path;
        if ($query !== []) {
            $urlPath .= '?'.http_build_query($query);
        }

        $doPost = function () use ($baseUrl, $urlPath, $payload) {
            return Http::baseUrl($baseUrl)
                ->acceptJson()
                ->asForm()
                ->retry(1, 200, null, false)
                ->timeout(60)
                ->withToken($this->dealerToken())
                ->post($urlPath, $payload);
        };

        $response = $doPost();
        if ($response->status() === 401) {
            Cache::forget($this->dealerTokenCacheKey());
            $response = $doPost();
        }

        $json = $response->json();
        $json = is_array($json) ? $json : null;

        if (! $response->successful()) {
            $message = is_array($json) ? (string) (data_get($json, 'message') ?? '') : '';

            return [
                'ok' => false,
                'status' => $response->status(),
                'json' => $json,
                'error' => trim(($message !== '' ? $message : $response->reason())),
            ];
        }

        return [
            'ok' => true,
            'status' => $response->status(),
            'json' => $json,
            'error' => null,
        ];
    }

    private function postJsonDetailed(string $path, array $payload = []): array
    {
        if (! $this->hasDealerCredentials()) {
            return [
                'ok' => false,
                'status' => null,
                'json' => null,
                'error' => 'Missing dealer credentials.',
            ];
        }

        $baseUrl = rtrim((string) config('services.gloesim.base_url'), '/');

        $doPost = function () use ($baseUrl, $path, $payload) {
            return Http::baseUrl($baseUrl)
                ->acceptJson()
                ->retry(1, 200, null, false)
                ->timeout(60)
                ->withToken($this->dealerToken())
                ->post($path, $payload);
        };

        $response = $doPost();
        if ($response->status() === 401) {
            Cache::forget($this->dealerTokenCacheKey());
            $response = $doPost();
        }

        $json = $response->json();
        $json = is_array($json) ? $json : null;

        if (! $response->successful()) {
            $message = is_array($json) ? (string) (data_get($json, 'message') ?? '') : '';

            return [
                'ok' => false,
                'status' => $response->status(),
                'json' => $json,
                'error' => trim(($message !== '' ? $message : $response->reason())),
            ];
        }

        return [
            'ok' => true,
            'status' => $response->status(),
            'json' => $json,
            'error' => null,
        ];
    }

    public function fulfillEsim(string $packageTypeId, string $reference, string $customerEmail, string $packageType = 'DATA-ONLY', array $customerDetails = []): array
    {
        $normalizedType = $this->normalizePackageType($packageType);
        $normalizedType = $normalizedType === '' ? 'DATA-ONLY' : $normalizedType;

        $endpoint = $normalizedType === 'DATA-VOICE-SMS'
            ? '/developer/dealer/package/data_voice_sms/purchase'
            : '/developer/dealer/package/purchase';

        $payload = [
            'package_type_id' => $packageTypeId,
            'email' => $customerEmail,
        ];

        if ($normalizedType === 'DATA-VOICE-SMS') {
            $name = trim((string) ($customerDetails['name'] ?? ''));
            $parts = $name !== '' ? preg_split('/\s+/', $name) : [];
            $firstName = (string) ($customerDetails['first_name'] ?? ($parts[0] ?? 'Customer'));
            $lastName = (string) ($customerDetails['last_name'] ?? (count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : ''));

            $payload = array_merge($payload, [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'street_name' => (string) ($customerDetails['street_name'] ?? 'Unknown'),
                'street_number' => (string) ($customerDetails['street_number'] ?? '0'),
                'street_direction' => (string) ($customerDetails['street_direction'] ?? ''),
                'city' => (string) ($customerDetails['city'] ?? 'Unknown'),
                'state' => (string) ($customerDetails['state'] ?? 'Unknown'),
                'zipcode' => (string) ($customerDetails['zipcode'] ?? '00000'),
                'contact_number' => (string) ($customerDetails['contact_number'] ?? '0000000000'),
                'imei' => (string) ($customerDetails['imei'] ?? '000000000000000'),
            ]);
        }

        $resultWrapper = $this->postFormDetailed($endpoint, $payload);
        if (($resultWrapper['ok'] ?? false) !== true || ! is_array($resultWrapper['json'] ?? null)) {
            $msg = (string) ($resultWrapper['error'] ?? 'Unable to fulfill eSIM.');
            $status = $resultWrapper['status'] ?? null;

            return [
                'ok' => false,
                'package_id' => $packageTypeId,
                'error' => is_numeric($status) ? "HTTP {$status}: {$msg}" : $msg,
            ];
        }

        $result = $resultWrapper['json'];

        $orderId = data_get($result, 'data.id')
            ?? data_get($result, 'data.order_id')
            ?? data_get($result, 'order_id')
            ?? data_get($result, 'id');

        $esimId = data_get($result, 'data.esim_id')
            ?? data_get($result, 'esim_id')
            ?? data_get($result, 'data.sim_id')
            ?? data_get($result, 'sim_id');

        $simApplied = data_get($result, 'data.sim_applied')
            ?? data_get($result, 'sim_applied');

        $iccid = data_get($result, 'data.iccid')
            ?? data_get($result, 'iccid')
            ?? data_get($result, 'data.items.0.iccid')
            ?? data_get($result, 'data.esim.iccid');

        $activationCode = data_get($result, 'data.activation_code')
            ?? data_get($result, 'activation_code')
            ?? data_get($result, 'data.items.0.activation_code')
            ?? data_get($result, 'data.esim.activation_code')
            ?? data_get($result, 'data.esim.activationCode');

        $lpa = data_get($result, 'data.lpa')
            ?? data_get($result, 'lpa')
            ?? data_get($result, 'data.lpa_code')
            ?? data_get($result, 'lpa_code')
            ?? data_get($result, 'data.lpa_string')
            ?? data_get($result, 'lpa_string')
            ?? data_get($result, 'data.qr_payload')
            ?? data_get($result, 'qr_payload');

        $qrCodeUrl = data_get($result, 'data.qr_code_url')
            ?? data_get($result, 'qr_code_url')
            ?? data_get($result, 'data.items.0.qr_code_url')
            ?? data_get($result, 'data.esim.qr_code_url')
            ?? data_get($result, 'data.esim.qrCodeUrl')
            ?? data_get($result, 'data.qr_code');

        $pukCode = data_get($result, 'data.puk_code')
            ?? data_get($result, 'puk_code')
            ?? data_get($result, 'data.puk')
            ?? data_get($result, 'puk');

        $number = data_get($result, 'data.number')
            ?? data_get($result, 'number')
            ?? data_get($result, 'data.msisdn')
            ?? data_get($result, 'msisdn');

        $esimStatus = data_get($result, 'data.status')
            ?? data_get($result, 'status');

        $installIos = data_get($result, 'data.direct_installation_link_ios')
            ?? data_get($result, 'direct_installation_link_ios')
            ?? data_get($result, 'data.installation_link_ios')
            ?? data_get($result, 'installation_link_ios')
            ?? data_get($result, 'data.ios_installation_link')
            ?? data_get($result, 'ios_installation_link')
            ?? data_get($result, 'data.ios_install_link')
            ?? data_get($result, 'ios_install_link');

        $installAndroid = data_get($result, 'data.direct_installation_link_android')
            ?? data_get($result, 'direct_installation_link_android')
            ?? data_get($result, 'data.installation_link_android')
            ?? data_get($result, 'installation_link_android')
            ?? data_get($result, 'data.android_installation_link')
            ?? data_get($result, 'android_installation_link')
            ?? data_get($result, 'data.android_install_link')
            ?? data_get($result, 'android_install_link');

        $smdpAddress = data_get($result, 'data.smdp_address')
            ?? data_get($result, 'smdp_address')
            ?? data_get($result, 'data.smdpAddress')
            ?? data_get($result, 'smdpAddress')
            ?? data_get($result, 'data.smdp_plus_address')
            ?? data_get($result, 'smdp_plus_address')
            ?? data_get($result, 'data.smdpPlusAddress')
            ?? data_get($result, 'smdpPlusAddress')
            ?? data_get($result, 'data.smdp')
            ?? data_get($result, 'smdp');

        $hasDelivery = is_string($iccid) && trim($iccid) !== '';
        $simAppliedBool = is_bool($simApplied) ? $simApplied : null;

        if (! $hasDelivery || $simAppliedBool === false) {
            return [
                'ok' => false,
                'pending' => true,
                'package_id' => $packageTypeId,
                'package_type' => $normalizedType,
                'reference' => $reference,
                'esim_id' => $esimId,
                'order_id' => $orderId,
                'smdp_address' => is_string($smdpAddress) ? $smdpAddress : null,
                'lpa' => is_string($lpa) ? $lpa : null,
                'activation_code' => is_string($activationCode) ? $activationCode : null,
                'error' => 'Purchased, but eSIM details are not ready yet.',
                'raw' => $result,
            ];
        }

        return [
            'ok' => true,
            'package_id' => $packageTypeId,
            'package_type' => $normalizedType,
            'reference' => $reference,
            'esim_id' => $esimId,
            'order_id' => $orderId,
            'iccid' => $iccid,
            'activation_code' => is_string($activationCode) ? $activationCode : null,
            'lpa' => is_string($lpa) ? $lpa : null,
            'qr_code_url' => $qrCodeUrl,
            'smdp_address' => is_string($smdpAddress) ? $smdpAddress : null,
            'puk_code' => is_string($pukCode) ? $pukCode : null,
            'number' => is_string($number) ? $number : null,
            'esim_status' => is_string($esimStatus) ? $esimStatus : null,
            'direct_installation_link_ios' => is_string($installIos) ? $installIos : null,
            'direct_installation_link_android' => is_string($installAndroid) ? $installAndroid : null,
            'raw' => $result,
        ];
    }

    public function getEsimDetails(string $esimId): array
    {
        $id = trim($esimId);
        if ($id === '') {
            return ['ok' => false, 'error' => 'Missing esim_id.'];
        }

        $candidates = [
            [$this->getJson('/developer/dealer/my_esims', ['esim_id' => $id]), 'my_esims?esim_id'],
            [$this->getJson('/developer/dealer/my-esims', ['esim_id' => $id]), 'my-esims?esim_id'],
            [$this->getJson('/developer/dealer/esims/'.urlencode($id)), 'esims/{id}'],
            [$this->getJson('/developer/dealer/esim/'.urlencode($id)), 'esim/{id}'],
            [$this->getJson('/developer/dealer/my_esims/'.urlencode($id)), 'my_esims/{id}'],
        ];

        $payload = null;
        foreach ($candidates as [$resp]) {
            if (is_array($resp)) {
                $payload = $resp;
                break;
            }
        }

        if (! is_array($payload)) {
            return ['ok' => false, 'error' => 'Unable to fetch eSIM details yet.'];
        }

        $row = null;
        $rowCandidates = [
            data_get($payload, 'data.0'),
            data_get($payload, 'data.items.0'),
            data_get($payload, 'data.esim'),
            data_get($payload, 'data'),
        ];
        foreach ($rowCandidates as $candidate) {
            if (is_array($candidate)) {
                $row = $candidate;
                break;
            }
        }

        $resolvedEsimId = data_get($row, 'id')
            ?? data_get($row, 'esim_id')
            ?? data_get($payload, 'data.id')
            ?? data_get($payload, 'id')
            ?? $id;

        $iccid = data_get($payload, 'data.iccid')
            ?? data_get($payload, 'iccid')
            ?? data_get($payload, 'data.items.0.iccid')
            ?? data_get($payload, 'data.esim.iccid')
            ?? data_get($payload, 'data.0.iccid');

        $activationCode = data_get($payload, 'data.activation_code')
            ?? data_get($payload, 'activation_code')
            ?? data_get($payload, 'data.items.0.activation_code')
            ?? data_get($payload, 'data.esim.activation_code')
            ?? data_get($payload, 'data.esim.activationCode')
            ?? data_get($payload, 'data.0.activation_code');

        $lpa = data_get($payload, 'data.lpa')
            ?? data_get($payload, 'lpa')
            ?? data_get($payload, 'data.lpa_code')
            ?? data_get($payload, 'lpa_code')
            ?? data_get($payload, 'data.lpa_string')
            ?? data_get($payload, 'lpa_string')
            ?? data_get($payload, 'data.qr_payload')
            ?? data_get($payload, 'qr_payload')
            ?? data_get($payload, 'data.qr_code_text')
            ?? data_get($payload, 'qr_code_text')
            ?? data_get($payload, 'data.0.qr_code_text')
            ?? data_get($payload, 'data.items.0.qr_code_text');

        $qrCodeUrl = data_get($payload, 'data.qr_code_url')
            ?? data_get($payload, 'qr_code_url')
            ?? data_get($payload, 'data.items.0.qr_code_url')
            ?? data_get($payload, 'data.esim.qr_code_url')
            ?? data_get($payload, 'data.esim.qrCodeUrl')
            ?? data_get($payload, 'data.qr_code')
            ?? data_get($payload, 'data.0.qr_code_url');

        $pukCode = data_get($payload, 'data.puk_code')
            ?? data_get($payload, 'puk_code')
            ?? data_get($payload, 'data.puk')
            ?? data_get($payload, 'puk');

        $number = data_get($payload, 'data.number')
            ?? data_get($payload, 'number')
            ?? data_get($payload, 'data.msisdn')
            ?? data_get($payload, 'msisdn')
            ?? data_get($payload, 'data.0.number')
            ?? data_get($payload, 'data.0.msisdn');

        $esimStatus = data_get($payload, 'data.status')
            ?? data_get($payload, 'status')
            ?? data_get($payload, 'data.0.status')
            ?? data_get($payload, 'data.items.0.status');

        $installIos = data_get($payload, 'data.direct_installation_link_ios')
            ?? data_get($payload, 'direct_installation_link_ios')
            ?? data_get($payload, 'data.installation_link_ios')
            ?? data_get($payload, 'installation_link_ios')
            ?? data_get($payload, 'data.ios_installation_link')
            ?? data_get($payload, 'ios_installation_link')
            ?? data_get($payload, 'data.ios_install_link')
            ?? data_get($payload, 'ios_install_link')
            ?? data_get($payload, 'data.universal_link')
            ?? data_get($payload, 'universal_link')
            ?? data_get($payload, 'data.0.universal_link')
            ?? data_get($payload, 'data.items.0.universal_link');

        $installAndroid = data_get($payload, 'data.direct_installation_link_android')
            ?? data_get($payload, 'direct_installation_link_android')
            ?? data_get($payload, 'data.installation_link_android')
            ?? data_get($payload, 'installation_link_android')
            ?? data_get($payload, 'data.android_installation_link')
            ?? data_get($payload, 'android_installation_link')
            ?? data_get($payload, 'data.android_install_link')
            ?? data_get($payload, 'android_install_link')
            ?? data_get($payload, 'data.android_universal_link')
            ?? data_get($payload, 'android_universal_link')
            ?? data_get($payload, 'data.0.android_universal_link')
            ?? data_get($payload, 'data.items.0.android_universal_link');

        $smdpAddress = data_get($payload, 'data.smdp_address')
            ?? data_get($payload, 'smdp_address')
            ?? data_get($payload, 'data.smdpAddress')
            ?? data_get($payload, 'smdpAddress')
            ?? data_get($payload, 'data.smdp_plus_address')
            ?? data_get($payload, 'smdp_plus_address')
            ?? data_get($payload, 'data.smdpPlusAddress')
            ?? data_get($payload, 'smdpPlusAddress')
            ?? data_get($payload, 'data.smdp')
            ?? data_get($payload, 'smdp')
            ?? data_get($payload, 'data.0.smdp_address')
            ?? data_get($payload, 'data.items.0.smdp_address');

        $gloesimItem = null;
        if (is_array($row)) {
            $gloesimItem = [
                'id' => (string) (data_get($row, 'id') ?? ''),
                'iccid' => (string) (data_get($row, 'iccid') ?? ''),
                'number' => data_get($row, 'number'),
                'status' => (string) (data_get($row, 'status') ?? ''),
                'can_renew' => data_get($row, 'can_renew'),
                'created_at' => (string) (data_get($row, 'created_at') ?? ''),
                'last_bundle' => (string) (data_get($row, 'last_bundle') ?? ''),
                'matching_id' => (string) (data_get($row, 'matching_id') ?? ''),
                'sim_applied' => data_get($row, 'sim_applied'),
                'qr_code_text' => (string) (data_get($row, 'qr_code_text') ?? data_get($row, 'qr_code_text') ?? ''),
                'smdp_address' => (string) (data_get($row, 'smdp_address') ?? ''),
                'total_bundles' => data_get($row, 'total_bundles'),
                'universal_link' => (string) (data_get($row, 'universal_link') ?? ''),
                'android_universal_link' => (string) (data_get($row, 'android_universal_link') ?? ''),
            ];
        }

        if (! is_string($iccid) || trim($iccid) === '') {
            return [
                'ok' => false,
                'pending' => true,
                'esim_id' => (string) $resolvedEsimId,
                'smdp_address' => is_string($smdpAddress) ? $smdpAddress : null,
                'lpa' => is_string($lpa) ? $lpa : null,
                'activation_code' => is_string($activationCode) ? $activationCode : null,
                'error' => 'eSIM exists but delivery details are not available yet.',
                'raw' => $payload,
                'gloesim_item' => $gloesimItem,
            ];
        }

        return [
            'ok' => true,
            'esim_id' => (string) $resolvedEsimId,
            'iccid' => $iccid,
            'activation_code' => is_string($activationCode) ? $activationCode : null,
            'lpa' => is_string($lpa) ? $lpa : null,
            'qr_code_url' => $qrCodeUrl,
            'smdp_address' => is_string($smdpAddress) ? $smdpAddress : null,
            'puk_code' => is_string($pukCode) ? $pukCode : null,
            'number' => is_string($number) ? $number : null,
            'esim_status' => is_string($esimStatus) ? $esimStatus : null,
            'direct_installation_link_ios' => is_string($installIos) ? $installIos : null,
            'direct_installation_link_android' => is_string($installAndroid) ? $installAndroid : null,
            'raw' => $payload,
            'gloesim_item' => $gloesimItem,
        ];
    }

    private function dealerToken(): ?string
    {
        return Cache::remember($this->dealerTokenCacheKey(), now()->addMinutes(55), function () {
            return $this->loginAndGetToken();
        });
    }

    private function dealerTokenCacheKey(): string
    {
        $baseUrl = (string) config('services.gloesim.base_url');

        return 'gloesim.dealer_token.v1.'.sha1($baseUrl);
    }

    private function loginAndGetToken(): ?string
    {
        $baseUrl = rtrim((string) config('services.gloesim.base_url'), '/');

        $email = (string) config('services.gloesim.dealer_email');
        $password = (string) config('services.gloesim.dealer_password');

        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->retry(2, 250, null, false)
            ->timeout(30)
            ->post('/developer/dealer/login', [
                'email' => $email,
                'password' => $password,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();

        if (! is_array($json)) {
            return null;
        }

        $token = data_get($json, 'token')
            ?? data_get($json, 'access_token')
            ?? data_get($json, 'data.token')
            ?? data_get($json, 'data.access_token');

        return is_string($token) && trim($token) !== '' ? trim($token) : null;
    }

    private function extractList(?array $payload): array
    {
        if ($payload === null) {
            return [];
        }

        $candidates = [
            data_get($payload, 'data'),
            data_get($payload, 'items'),
            data_get($payload, 'results'),
            data_get($payload, 'packages'),
            data_get($payload, 'countries'),
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate) && Arr::isList($candidate)) {
                return $candidate;
            }
        }

        return Arr::isList($payload) ? $payload : [];
    }

    private function countriesIndexById(): array
    {
        return Cache::remember('gloesim.countries_index.v1', now()->addHours(6), function () {
            $countries = $this->countries();

            $index = [];
            foreach ($countries as $country) {
                if (! is_array($country)) {
                    continue;
                }

                $id = data_get($country, 'id') ?? data_get($country, 'country_id') ?? data_get($country, 'countryId');

                if (! is_scalar($id)) {
                    continue;
                }

                $name = data_get($country, 'name') ?? data_get($country, 'country_name') ?? data_get($country, 'countryName');
                $code = $this->extractCountryCode($country);
                $imageUrl = data_get($country, 'image_url');

                $index[(string) $id] = [
                    'name' => is_string($name) && trim($name) !== '' ? trim($name) : null,
                    'code' => $code,
                    'image_url' => is_string($imageUrl) && trim($imageUrl) !== '' ? trim($imageUrl) : null,
                ];
            }

            return $index;
        });
    }

    private function countriesIndexByNormalizedName(): array
    {
        return Cache::remember('gloesim.countries_index_by_name.v1', now()->addHours(6), function () {
            $countries = $this->countries();

            $index = [];
            foreach ($countries as $country) {
                if (! is_array($country)) {
                    continue;
                }

                $id = data_get($country, 'id') ?? data_get($country, 'country_id') ?? data_get($country, 'countryId');
                $name = data_get($country, 'name') ?? data_get($country, 'country_name') ?? data_get($country, 'countryName');

                if (! is_scalar($id) || ! is_string($name) || trim($name) === '') {
                    continue;
                }

                $imageUrl = data_get($country, 'image_url');

                $index[$this->normalizeCountryName($name)] = [
                    'id' => trim((string) $id),
                    'name' => trim($name),
                    'image_url' => is_string($imageUrl) && trim($imageUrl) !== '' ? trim($imageUrl) : null,
                ];
            }

            return $index;
        });
    }

    private function normalizeCountryName(string $name): string
    {
        $name = trim(mb_strtolower($name));
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        $name = preg_replace('/[^a-z0-9 ]/u', '', $name) ?? $name;

        return trim($name);
    }

    private function slugify(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? $value;
        $value = trim($value, '-');

        return $value === '' ? 'region' : $value;
    }

    private function fallbackPopularRegions(int $limit): array
    {
        $fallback = [
            ['name' => 'Global'],
            ['name' => 'North America'],
            ['name' => 'Asia'],
            ['name' => 'Europe'],
            ['name' => 'Latin America'],
            ['name' => 'Middle East'],
            ['name' => 'Oceania'],
        ];

        $fallback = array_map(function (array $r) {
            $name = $r['name'];

            return [
                'id' => $name === 'Global' ? 'global' : null,
                'name' => $name,
                'slug' => $this->slugify($name),
                'note' => $this->noteForRegion($name),
                'badge' => $this->badgeForRegion($name),
                'starting_price' => null,
                'starting_price_formatted' => null,
                'flag' => '🌐',
                'flag_url' => 'https://flagcdn.com/w80/un.png',
            ];
        }, $fallback);

        return array_slice($fallback, 0, $limit);
    }

    private function extractCountriesFromPackage(array $package): array
    {
        $countries = data_get($package, 'countries');

        if (! is_array($countries)) {
            return [];
        }

        return Arr::isList($countries) ? $countries : array_values($countries);
    }

    private function aggregatePopularCountriesFromPackages(array $packages, array $countriesIndexById, int $limit): array
    {
        $aggregate = [];

        foreach ($packages as $package) {
            if (! is_array($package)) {
                continue;
            }

            $amount = $this->extractAmount($package);
            $currency = $this->extractCurrency($package) ?: (string) config('services.gloesim.currency', 'USD');

            $embeddedCountries = $this->extractCountriesFromPackage($package);
            $countryNodes = $embeddedCountries !== [] ? $embeddedCountries : [$package];

            foreach ($countryNodes as $countryNode) {
                if (! is_array($countryNode)) {
                    continue;
                }

                $countryId = $embeddedCountries !== []
                    ? (is_scalar(data_get($countryNode, 'id')) ? trim((string) data_get($countryNode, 'id')) : null)
                    : $this->extractCountryId($countryNode);

                $countryName = $embeddedCountries !== []
                    ? (is_string(data_get($countryNode, 'name')) ? trim((string) data_get($countryNode, 'name')) : null)
                    : $this->extractCountryName($countryNode);

                $countryCode = $this->extractCountryCode($countryNode);

                if ($countryId !== null && isset($countriesIndexById[$countryId])) {
                    $countryName = $countryName ?: ($countriesIndexById[$countryId]['name'] ?? null);
                    $countryCode = $countryCode ?: ($countriesIndexById[$countryId]['code'] ?? null);
                }

                if ($countryId === null && $countryName === null) {
                    continue;
                }

                $key = $countryId ?: ($countryCode ?: $countryName);

                if (! isset($aggregate[$key])) {
                    $aggregate[$key] = [
                        'id' => $countryId,
                        'code' => $countryCode,
                        'name' => $countryName,
                        'count' => 0,
                        'min_amount' => null,
                        'currency' => $currency,
                    ];
                }

                $aggregate[$key]['count']++;

                if ($amount !== null) {
                    if ($aggregate[$key]['min_amount'] === null || $amount < $aggregate[$key]['min_amount']) {
                        $aggregate[$key]['min_amount'] = $amount;
                        $aggregate[$key]['currency'] = $currency;
                    }
                }
            }
        }

        $list = array_values($aggregate);

        usort($list, function (array $a, array $b) {
            $countCmp = ($b['count'] ?? 0) <=> ($a['count'] ?? 0);

            if ($countCmp !== 0) {
                return $countCmp;
            }

            $aPrice = $a['min_amount'] ?? INF;
            $bPrice = $b['min_amount'] ?? INF;

            return $aPrice <=> $bPrice;
        });

        $list = array_slice($list, 0, $limit);

        $ranked = [];
        foreach ($list as $i => $country) {
            $code = $country['code'] ? strtoupper((string) $country['code']) : null;
            $name = $country['name'] ? (string) $country['name'] : ($code ?: 'Worldwide');

            $ranked[] = [
                'code' => $code,
                'name' => $name,
                'flag' => $this->flagEmoji($code),
                'note' => $this->noteForRank($i),
                'badge' => $this->badgeForRank($i),
                'starting_price' => $country['min_amount'],
                'starting_price_formatted' => $this->formatMoney($country['min_amount'], $country['currency']),
            ];
        }

        return $ranked;
    }

    private function extractCountryId(array $item): ?string
    {
        $id = data_get($item, 'country_id')
            ?? data_get($item, 'countryId')
            ?? data_get($item, 'country.id')
            ?? data_get($item, 'country.country_id');

        if (! is_scalar($id)) {
            return null;
        }

        $id = trim((string) $id);

        return $id === '' ? null : $id;
    }

    private function extractCountryCode(array $item): ?string
    {
        $code = data_get($item, 'country_code')
            ?? data_get($item, 'countryCode')
            ?? data_get($item, 'iso2')
            ?? data_get($item, 'country.iso2')
            ?? data_get($item, 'country.code');

        if (! is_string($code)) {
            return null;
        }

        $code = strtoupper(trim($code));

        if (strlen($code) !== 2 || preg_match('/[^A-Z]/', $code)) {
            return null;
        }

        return $code;
    }

    private function extractCountryName(array $item): ?string
    {
        $name = data_get($item, 'country_name')
            ?? data_get($item, 'countryName')
            ?? data_get($item, 'CountryName')
            ?? data_get($item, 'country.name')
            ?? data_get($item, 'country.Name');

        if (! is_string($name)) {
            return null;
        }

        $name = trim($name);

        return $name === '' ? null : $name;
    }

    private function extractAmount(array $item): ?float
    {
        $amount = data_get($item, 'starting_price')
            ?? data_get($item, 'min_price')
            ?? data_get($item, 'Price')
            ?? data_get($item, 'price.amount')
            ?? data_get($item, 'price')
            ?? data_get($item, 'amount');

        if (is_string($amount)) {
            $amount = str_replace([',', ' '], '', $amount);
        }

        return is_numeric($amount) ? (float) $amount : null;
    }

    private function extractCurrency(array $item): ?string
    {
        $currency = data_get($item, 'currency')
            ?? data_get($item, 'Currency')
            ?? data_get($item, 'price.currency')
            ?? data_get($item, 'priceCurrency');

        if (! is_string($currency)) {
            return null;
        }

        $currency = strtoupper(trim($currency));

        return $currency === '' ? null : $currency;
    }

    private function formatMoney(?float $amount, ?string $currency): ?string
    {
        if ($amount === null) {
            return null;
        }

        $currency = $currency ? strtoupper($currency) : null;

        $symbol = match ($currency) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => null,
        };

        $formatted = number_format($amount, 2, '.', '');

        if ($symbol) {
            return $symbol.$formatted;
        }

        return $currency ? $currency.' '.$formatted : '$'.$formatted;
    }

    private function flagEmoji(?string $countryCode): string
    {
        if (! is_string($countryCode) || strlen($countryCode) !== 2) {
            return '🌐';
        }

        $countryCode = strtoupper($countryCode);

        if (preg_match('/[^A-Z]/', $countryCode)) {
            return '🌐';
        }

        $points = [
            0x1F1E6 + (ord($countryCode[0]) - 65),
            0x1F1E6 + (ord($countryCode[1]) - 65),
        ];

        return mb_convert_encoding('&#'.$points[0].';', 'UTF-8', 'HTML-ENTITIES')
            .mb_convert_encoding('&#'.$points[1].';', 'UTF-8', 'HTML-ENTITIES');
    }

    private function badgeForRank(int $rank): string
    {
        return match ($rank) {
            0 => 'Top pick',
            1 => 'Popular',
            2 => 'Trending',
            3 => 'Value',
            default => 'Hot',
        };
    }

    private function badgeForRegion(string $name): string
    {
        return match (strtolower($name)) {
            'global' => 'World wide',
            'north america' => 'High speed',
            'europe' => 'Best value',
            'asia' => 'Top coverage',
            default => 'Popular',
        };
    }

    private function noteForRank(int $rank): string
    {
        return match ($rank) {
            0 => 'Best for travel & business',
            1 => 'Great coverage in cities',
            2 => 'Fast setup for trips',
            3 => 'Reliable data options',
            default => 'Flexible plans available',
        };
    }

    private function noteForRegion(string $name): string
    {
        return match (strtolower($name)) {
            'global' => 'Best for international travel',
            'north america' => 'Great for USA & Canada',
            'europe' => 'Seamless EU roaming',
            'asia' => 'Top networks in Asia',
            default => 'Regional data coverage',
        };
    }

    private function fallbackPopularCountries(int $limit): array
    {
        $fallback = [
            [
                'code' => 'US',
                'name' => 'USA',
                'note' => 'Best for travel & business',
                'badge' => 'Top pick',
                'starting_price_formatted' => '$5.99',
            ],
            [
                'code' => 'TR',
                'name' => 'Turkey',
                'note' => 'Great coverage in cities',
                'badge' => 'Popular',
                'starting_price_formatted' => '$4.49',
            ],
            [
                'code' => 'SA',
                'name' => 'Saudi Arabia',
                'note' => 'Fast setup for trips',
                'badge' => 'Trending',
                'starting_price_formatted' => '$6.29',
            ],
            [
                'code' => 'EG',
                'name' => 'Egypt',
                'note' => 'Reliable data options',
                'badge' => 'Value',
                'starting_price_formatted' => '$3.99',
            ],
            [
                'code' => 'FR',
                'name' => 'France',
                'note' => 'EU-ready packages',
                'badge' => 'Best seller',
                'starting_price_formatted' => '$5.19',
            ],
            [
                'code' => 'AE',
                'name' => 'United Arab Emirates',
                'note' => 'Premium network options',
                'badge' => 'High speed',
                'starting_price_formatted' => '$6.99',
            ],
        ];

        $fallback = array_map(function (array $c) {
            return [
                ...$c,
                'flag' => $this->flagEmoji($c['code']),
                'starting_price' => null,
            ];
        }, $fallback);

        return array_slice($fallback, 0, $limit);
    }

    public function listMyEsims(): array
    {
        $cacheKey = 'gloesim.my_esims.v1';

        return Cache::remember($cacheKey, now()->addMinutes(5), function () {
            $candidates = [
                $this->getJson('/developer/dealer/my_esims', [], 6),
                $this->getJson('/developer/dealer/my-esims', [], 6),
                $this->getJson('/developer/dealer/my_esims/list', [], 6),
            ];

            $payload = null;
            foreach ($candidates as $resp) {
                if (is_array($resp)) {
                    $payload = $resp;
                    break;
                }
            }

            if (! is_array($payload)) {
                return [];
            }

            $items = data_get($payload, 'data.items');
            if (is_array($items)) {
                return array_values(array_filter($items, fn ($v) => is_array($v) || is_object($v)));
            }

            $data = data_get($payload, 'data');
            if (is_array($data)) {
                return array_values(array_filter($data, fn ($v) => is_array($v) || is_object($v)));
            }

            return [];
        });
    }

    public function dealerMyEsimsRaw(array $query = []): ?array
    {
        $query = array_filter($query, fn ($v) => $v !== null && $v !== '');

        $candidates = [
            $this->getJson('/developer/dealer/my_esims', $query, 6),
            $this->getJson('/developer/dealer/my-esims', $query, 6),
            $this->getJson('/developer/dealer/my_esims/list', $query, 6),
        ];

        foreach ($candidates as $resp) {
            if (is_array($resp)) {
                return $resp;
            }
        }

        return null;
    }
}
