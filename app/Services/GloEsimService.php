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

        return $this->extractList($this->getJson('/developer/dealer/packages/country/'.urlencode((string) $countryId), $query));
    }

    public function packagesByContinentId(int|string $continentId, ?string $packageType = null): array
    {
        $query = $packageType ? ['package_type' => $packageType] : [];

        return $this->extractList($this->getJson('/developer/dealer/packages/continent/'.urlencode((string) $continentId), $query));
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

        return $this->extractList($this->getJson('/developer/dealer/packages/global', $query));
    }

    public function allCountriesWithPrices(string $packageType = 'DATA-ONLY'): array
    {
        return Cache::remember('gloesim.all_countries_prices.v1.' . $packageType, now()->addHours(6), function () use ($packageType) {
            $countries = $this->countries();
            $globalPackages = $this->globalPackages($packageType, 1, 200);

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

            $currency = (string) config('services.gloesim.currency', 'USD');
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
            $results = array_filter($results, fn($r) => $r['starting_price'] !== null);

            usort($results, fn ($a, $b) => strcmp($a['name'], $b['name']));

            return array_values($results);
        });
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
        return Cache::remember("gloesim.asset_details.{$type}.{$id}.{$packageType}.v1", now()->addMinutes(15), function () use ($type, $id, $packageType) {
            $currency = (string) config('services.gloesim.currency', 'USD');
            
            if ($type === 'country') {
                $countries = $this->countriesIndexById();
                $country = $countries[$id] ?? null;
                if (!$country) return null;

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
                $continent = collect($continents)->first(fn($c) => (string)data_get($c, 'id') === $id);
                if (!$continent) return null;

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
                    if ($days === 1) $validity = "1 Day";
                    elseif ($days === 7) $validity = "1 Week";
                    elseif ($days === 14) $validity = "2 Weeks";
                    elseif ($days === 30) $validity = "30 Days";
                    elseif ($days >= 30 && $days % 30 === 0) $validity = ($days / 30) . " Months";
                    else $validity = "{$days} Days";
                } else {
                    $validity = "{$days} {$unit}" . ($days > 1 ? 's' : '');
                }
            } else {
                $validity = data_get($p, 'validity_formatted') ?? data_get($p, 'validity');
                // If validity is just a number, append 'Days'
                if (is_numeric($validity)) {
                    $days = (int) $validity;
                    if ($days === 1) $validity = "1 Day";
                    elseif ($days === 7) $validity = "1 Week";
                    elseif ($days === 14) $validity = "2 Weeks";
                    elseif ($days === 30) $validity = "30 Days";
                    elseif ($days >= 30 && $days % 30 === 0) $validity = ($days / 30) . " Months";
                    else $validity = $days . " Days";
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
                ]
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
                        'type' => 'country'
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
                        'type' => 'region'
                    ];
                }
            }
            
            // Add "Global" manually as it's a special region
            $results[] = [
                'id' => 'global',
                'name' => 'Global',
                'type' => 'region'
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

    private function getJson(string $path, array $query = []): ?array
    {
        if (! $this->hasDealerCredentials()) {
            return null;
        }

        $baseUrl = rtrim((string) config('services.gloesim.base_url'), '/');

        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->retry(2, 250)
            ->timeout(30)
            ->withToken($this->dealerToken())
            ->get($path, $query);

        if ($response->status() === 401) {
            Cache::forget($this->dealerTokenCacheKey());

            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->retry(2, 250)
                ->timeout(30)
                ->withToken($this->dealerToken())
                ->get($path, $query);
        }

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
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
            ->retry(2, 250)
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
}
