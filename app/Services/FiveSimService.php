<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FiveSimService
{
    public function isConfigured(): bool
    {
        return trim((string) config('services.fivesim.token')) !== '';
    }

    public function profile(): array
    {
        return $this->request('GET', '/user/profile', [], true);
    }

    public function userOrders(string $category = 'activation', int $limit = 20, int $offset = 0, string $order = 'id', bool $reverse = true): array
    {
        $category = in_array($category, ['activation', 'hosting'], true) ? $category : 'activation';
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $order = trim($order) !== '' ? trim($order) : 'id';

        return $this->request('GET', '/user/orders', [
            'category' => $category,
            'limit' => $limit,
            'offset' => $offset,
            'order' => $order,
            'reverse' => $reverse ? 'true' : 'false',
        ], true);
    }

    public function guestCountries(): array
    {
        return $this->request('GET', '/guest/countries', [], false);
    }

    public function guestProducts(string $country = 'any', string $operator = 'any'): array
    {
        $country = trim($country) !== '' ? trim($country) : 'any';
        $operator = trim($operator) !== '' ? trim($operator) : 'any';

        return $this->request('GET', '/guest/products/'.urlencode($country).'/'.urlencode($operator), [], false);
    }

    public function guestPrices(?string $country = null, ?string $product = null): array
    {
        $query = [];
        if (is_string($country) && trim($country) !== '') {
            $query['country'] = trim($country);
        }
        if (is_string($product) && trim($product) !== '') {
            $query['product'] = trim($product);
        }

        return $this->request('GET', '/guest/prices', $query, false);
    }

    public function buyActivation(string $country, string $operator, string $product, ?float $maxPrice = null): array
    {
        $country = trim($country);
        $operator = trim($operator);
        $product = trim($product);

        if ($country === '' || $operator === '' || $product === '') {
            return ['ok' => false, 'status' => 422, 'error' => 'Invalid purchase parameters.'];
        }

        $query = [];
        if ($operator === 'any' && $maxPrice !== null && is_numeric($maxPrice) && $maxPrice > 0) {
            $query['maxPrice'] = (string) $maxPrice;
        }

        return $this->request('GET', '/user/buy/activation/'.urlencode($country).'/'.urlencode($operator).'/'.urlencode($product), $query, true);
    }

    public function checkOrder(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'status' => 422, 'error' => 'Invalid order id.'];
        }

        return $this->request('GET', '/user/check/'.urlencode((string) $id), [], true);
    }

    public function finishOrder(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'status' => 422, 'error' => 'Invalid order id.'];
        }

        return $this->request('GET', '/user/finish/'.urlencode((string) $id), [], true);
    }

    public function cancelOrder(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'status' => 422, 'error' => 'Invalid order id.'];
        }

        return $this->request('GET', '/user/cancel/'.urlencode((string) $id), [], true);
    }

    public function banOrder(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'status' => 422, 'error' => 'Invalid order id.'];
        }

        return $this->request('GET', '/user/ban/'.urlencode((string) $id), [], true);
    }

    private function request(string $method, string $path, array $query = [], bool $auth = false): array
    {
        $baseUrl = trim((string) config('services.fivesim.base_url', 'https://5sim.net/v1'));
        if ($baseUrl === '') {
            $baseUrl = 'https://5sim.net/v1';
        }

        $token = trim((string) config('services.fivesim.token'));
        if ($auth && $token === '') {
            return ['ok' => false, 'status' => 500, 'error' => '5SIM is not configured.'];
        }

        try {
            $req = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->timeout(25)
                ->retry(2, 250, throw: false);

            if ($auth) {
                $req = $req->withToken($token);
            }

            $res = $req->send($method, $path, [
                'query' => $query,
            ]);
        } catch (\Throwable) {
            return ['ok' => false, 'status' => 502, 'error' => '5SIM request failed.'];
        }

        $status = (int) $res->status();
        $json = $res->json();
        $ok = $res->ok();

        if ($ok) {
            return ['ok' => true, 'status' => $status, 'json' => is_array($json) ? $json : $json];
        }

        $error = '';
        if (is_array($json)) {
            $error = (string) ($json['message'] ?? $json['error'] ?? '');
        }
        if (trim($error) === '') {
            $error = trim((string) $res->body());
        }
        if (trim($error) === '') {
            $error = '5SIM error.';
        }

        return ['ok' => false, 'status' => $status, 'error' => $error, 'json' => $json];
    }
}

