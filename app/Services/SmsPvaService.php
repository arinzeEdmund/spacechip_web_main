<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SmsPvaService
{
    public function isConfigured(): bool
    {
        return trim((string) config('services.smspva.api_key')) !== '';
    }

    public function apps(): array
    {
        return [
            'whatsapp' => ['key' => 'whatsapp', 'name' => 'WhatsApp', 'service' => 'opt20', 'icon' => '💬', 'desc' => 'SMS verification numbers for WhatsApp.'],
            'telegram' => ['key' => 'telegram', 'name' => 'Telegram', 'service' => 'opt29', 'icon' => '✈️', 'desc' => 'Verification numbers for Telegram.'],
            'instagram' => ['key' => 'instagram', 'name' => 'Instagram', 'service' => 'opt16', 'icon' => '📸', 'desc' => 'Numbers for IG verification codes.'],
            'facebook' => ['key' => 'facebook', 'name' => 'Facebook', 'service' => 'opt2', 'icon' => '👤', 'desc' => 'Verification numbers for Facebook.'],
            'tiktok' => ['key' => 'tiktok', 'name' => 'TikTok', 'service' => 'opt104', 'icon' => '🎵', 'desc' => 'Numbers for TikTok sign-up.'],
            'twitter' => ['key' => 'twitter', 'name' => 'X (Twitter)', 'service' => 'opt41', 'icon' => '✖️', 'desc' => 'Verification numbers for X.'],
        ];
    }

    public function countries(): array
    {
        return [
            ['country' => 'UK', 'name' => 'United Kingdom', 'iso' => 'GB', 'prefix' => '+44'],
            ['country' => 'US', 'name' => 'United States', 'iso' => 'US', 'prefix' => '+1'],
            ['country' => 'FR', 'name' => 'France', 'iso' => 'FR', 'prefix' => '+33'],
            ['country' => 'DE', 'name' => 'Germany', 'iso' => 'DE', 'prefix' => '+49'],
            ['country' => 'ES', 'name' => 'Spain', 'iso' => 'ES', 'prefix' => '+34'],
            ['country' => 'IT', 'name' => 'Italy', 'iso' => 'IT', 'prefix' => '+39'],
            ['country' => 'AU', 'name' => 'Australia', 'iso' => 'AU', 'prefix' => '+61'],
            ['country' => 'MX', 'name' => 'Mexico', 'iso' => 'MX', 'prefix' => '+52'],
            ['country' => 'BR', 'name' => 'Brazil', 'iso' => 'BR', 'prefix' => '+55'],
            ['country' => 'PH', 'name' => 'Philippines', 'iso' => 'PH', 'prefix' => '+63'],
            ['country' => 'ID', 'name' => 'Indonesia', 'iso' => 'ID', 'prefix' => '+62'],
            ['country' => 'JP', 'name' => 'Japan', 'iso' => 'JP', 'prefix' => '+81'],
            ['country' => 'RO', 'name' => 'Romania', 'iso' => 'RO', 'prefix' => '+40'],
            ['country' => 'PT', 'name' => 'Portugal', 'iso' => 'PT', 'prefix' => '+351'],
            ['country' => 'CA', 'name' => 'Canada', 'iso' => 'CA', 'prefix' => '+1'],
            ['country' => 'AR', 'name' => 'Argentina', 'iso' => 'AR', 'prefix' => '+54'],
            ['country' => 'PL', 'name' => 'Poland', 'iso' => 'PL', 'prefix' => '+48'],
            ['country' => 'GR', 'name' => 'Greece', 'iso' => 'GR', 'prefix' => '+30'],
            ['country' => 'NL', 'name' => 'Netherlands', 'iso' => 'NL', 'prefix' => '+31'],
            ['country' => 'TR', 'name' => 'Turkey', 'iso' => 'TR', 'prefix' => '+90'],
            ['country' => 'UA', 'name' => 'Ukraine', 'iso' => 'UA', 'prefix' => '+380'],
            ['country' => 'VN', 'name' => 'Vietnam', 'iso' => 'VN', 'prefix' => '+84'],
        ];
    }

    public function app(string $key): ?array
    {
        $apps = $this->apps();

        return $apps[$key] ?? null;
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

    public function profile(): array
    {
        return $this->request('get_userinfo');
    }

    public function balance(): array
    {
        return $this->request('get_balance');
    }

    public function count(string $service, string $country): array
    {
        return $this->request('get_count_new', [
            'service' => $service,
            'country' => strtoupper($country),
        ]);
    }

    public function price(string $service, string $country): array
    {
        return $this->request('get_service_price', [
            'service' => $service,
            'country' => strtoupper($country),
        ]);
    }

    public function quote(string $product, string $country): array
    {
        $app = $this->app($product);
        $countryRow = $this->country($country);
        if (! $app || ! $countryRow) {
            return ['ok' => false, 'status' => 422, 'error' => 'Invalid product or country.'];
        }

        $count = $this->count((string) $app['service'], (string) $countryRow['country']);
        if (($count['ok'] ?? false) !== true) {
            return $count;
        }

        $price = $this->price((string) $app['service'], (string) $countryRow['country']);
        if (($price['ok'] ?? false) !== true) {
            return $price;
        }

        $countJson = is_array($count['json'] ?? null) ? $count['json'] : [];
        $priceJson = is_array($price['json'] ?? null) ? $price['json'] : [];
        $providerPrice = (float) ($priceJson['price'] ?? 0);
        $providerCostMinor = max(0, (int) round($providerPrice * 100));
        $sellAmountMinor = $this->sellAmountMinor($providerCostMinor);

        return [
            'ok' => true,
            'app' => $app,
            'country' => $countryRow,
            'count' => max(0, (int) ($countJson['online'] ?? $countJson['total'] ?? 0)),
            'total' => max(0, (int) ($countJson['total'] ?? $countJson['online'] ?? 0)),
            'provider_cost_minor' => $providerCostMinor,
            'provider_price' => $providerPrice,
            'sell_amount_minor' => $sellAmountMinor,
            'sell_price' => number_format($sellAmountMinor / 100, 2, '.', ''),
            'raw' => ['count' => $countJson, 'price' => $priceJson],
        ];
    }

    public function buyNumber(string $service, string $country, ?string $operator = null): array
    {
        $query = [
            'service' => $service,
            'country' => strtoupper($country),
        ];
        if (is_string($operator) && trim($operator) !== '' && trim($operator) !== 'any') {
            $query['operator'] = trim($operator);
        }

        return $this->request('get_number', $query);
    }

    public function buyNumberV2(string $service, string $country, ?string $operator = null): array
    {
        $path = '/activation/number/'.rawurlencode(strtoupper($country)).'/'.rawurlencode($service);
        if (is_string($operator) && trim($operator) !== '' && trim($operator) !== 'any') {
            $path .= '/'.rawurlencode(trim($operator));
        }

        return $this->requestV2('GET', $path);
    }

    public function getSms(string $service, string $country, string $providerOrderId): array
    {
        return $this->request('get_sms', [
            'service' => $service,
            'country' => strtoupper($country),
            'id' => $providerOrderId,
        ]);
    }

    public function ordersV2(): array
    {
        return $this->requestV2('GET', '/activation/orders');
    }

    public function findOrderV2(string|int $providerOrderId): array
    {
        $res = $this->ordersV2();
        if (($res['ok'] ?? false) !== true) {
            return $res;
        }

        $orders = data_get($res, 'json.data.orders');
        if (! is_array($orders)) {
            $orders = [];
        }

        foreach ($orders as $row) {
            if (is_array($row) && (string) ($row['orderId'] ?? '') === (string) $providerOrderId) {
                return ['ok' => true, 'status' => (int) ($res['status'] ?? 200), 'json' => $row, 'raw' => $res['json'] ?? null];
            }
        }

        return ['ok' => true, 'status' => (int) ($res['status'] ?? 200), 'json' => null, 'raw' => $res['json'] ?? null];
    }

    public function cancel(string $service, string $country, string $providerOrderId): array
    {
        return $this->request('denial', [
            'service' => $service,
            'country' => strtoupper($country),
            'id' => $providerOrderId,
        ]);
    }

    public function cancelV2(string|int $providerOrderId): array
    {
        return $this->requestV2('PUT', '/activation/cancelorder/'.rawurlencode((string) $providerOrderId));
    }

    public function ban(string $service, string $providerOrderId): array
    {
        return $this->request('ban', [
            'service' => $service,
            'id' => $providerOrderId,
        ]);
    }

    public function banV2(string|int $providerOrderId): array
    {
        return $this->requestV2('PUT', '/activation/blocknumber/'.rawurlencode((string) $providerOrderId));
    }

    public function sellAmountMinor(int $providerCostMinor): int
    {
        $multiplier = (float) config('services.smspva.markup_multiplier', 1.4);
        if ($multiplier < 1) {
            $multiplier = 1;
        }
        $min = max(1, (int) config('services.smspva.minimum_sell_minor', 100));
        $amount = (int) ceil($providerCostMinor * $multiplier);

        return max($min, $amount);
    }

    public function formatUsd(int $amountMinor): string
    {
        return '$'.number_format($amountMinor / 100, 2);
    }

    private function request(string $method, array $query = []): array
    {
        $baseUrl = rtrim(trim((string) config('services.smspva.base_url', 'https://smspva.com/priemnik.php')), '?');
        if ($baseUrl === '') {
            $baseUrl = 'https://smspva.com/priemnik.php';
        }

        $apiKey = trim((string) config('services.smspva.api_key'));
        if ($apiKey === '') {
            return ['ok' => false, 'status' => 500, 'error' => 'SMSPVA is not configured.'];
        }
        if (! isset($query['service'])) {
            $query['service'] = 'opt20';
        }

        try {
            $res = Http::timeout(25)
                ->retry(1, 500, throw: false)
                ->acceptJson()
                ->get($baseUrl, array_merge($query, [
                    'metod' => $method,
                    'apikey' => $apiKey,
                ]));
        } catch (\Throwable) {
            return ['ok' => false, 'status' => 502, 'error' => 'SMSPVA request failed.'];
        }

        $status = (int) $res->status();
        $json = $res->json();
        if (! is_array($json)) {
            $body = trim((string) $res->body());

            return ['ok' => false, 'status' => $status, 'error' => $body !== '' ? $body : 'SMSPVA returned an invalid response.'];
        }

        $response = (string) ($json['response'] ?? $json['responce'] ?? '');
        if ($res->ok() && ($response === '1' || $response === 'ok' || $method === 'get_count_new')) {
            return ['ok' => true, 'status' => $status, 'json' => $json];
        }

        if ($method === 'get_sms' && in_array($response, ['2', '3', '4'], true)) {
            return ['ok' => true, 'status' => $status, 'json' => $json];
        }

        $error = trim((string) ($json['error_msg'] ?? $json['text'] ?? ''));
        if ($error === '') {
            $error = $this->responseMessage($response);
        }

        return ['ok' => false, 'status' => $status, 'error' => $error, 'json' => $json];
    }

    private function requestV2(string $method, string $path, array $query = []): array
    {
        $baseUrl = rtrim(trim((string) config('services.smspva.v2_base_url', 'https://api.smspva.com')), '/');
        if ($baseUrl === '') {
            $baseUrl = 'https://api.smspva.com';
        }

        $apiKey = trim((string) config('services.smspva.api_key'));
        if ($apiKey === '') {
            return ['ok' => false, 'status' => 500, 'error' => 'SMSPVA is not configured.'];
        }

        try {
            $res = Http::timeout(25)
                ->retry(1, 500, throw: false)
                ->acceptJson()
                ->withHeaders(['apikey' => $apiKey])
                ->send(strtoupper($method), $baseUrl.$path, ['query' => $query]);
        } catch (\Throwable) {
            return ['ok' => false, 'status' => 502, 'error' => 'SMSPVA v2 request failed.'];
        }

        $status = (int) $res->status();
        $json = $res->json();
        if (! is_array($json)) {
            $body = trim((string) $res->body());

            return ['ok' => false, 'status' => $status, 'error' => $body !== '' ? $body : 'SMSPVA v2 returned an invalid response.'];
        }

        if ($res->successful() && (int) ($json['statusCode'] ?? $status) >= 200 && (int) ($json['statusCode'] ?? $status) < 300) {
            return ['ok' => true, 'status' => $status, 'json' => $json];
        }

        $error = trim((string) (data_get($json, 'error.description') ?? data_get($json, 'error.type') ?? $json['message'] ?? ''));
        if ($error === '') {
            $error = 'SMSPVA v2 error.';
        }

        return ['ok' => false, 'status' => $status, 'error' => $error, 'json' => $json];
    }

    private function responseMessage(string $response): string
    {
        return match ($response) {
            '2' => 'No numbers are available right now. Try again in about a minute.',
            '5' => 'SMSPVA rate limit exceeded. Please wait and try again.',
            '6' => 'SMSPVA temporarily blocked requests because of account karma.',
            '7' => 'SMSPVA has too many concurrent streams. Wait for previous orders.',
            default => 'SMSPVA error.',
        };
    }
}
