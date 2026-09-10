<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * One-time SMS / OTP activations via SMSPool (https://api.smspool.net).
 *
 * Public method names and return shapes mirror the previous SMSPVA service so
 * the /api/social-numbers/* route closures and payload builders are unchanged.
 */
class SmsPoolService
{
    public function isConfigured(): bool
    {
        return trim((string) config('services.smspool.api_key')) !== '';
    }

    /**
     * The social apps we sell numbers for, mapped to their SMSPool service name.
     * IDs are resolved dynamically at runtime from /service/retrieve_all.
     */
    public function apps(): array
    {
        return [
            'whatsapp'   => ['key' => 'whatsapp', 'name' => 'WhatsApp', 'service' => 'WhatsApp', 'icon' => '💬', 'desc' => 'SMS verification numbers for WhatsApp.'],
            'telegram'   => ['key' => 'telegram', 'name' => 'Telegram', 'service' => 'Telegram', 'icon' => '✈️', 'desc' => 'Verification numbers for Telegram.'],
            'facebook'   => ['key' => 'facebook', 'name' => 'Facebook', 'service' => 'Facebook / Meta Viewpoints', 'icon' => '👤', 'desc' => 'Verification numbers for Facebook.'],
            'instagram'  => ['key' => 'instagram', 'name' => 'Instagram', 'service' => 'Instagram / Threads', 'icon' => '📸', 'desc' => 'Numbers for Instagram & Threads.'],
            'tiktok'     => ['key' => 'tiktok', 'name' => 'TikTok', 'service' => 'TikTok/Douyin', 'icon' => '🎵', 'desc' => 'Numbers for TikTok sign-up.'],
            'twitter'    => ['key' => 'twitter', 'name' => 'X (Twitter)', 'service' => 'Twitter / X', 'icon' => '✖️', 'desc' => 'Verification numbers for X.'],
            'google'     => ['key' => 'google', 'name' => 'Google / Gmail', 'service' => 'Google/Gmail', 'icon' => '🔷', 'desc' => 'Numbers for Google & Gmail.'],
            'snapchat'   => ['key' => 'snapchat', 'name' => 'Snapchat', 'service' => 'Snapchat', 'icon' => '👻', 'desc' => 'Numbers for Snapchat sign-up.'],
            'discord'    => ['key' => 'discord', 'name' => 'Discord', 'service' => 'Discord', 'icon' => '🎮', 'desc' => 'Numbers for Discord verification.'],
            'linkedin'   => ['key' => 'linkedin', 'name' => 'LinkedIn', 'service' => 'LinkedIn', 'icon' => '💼', 'desc' => 'Numbers for LinkedIn verification.'],
            'wechat'     => ['key' => 'wechat', 'name' => 'WeChat', 'service' => 'WeChat', 'icon' => '💚', 'desc' => 'Numbers for WeChat verification.'],
            'viber'      => ['key' => 'viber', 'name' => 'Viber', 'service' => 'Viber', 'icon' => '🟣', 'desc' => 'Numbers for Viber verification.'],
            'signal'     => ['key' => 'signal', 'name' => 'Signal', 'service' => 'Signal', 'icon' => '🔵', 'desc' => 'Numbers for Signal verification.'],
            'truecaller' => ['key' => 'truecaller', 'name' => 'Truecaller', 'service' => 'TrueCaller', 'icon' => '📞', 'desc' => 'Numbers for Truecaller verification.'],
        ];
    }

    public function app(string $key): ?array
    {
        return $this->apps()[$key] ?? null;
    }

    /**
     * Countries offered for one-time OTP, pulled live from SMSPool and cached.
     * Shape kept identical to the old service: country/name/iso/prefix.
     */
    public function countries(): array
    {
        return Cache::remember('smspool.countries.v1', now()->addHours(12), function () {
            $res = $this->http('/country/retrieve_all', [], 'GET', false);
            $rows = ($res['ok'] ?? false) === true && is_array($res['json'] ?? null) ? $res['json'] : [];

            $out = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $iso = strtoupper(trim((string) ($row['short_name'] ?? '')));
                $name = trim((string) ($row['name'] ?? ''));
                if ($iso === '' || $name === '') {
                    continue;
                }
                $out[] = [
                    'country' => $iso,
                    'name'    => $name,
                    'iso'     => $iso,
                    'prefix'  => $this->dialingPrefix($iso),
                    'smspool_id' => (int) ($row['ID'] ?? 0),
                    'region'  => (string) ($row['region'] ?? ''),
                ];
            }

            usort($out, fn ($a, $b) => strcmp($a['name'], $b['name']));

            return $out;
        });
    }

    public function country(string $code): ?array
    {
        $code = strtoupper(trim($code));
        foreach ($this->countries() as $country) {
            if ((string) $country['country'] === $code) {
                return $country;
            }
        }

        return null;
    }

    /**
     * SMSPool has no per-carrier selection for the pools we use; the pool is
     * chosen automatically by price. Kept so the UI operator dropdown still
     * renders.
     */
    public function operators(string $country): array
    {
        return [['key' => 'any', 'name' => 'Any']];
    }

    public function profile(): array
    {
        $res = $this->http('/request/balance');
        if (($res['ok'] ?? false) !== true) {
            return $res;
        }

        return ['ok' => true, 'status' => 200, 'json' => $res['json']];
    }

    public function balance(): array
    {
        return $this->profile();
    }

    /**
     * Resolve an SMSPool numeric service id from its name (cached).
     */
    public function serviceId(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return $this->matchServiceName($this->serviceMap(), $name);
    }

    /**
     * The full SMSPool service catalogue, cached.
     *
     * @return list<array{id:int,name:string}>
     */
    public function serviceCatalog(): array
    {
        return Cache::remember('smspool.service_catalog.v1', now()->addHours(12), function () {
            $res = $this->http('/service/retrieve_all', [], 'GET', false);
            $rows = ($res['ok'] ?? false) === true && is_array($res['json'] ?? null) ? $res['json'] : [];
            $out = [];
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['ID'], $row['name'])) {
                    $out[] = ['id' => (int) $row['ID'], 'name' => trim((string) $row['name'])];
                }
            }
            usort($out, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

            return $out;
        });
    }

    /** @return array<string,int> lower-cased service name => id */
    public function serviceMap(): array
    {
        return Cache::remember('smspool.services.v1', now()->addHours(12), function () {
            $map = [];
            foreach ($this->serviceCatalog() as $row) {
                $map[mb_strtolower($row['name'])] = $row['id'];
            }

            return $map;
        });
    }

    /**
     * Resolve a "product" (a curated app key, or an arbitrary SMSPool service id
     * / name chosen from the full catalogue) to an app-shaped row.
     *
     * @return array{key:string,name:string,service:string,service_id:int,icon:string,desc:string}|null
     */
    public function resolveProduct(string $product): ?array
    {
        $product = trim($product);
        if ($product === '') {
            return null;
        }

        $curated = $this->app($product);
        if ($curated) {
            $id = $this->serviceId((string) $curated['service']);

            return $id === null ? null : [
                'key' => $curated['key'],
                'name' => $curated['name'],
                'service' => (string) $curated['service'],
                'service_id' => $id,
                'icon' => (string) ($curated['icon'] ?? ''),
                'desc' => (string) ($curated['desc'] ?? ''),
            ];
        }

        // Arbitrary catalogue entry: product is a numeric service id or a name.
        foreach ($this->serviceCatalog() as $row) {
            if ((string) $row['id'] === $product || mb_strtolower($row['name']) === mb_strtolower($product)) {
                return [
                    'key' => (string) $row['id'],
                    'name' => $row['name'],
                    'service' => $row['name'],
                    'service_id' => $row['id'],
                    'icon' => '',
                    'desc' => '',
                ];
            }
        }

        return null;
    }

    /**
     * Country coverage + cheapest price for each curated app, fetched
     * concurrently and cached. Keyed by app key.
     *
     * @return array<string,array{countries:int,min_price:float}>
     */
    public function curatedCoverage(): array
    {
        return Cache::remember('smspool.curated_coverage.v2', now()->addMinutes(45), function () {
            $apps = $this->apps();
            $map = $this->serviceMap();

            $ids = [];
            foreach ($apps as $key => $app) {
                $id = $this->matchServiceName($map, (string) $app['service']);
                if ($id !== null) {
                    $ids[$key] = $id;
                }
            }
            if ($ids === []) {
                return [];
            }

            $baseUrl = rtrim(trim((string) config('services.smspool.base_url', 'https://api.smspool.net')), '/');
            $apiKey = trim((string) config('services.smspool.api_key'));

            try {
                $responses = Http::pool(fn ($pool) => collect($ids)->map(
                    fn ($id, $key) => $pool->as($key)->asForm()->timeout(20)
                        ->post($baseUrl.'/request/suggested_countries', ['service' => $id, 'key' => $apiKey])
                )->all());
            } catch (\Throwable) {
                return [];
            }

            $out = [];
            foreach ($ids as $key => $id) {
                $res = $responses[$key] ?? null;
                $rows = is_object($res) && method_exists($res, 'json') ? $res->json() : null;
                if (! is_array($rows) || $rows === []) {
                    continue;
                }
                $min = null;
                foreach ($rows as $row) {
                    if (isset($row['price']) && ($min === null || (float) $row['price'] < $min)) {
                        $min = (float) $row['price'];
                    }
                }
                $out[$key] = ['countries' => count($rows), 'min_price' => $min ?? 0.0];
            }

            return $out;
        });
    }

    /**
     * Resolve "<app>" against SMSPool's service names, tolerating the "/" and
     * " / " compound names they use ("Instagram / Threads", "TikTok/Douyin",
     * "Facebook / Meta Viewpoints", "Twitter / X").
     *
     * @param array<string,int> $map  lower-cased service name => id
     */
    public function matchServiceName(array $map, string $name): ?int
    {
        $key = mb_strtolower(trim($name));
        if ($key === '') {
            return null;
        }
        if (isset($map[$key])) {
            return $map[$key];
        }

        // First token of the wanted name ("instagram / threads" -> "instagram").
        $wantedHead = trim(preg_split('#\s*/\s*#', $key)[0] ?? $key);

        foreach ($map as $serviceName => $id) {
            $head = trim(preg_split('#\s*/\s*#', $serviceName)[0] ?? $serviceName);
            if ($head === $key || $head === $wantedHead) {
                return $id;
            }
        }

        // Last resort: a compound name that contains the wanted head as a word.
        foreach ($map as $serviceName => $id) {
            if (preg_match('/(^|[\s\/])'.preg_quote($wantedHead, '/').'([\s\/]|$)/', $serviceName)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Availability + cheapest price for an app/country pair.
     */
    public function quote(string $product, string $country): array
    {
        $app = $this->resolveProduct($product);
        $countryRow = $this->country($country);
        if (! $app || ! $countryRow) {
            return ['ok' => false, 'status' => 422, 'error' => 'Invalid product or country.'];
        }

        $serviceId = (int) $app['service_id'];

        $iso = (string) $countryRow['country'];

        $pools = $this->http('/pool/retrieve_valid', [
            'service' => $serviceId,
            'country' => $iso,
            'web'     => 1,
        ]);
        if (($pools['ok'] ?? false) !== true || ! is_array($pools['json'] ?? null) || $pools['json'] === []) {
            return ['ok' => false, 'status' => 404, 'error' => 'No numbers are currently available for this app and country.'];
        }

        $cheapest = null;
        foreach ($pools['json'] as $row) {
            if (! is_array($row) || ! isset($row['price'])) {
                continue;
            }
            if ($cheapest === null || (float) $row['price'] < (float) $cheapest['price']) {
                $cheapest = $row;
            }
        }
        if ($cheapest === null) {
            return ['ok' => false, 'status' => 404, 'error' => 'No numbers are currently available for this app and country.'];
        }

        $poolId = (int) ($cheapest['pool'] ?? 0);
        $providerPrice = (float) $cheapest['price'];
        $providerCostMinor = max(0, (int) round($providerPrice * 100));

        $count = 0;
        $stock = $this->http('/sms/stock', [
            'service' => $serviceId,
            'country' => $iso,
            'pool'    => $poolId,
        ]);
        if (($stock['ok'] ?? false) === true && is_array($stock['json'] ?? null)) {
            $count = (int) ($stock['json']['amount'] ?? 0);
        }
        if ($count <= 0) {
            // pool/retrieve_valid already implies at least one number is orderable
            $count = 1;
        }

        $sellAmountMinor = $this->sellAmountMinor($providerCostMinor);

        return [
            'ok' => true,
            'app' => $app,
            'country' => $countryRow,
            'service_id' => $serviceId,
            'pool' => $poolId,
            'count' => $count,
            'total' => $count,
            'provider_cost_minor' => $providerCostMinor,
            'provider_price' => $providerPrice,
            'sell_amount_minor' => $sellAmountMinor,
            'sell_price' => number_format($sellAmountMinor / 100, 2, '.', ''),
            'raw' => ['pools' => $pools['json']],
        ];
    }

    /**
     * Purchase a number. Signature kept from the old v2 method; the pool and
     * price are re-resolved here (SMSPool re-prices at order time anyway).
     */
    public function buyNumberV2(string $service, string $country, ?string $operator = null): array
    {
        $serviceId = $this->serviceId($service);
        $countryRow = $this->country($country);
        if ($serviceId === null || ! $countryRow) {
            return ['ok' => false, 'status' => 422, 'error' => 'Invalid product or country.'];
        }

        $iso = (string) $countryRow['country'];

        // Candidate pools, cheapest first. Some pools are whitelist-only per
        // account, so we walk them in order until one sells us a number.
        $candidates = [];
        $pools = $this->http('/pool/retrieve_valid', ['service' => $serviceId, 'country' => $iso, 'web' => 1]);
        if (($pools['ok'] ?? false) === true && is_array($pools['json'] ?? null)) {
            foreach ($pools['json'] as $row) {
                if (is_array($row) && isset($row['pool'], $row['price'])) {
                    $candidates[] = ['pool' => (int) $row['pool'], 'price' => (float) $row['price']];
                }
            }
            usort($candidates, fn ($a, $b) => $a['price'] <=> $b['price']);
        }
        if ($candidates === []) {
            $candidates[] = ['pool' => null, 'price' => null]; // let SMSPool choose
        }

        $lastError = 'Number purchase failed.';
        $lastStatus = 502;
        foreach ($candidates as $cand) {
            $params = [
                'service'         => $serviceId,
                'country'         => $iso,
                'quantity'        => 1,
                'activation_type' => 'SMS',
            ];
            if ($cand['pool'] !== null) {
                $params['pool'] = $cand['pool'];
            }
            if ($cand['price'] !== null) {
                $params['max_price'] = number_format($cand['price'] + 0.05, 2, '.', '');
            }

            $res = $this->http('/purchase/sms', $params);
            $json = is_array($res['json'] ?? null) ? $res['json'] : [];

            if (($res['ok'] ?? false) === true && (int) ($json['success'] ?? 0) === 1) {
                return $this->normalisePurchase($json, $cand['pool']);
            }

            $lastError = $this->errorMessage($json, 'Number purchase failed.');
            $lastStatus = (int) ($res['status'] ?? 502);

            // A balance problem won't be fixed by trying another pool.
            if (($json['type'] ?? '') === 'BALANCE_ERROR' || stripos($lastError, 'balance') !== false) {
                break;
            }
        }

        return ['ok' => false, 'status' => $lastStatus, 'error' => $lastError];
    }

    private function normalisePurchase(array $json, ?int $pool): array
    {
        return [
            'ok' => true,
            'status' => 200,
            'json' => [
                'data' => [
                    'orderId'        => (string) ($json['order_id'] ?? ''),
                    'phoneNumber'    => (string) ($json['number'] ?? ''),
                    'nationalNumber' => (string) ($json['phonenumber'] ?? ''),
                    'countryCode'    => (string) ($json['cc'] ?? ''),
                    'pool'           => $json['pool'] ?? $pool,
                    'expiration'     => $json['expiration'] ?? null,
                    'expires_in'     => $json['expires_in'] ?? null,
                    'cost_in_cents'  => $json['cost_in_cents'] ?? null,
                    'raw'            => $json,
                ],
            ],
        ];
    }

    /**
     * Poll for the SMS. Normalised to the old SMSPVA "response" codes the route
     * understands: "1" = code ready, "2" = still waiting, "3" = dead/refunded.
     */
    public function getSms(string $service, string $country, string $providerOrderId): array
    {
        $res = $this->http('/sms/check', ['orderid' => $providerOrderId]);
        $json = is_array($res['json'] ?? null) ? $res['json'] : [];

        if (($res['ok'] ?? false) !== true) {
            return $res;
        }

        $status = (int) ($json['status'] ?? 0);
        $timeLeft = isset($json['time_left']) ? (int) $json['time_left'] : null;
        $expiration = isset($json['expiration']) ? (int) $json['expiration'] : null;

        if ($status === 3) {
            $code = trim((string) ($json['sms'] ?? ''));
            $full = trim((string) ($json['full_sms'] ?? $code));

            return ['ok' => true, 'status' => 200, 'json' => [
                'response' => '1',
                'sms' => ['code' => $code, 'fullText' => $full],
                'text' => $full,
                'time_left' => $timeLeft,
                'expiration' => $expiration,
                'raw' => $json,
            ]];
        }

        if ($status === 6) {
            return ['ok' => true, 'status' => 200, 'json' => [
                'response' => '3',
                'refunded' => true,
                'time_left' => $timeLeft,
                'expiration' => $expiration,
                'raw' => $json,
            ]];
        }

        // status 1 (or anything else) — still waiting
        return ['ok' => true, 'status' => 200, 'json' => [
            'response' => '2',
            'time_left' => $timeLeft,
            'expiration' => $expiration,
            'raw' => $json,
        ]];
    }

    public function cancel(string $service, ?string $arg2 = null, ?string $arg3 = null): array
    {
        return $this->cancelOrder($arg3 ?? $arg2 ?? $service);
    }

    public function cancelV2(string|int $providerOrderId): array
    {
        return $this->cancelOrder((string) $providerOrderId);
    }

    public function ban(string $service, ?string $arg2 = null, ?string $arg3 = null): array
    {
        // SMSPool has no "ban"; cancelling releases and refunds the number.
        return $this->cancelOrder($arg3 ?? $arg2 ?? $service);
    }

    public function banV2(string|int $providerOrderId): array
    {
        return $this->cancelOrder((string) $providerOrderId);
    }

    private function cancelOrder(string $providerOrderId): array
    {
        if (trim($providerOrderId) === '') {
            return ['ok' => false, 'status' => 422, 'error' => 'Missing order id.'];
        }

        $res = $this->http('/sms/cancel', ['orderid' => $providerOrderId]);
        $json = is_array($res['json'] ?? null) ? $res['json'] : [];

        if (($res['ok'] ?? false) === true && (int) ($json['success'] ?? 0) === 1) {
            return ['ok' => true, 'status' => 200, 'json' => $json];
        }

        // "cannot be cancelled yet" / "not found" — surface but don't hard fail
        return ['ok' => false, 'status' => (int) ($res['status'] ?? 400), 'error' => $this->errorMessage($json, 'Unable to cancel this number yet.'), 'json' => $json];
    }

    public function sellAmountMinor(int $providerCostMinor): int
    {
        $multiplier = (float) config('services.smspool.markup_multiplier', 1.4);
        if ($multiplier < 1) {
            $multiplier = 1;
        }
        $min = max(1, (int) config('services.smspool.minimum_sell_minor', 100));
        $amount = (int) ceil($providerCostMinor * $multiplier);

        return max($min, $amount);
    }

    public function formatUsd(int $amountMinor): string
    {
        return '$'.number_format($amountMinor / 100, 2);
    }

    /**
     * Low-level SMSPool request. GET endpoints (country/service lists) take no
     * key; everything else is POST form-data with the API key.
     *
     * @return array{ok:bool,status:int,json:mixed,error?:string}
     */
    public function http(string $path, array $params = [], string $method = 'POST', bool $auth = true): array
    {
        $baseUrl = rtrim(trim((string) config('services.smspool.base_url', 'https://api.smspool.net')), '/');
        if ($baseUrl === '') {
            $baseUrl = 'https://api.smspool.net';
        }

        $apiKey = trim((string) config('services.smspool.api_key'));
        if ($auth && $apiKey === '') {
            return ['ok' => false, 'status' => 500, 'json' => null, 'error' => 'SMSPool is not configured.'];
        }
        if ($auth) {
            $params['key'] = $apiKey;
        }

        try {
            $request = Http::timeout(30)->retry(1, 500, throw: false)->acceptJson();
            $res = strtoupper($method) === 'GET'
                ? $request->get($baseUrl.$path, $params)
                : $request->asForm()->post($baseUrl.$path, $params);
        } catch (\Throwable) {
            return ['ok' => false, 'status' => 502, 'json' => null, 'error' => 'SMSPool request failed.'];
        }

        $status = (int) $res->status();
        $json = $res->json();
        if (! is_array($json)) {
            $body = trim((string) $res->body());

            return ['ok' => false, 'status' => $status, 'json' => null, 'error' => $body !== '' ? $body : 'SMSPool returned an invalid response.'];
        }

        if ($res->successful()) {
            return ['ok' => true, 'status' => $status, 'json' => $json];
        }

        return ['ok' => false, 'status' => $status, 'json' => $json, 'error' => $this->errorMessage($json, 'SMSPool error.')];
    }

    private function errorMessage(array $json, string $fallback): string
    {
        if (isset($json['errors'][0]['message'])) {
            return (string) $json['errors'][0]['message'];
        }
        if (isset($json['pools']) && is_array($json['pools'])) {
            foreach ($json['pools'] as $pool) {
                if (is_array($pool) && isset($pool['errors'][0]['message'])) {
                    return (string) $pool['errors'][0]['message'];
                }
                if (is_array($pool) && isset($pool['message'])) {
                    return trim(strip_tags((string) $pool['message']));
                }
            }
        }
        if (isset($json['message'])) {
            return trim(strip_tags((string) $json['message'])) ?: $fallback;
        }

        return $fallback;
    }

    private function dialingPrefix(string $iso): string
    {
        static $map = [
            'US' => '+1', 'CA' => '+1', 'GB' => '+44', 'FR' => '+33', 'DE' => '+49',
            'ES' => '+34', 'IT' => '+39', 'AU' => '+61', 'MX' => '+52', 'BR' => '+55',
            'PH' => '+63', 'ID' => '+62', 'JP' => '+81', 'RO' => '+40', 'PT' => '+351',
            'AR' => '+54', 'PL' => '+48', 'GR' => '+30', 'NL' => '+31', 'TR' => '+90',
            'UA' => '+380', 'VN' => '+84', 'RU' => '+7', 'KZ' => '+7', 'SE' => '+46',
            'LV' => '+371', 'EE' => '+372', 'DK' => '+45', 'IL' => '+972', 'KG' => '+996',
            'IN' => '+91', 'NG' => '+234', 'ZA' => '+27', 'KE' => '+254', 'HK' => '+852',
        ];

        return $map[strtoupper($iso)] ?? '';
    }
}
