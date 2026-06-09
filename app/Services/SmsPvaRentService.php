<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class SmsPvaRentService
{
    public function isConfigured(): bool
    {
        return trim((string) config('services.smspva.api_key')) !== '';
    }

    public function apps(): array
    {
        return app(SmsPvaService::class)->apps();
    }

    public function app(string $key): ?array
    {
        $apps = $this->apps();

        return $apps[$key] ?? null;
    }

    public function countries(): array
    {
        return app(SmsPvaService::class)->countries();
    }

    public function country(string $code): ?array
    {
        return app(SmsPvaService::class)->country($code);
    }

    public function countriesFromProvider(): array
    {
        return $this->request('getcountries', [], false);
    }

    public function data(string $country, string $dtype = 'month', int $dcount = 1): array
    {
        return $this->request('getdataWithProviders', [
            'country' => strtoupper($country),
            'dtype' => $dtype,
            'dcount' => max(1, $dcount),
        ], false);
    }

    public function quote(string $product, string $country, string $provider = 'all_providers', string $dtype = 'month', int $dcount = 1): array
    {
        $app = $this->app($product);
        $countryRow = $this->country($country);
        if (! $app || ! $countryRow) {
            return ['ok' => false, 'status' => 422, 'error' => 'Invalid product or country.'];
        }

        $res = $this->data((string) $countryRow['country'], $dtype, $dcount);
        if (($res['ok'] ?? false) !== true) {
            return $res;
        }

        $json = is_array($res['json'] ?? null) ? $res['json'] : [];
        $data = is_array($json['data'] ?? null) ? $json['data'] : [];
        $services = is_array($data['services'] ?? null) ? $data['services'] : (is_array($json['data'] ?? null) ? $json['data'] : []);
        $serviceRow = null;
        foreach ($services as $row) {
            if (is_array($row) && (string) ($row['service'] ?? '') === (string) $app['service']) {
                $serviceRow = $row;
                break;
            }
        }
        if (! is_array($serviceRow)) {
            return ['ok' => false, 'status' => 404, 'error' => 'This monthly rental is not available for the selected country.'];
        }

        $count = 0;
        if (is_array($serviceRow['count'] ?? null)) {
            if ($provider !== '' && $provider !== 'all_providers') {
                $count = (int) ($serviceRow['count'][$provider] ?? 0);
            } else {
                $count = (int) ($serviceRow['totalCount'] ?? array_sum(array_map('intval', $serviceRow['count'])));
            }
        } else {
            $count = (int) ($serviceRow['count'] ?? $serviceRow['totalCount'] ?? 0);
        }

        $pricePerDay = (float) ($serviceRow['price_day'] ?? 0);
        $days = $dtype === 'week' ? (7 * max(1, $dcount)) : (30 * max(1, $dcount));
        $providerCostMinor = max(0, (int) ceil($pricePerDay * $days * 100));
        $sellAmountMinor = $this->sellAmountMinor($providerCostMinor);

        return [
            'ok' => true,
            'app' => $app,
            'country' => $countryRow,
            'service' => $serviceRow,
            'provider' => $provider,
            'dtype' => $dtype,
            'dcount' => max(1, $dcount),
            'days' => $days,
            'count' => $count,
            'providers' => is_array($data['allOperators'] ?? null) ? $data['allOperators'] : [],
            'provider_cost_minor' => $providerCostMinor,
            'sell_amount_minor' => $sellAmountMinor,
            'sell_price' => number_format($sellAmountMinor / 100, 2, '.', ''),
            'raw' => $json,
        ];
    }

    public function create(string $service, string $country, string $dtype = 'month', int $dcount = 1, ?string $provider = null): array
    {
        $query = [
            'dtype' => $dtype,
            'dcount' => max(1, $dcount),
            'country' => strtoupper($country),
            'service' => $service,
        ];
        if (is_string($provider) && trim($provider) !== '' && trim($provider) !== 'all_providers') {
            $query['provider'] = trim($provider);
        }

        return $this->request('create', $query);
    }

    public function activate(string|int $id): array
    {
        return $this->request('activate', ['id' => (string) $id]);
    }

    public function sms(string|int $id): array
    {
        return $this->request('sms', ['id' => (string) $id]);
    }

    public function orders(): array
    {
        return $this->request('orders');
    }

    public function prolong(string|int $id, string $dtype = 'month', int $dcount = 1): array
    {
        return $this->request('prolong', [
            'id' => (string) $id,
            'dtype' => $dtype,
            'dcount' => max(1, $dcount),
        ]);
    }

    public function delete(string|int $id): array
    {
        return $this->request('delete', ['id' => (string) $id]);
    }

    public function sellAmountMinor(int $providerCostMinor): int
    {
        $multiplier = (float) config('services.smspva.rent_markup_multiplier', 1.35);
        if ($multiplier < 1) {
            $multiplier = 1;
        }
        $min = max(1, (int) config('services.smspva.rent_minimum_sell_minor', 500));
        $amount = (int) ceil($providerCostMinor * $multiplier);

        return max($min, $amount);
    }

    public function formatUsd(int $amountMinor): string
    {
        return '$'.number_format($amountMinor / 100, 2);
    }

    public function timestampToCarbon(mixed $value): ?Carbon
    {
        $ts = is_numeric($value) ? (int) $value : 0;
        if ($ts <= 0) {
            return null;
        }

        return Carbon::createFromTimestamp($ts);
    }

    private function request(string $method, array $query = [], bool $auth = true): array
    {
        $baseUrl = trim((string) config('services.smspva.rent_base_url', 'https://smspva.io/api/rent.php'));
        if ($baseUrl === '') {
            $baseUrl = 'https://smspva.io/api/rent.php';
        }

        $apiKey = trim((string) config('services.smspva.api_key'));
        if ($auth && $apiKey === '') {
            return ['ok' => false, 'status' => 500, 'error' => 'SMSPVA is not configured.'];
        }

        $payload = array_merge($query, ['method' => $method]);
        if ($auth) {
            $payload['apikey'] = $apiKey;
        }

        try {
            $res = Http::timeout(30)
                ->retry(1, 500, throw: false)
                ->acceptJson()
                ->get($baseUrl, $payload);
        } catch (\Throwable) {
            return ['ok' => false, 'status' => 502, 'error' => 'SMSPVA rent request failed.'];
        }

        $status = (int) $res->status();
        $json = $res->json();
        if (! is_array($json)) {
            $body = trim((string) $res->body());

            return ['ok' => false, 'status' => $status, 'error' => $body !== '' ? $body : 'SMSPVA rent returned an invalid response.'];
        }

        if ($res->ok() && (int) ($json['status'] ?? 0) === 1) {
            return ['ok' => true, 'status' => $status, 'json' => $json];
        }

        $error = trim((string) ($json['msg'] ?? $json['message'] ?? $json['error'] ?? ''));
        if ($error === '') {
            $error = 'SMSPVA rent error.';
        }

        return ['ok' => false, 'status' => $status, 'error' => $error, 'json' => $json];
    }
}
