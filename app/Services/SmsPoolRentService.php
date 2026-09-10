<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Monthly rentals via SMSPool. SMSPool rentals are keyed by country (a "rental
 * ID") with an optional service lock; there is no separate activation step and
 * "1 month" maps to days=30.
 *
 * Public method names / return shapes mirror the previous SMSPVA rent service
 * so the /api/social-rentals/* routes and the renewal command are unchanged.
 */
class SmsPoolRentService
{
    private const DAYS = 30;

    public function isConfigured(): bool
    {
        return app(SmsPoolService::class)->isConfigured();
    }

    public function apps(): array
    {
        return app(SmsPoolService::class)->apps();
    }

    public function app(string $key): ?array
    {
        return app(SmsPoolService::class)->app($key);
    }

    /**
     * Countries available for monthly rental, from /rental/retrieve_all, mapped
     * back to ISO codes via the one-time country list. Cached.
     */
    public function countries(): array
    {
        return Cache::remember('smspool.rental.countries.v1', now()->addHours(6), function () {
            $res = app(SmsPoolService::class)->http('/rental/retrieve_all', ['type' => 1]);
            $rows = data_get($res, 'json.data');
            if (! is_array($rows)) {
                return [];
            }

            $isoByName = [];
            foreach (app(SmsPoolService::class)->countries() as $c) {
                $isoByName[mb_strtolower((string) $c['name'])] = $c;
            }

            $out = [];
            foreach ($rows as $row) {
                if (! is_array($row) || ! isset($row['ID'], $row['name'])) {
                    continue;
                }
                $name = trim((string) $row['name']);
                $match = $isoByName[mb_strtolower($name)] ?? null;
                $iso = $match['country'] ?? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 2));
                $pricing = is_array($row['pricing'] ?? null) ? $row['pricing'] : [];

                $out[] = [
                    'country'      => $iso,
                    'name'         => $name,
                    'iso'          => $iso,
                    'prefix'       => $match['prefix'] ?? '',
                    'rental_id'    => (int) $row['ID'],
                    'pool'         => (int) ($row['pool'] ?? 0),
                    'pricing'      => $pricing,
                    'region'       => (string) ($row['region'] ?? ''),
                    'is_refundable' => (int) ($row['is_refundable'] ?? 0) === 1,
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
     * Price / availability for an app + country monthly rental.
     * $provider / $dtype / $dcount are kept for signature compatibility; SMSPool
     * rentals have a fixed pool per country and only the 30-day period is sold.
     */
    public function quote(string $product, string $country, string $provider = 'all_providers', string $dtype = 'month', int $dcount = 1): array
    {
        $app = $this->app($product);
        $countryRow = $this->country($country);
        if (! $app || ! $countryRow) {
            return ['ok' => false, 'status' => 422, 'error' => 'Invalid product or country.'];
        }

        $rentalId = (int) $countryRow['rental_id'];
        $period = $this->monthlyPeriod($countryRow);
        if ($period === null) {
            return ['ok' => false, 'status' => 404, 'error' => 'Monthly rental is not available for the selected country.'];
        }

        $serviceId = $this->rentalServiceId($rentalId, (string) $app['service']);

        $count = 0;
        $stock = app(SmsPoolService::class)->http('/rental/stock', ['id' => $rentalId, 'days' => $period['days']]);
        if (($stock['ok'] ?? false) === true && is_array($stock['json'] ?? null)) {
            $count = (int) ($stock['json']['count'] ?? 0);
        }

        $providerCostMinor = max(0, (int) round($period['price'] * 100));
        $sellAmountMinor = $this->sellAmountMinor($providerCostMinor);

        return [
            'ok' => true,
            'app' => $app,
            'country' => $countryRow,
            'service' => ['service' => $serviceId, 'service_id' => $serviceId],
            'provider' => $provider,
            'dtype' => 'month',
            'dcount' => 1,
            'days' => $period['days'],
            'count' => $count,
            'providers' => [],
            'rental_id' => $rentalId,
            'provider_cost_minor' => $providerCostMinor,
            'sell_amount_minor' => $sellAmountMinor,
            'sell_price' => number_format($sellAmountMinor / 100, 2, '.', ''),
            'raw' => ['pricing' => $countryRow['pricing'] ?? []],
        ];
    }

    /**
     * Order a monthly rental. $service is the SMSPool service *name*.
     * Normalised to the old shape: json.data.{id,pnumber,ccode,until}.
     */
    public function create(string $service, string $country, string $dtype = 'month', int $dcount = 1, ?string $provider = null): array
    {
        $countryRow = $this->country($country);
        if (! $countryRow) {
            return ['ok' => false, 'status' => 422, 'error' => 'Invalid country.'];
        }

        $rentalId = (int) $countryRow['rental_id'];
        $period = $this->monthlyPeriod($countryRow);
        if ($period === null) {
            return ['ok' => false, 'status' => 404, 'error' => 'Monthly rental is not available for the selected country.'];
        }
        $serviceId = $this->rentalServiceId($rentalId, $service);

        $params = ['id' => $rentalId, 'days' => $period['days']];
        if ($serviceId !== null) {
            $params['service_id'] = $serviceId;
        }

        $res = app(SmsPoolService::class)->http('/purchase/rental', $params);
        $json = is_array($res['json'] ?? null) ? $res['json'] : [];

        if (($res['ok'] ?? false) !== true || (int) ($json['success'] ?? 0) !== 1) {
            return ['ok' => false, 'status' => (int) ($res['status'] ?? 502), 'error' => $this->error($json, 'Monthly rental purchase failed.')];
        }

        return ['ok' => true, 'status' => 200, 'json' => ['data' => [
            'id'      => (string) ($json['rental_code'] ?? ''),
            'pnumber' => (string) ($json['phonenumber'] ?? ''),
            'ccode'   => '',
            'until'   => $json['expiry'] ?? null,
            'days'    => $period['days'],
            'raw'     => $json,
        ]]];
    }

    /**
     * SMSPool rentals are live on purchase — no activation call.
     */
    public function activate(string|int $id): array
    {
        return ['ok' => true, 'status' => 200, 'json' => ['activated' => true, 'note' => 'SMSPool rentals activate automatically.']];
    }

    /**
     * Inbox for a rental. Normalised to json.data[] of {sender,text,date}.
     */
    public function sms(string|int $id): array
    {
        $res = app(SmsPoolService::class)->http('/rental/retrieve_messages', ['rental_code' => (string) $id]);
        $json = is_array($res['json'] ?? null) ? $res['json'] : [];

        if (($res['ok'] ?? false) !== true) {
            return $res;
        }

        $messages = is_array($json['messages'] ?? null) ? $json['messages'] : [];
        $data = [];
        foreach ($messages as $m) {
            if (! is_array($m)) {
                continue;
            }
            $ts = isset($m['timestamp']) ? strtotime((string) $m['timestamp']) : false;
            $data[] = [
                'sender' => (string) ($m['sender'] ?? 'SMS'),
                'text'   => (string) ($m['message'] ?? ''),
                'date'   => $ts !== false ? $ts : time(),
            ];
        }

        return ['ok' => true, 'status' => 200, 'json' => ['data' => $data, 'raw' => $json]];
    }

    /**
     * Extend (renew) a rental by one billing period. $days should match the
     * period the rental was bought on (carried in the rental's stored payload);
     * defaults to 30.
     * Normalised to json.data.until.
     */
    public function prolong(string|int $id, string $dtype = 'month', int $dcount = 1, int $days = self::DAYS): array
    {
        $res = app(SmsPoolService::class)->http('/rental/extend', ['rental_code' => (string) $id, 'days' => max(1, $days)]);
        $json = is_array($res['json'] ?? null) ? $res['json'] : [];

        if (($res['ok'] ?? false) !== true || (int) ($json['success'] ?? 0) !== 1) {
            return ['ok' => false, 'status' => (int) ($res['status'] ?? 502), 'error' => $this->error($json, 'Rental extension failed.')];
        }

        return ['ok' => true, 'status' => 200, 'json' => ['data' => [
            'until' => $json['expiration_date'] ?? null,
            'raw'   => $json,
        ]]];
    }

    public function delete(string|int $id): array
    {
        $res = app(SmsPoolService::class)->http('/rental/refund', ['rental_code' => (string) $id]);
        $json = is_array($res['json'] ?? null) ? $res['json'] : [];

        if (($res['ok'] ?? false) === true && (int) ($json['success'] ?? 0) === 1) {
            return ['ok' => true, 'status' => 200, 'json' => $json];
        }

        return ['ok' => false, 'status' => (int) ($res['status'] ?? 400), 'error' => $this->error($json, 'Rental refund failed.'), 'json' => $json];
    }

    public function sellAmountMinor(int $providerCostMinor): int
    {
        $multiplier = (float) config('services.smspool.rent_markup_multiplier', 1.35);
        if ($multiplier < 1) {
            $multiplier = 1;
        }
        $min = max(1, (int) config('services.smspool.rent_minimum_sell_minor', 500));
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

    /**
     * Pick the rental period that best represents "1 month" from whatever
     * day-buckets SMSPool sells for this country: exact 30 if present, else the
     * nearest bucket in 26-45 days, else the longest bucket available.
     *
     * @return array{days:int,price:float}|null
     */
    private function monthlyPeriod(array $countryRow): ?array
    {
        $pricing = is_array($countryRow['pricing'] ?? null) ? $countryRow['pricing'] : [];
        $buckets = [];
        foreach ($pricing as $days => $price) {
            $d = (int) $days;
            if ($d > 0 && is_numeric($price)) {
                $buckets[$d] = (float) $price;
            }
        }
        if ($buckets === []) {
            return null;
        }

        if (isset($buckets[self::DAYS])) {
            return ['days' => self::DAYS, 'price' => $buckets[self::DAYS]];
        }

        $near = null;
        foreach ($buckets as $d => $price) {
            if ($d >= 26 && $d <= 45 && ($near === null || abs($d - self::DAYS) < abs($near - self::DAYS))) {
                $near = $d;
            }
        }
        if ($near !== null) {
            return ['days' => $near, 'price' => $buckets[$near]];
        }

        $longest = max(array_keys($buckets));

        return ['days' => $longest, 'price' => $buckets[$longest]];
    }

    /**
     * Resolve the SMSPool rental service id for an app name within a rental
     * (country). Returns null when the app isn't individually listed — the
     * rental is then bought without a service lock.
     */
    private function rentalServiceId(int $rentalId, string $serviceName): ?int
    {
        $serviceName = trim($serviceName);
        if ($serviceName === '' || $rentalId <= 0) {
            return null;
        }

        $map = Cache::remember("smspool.rental.services.$rentalId.v1", now()->addHours(6), function () use ($rentalId) {
            $res = app(SmsPoolService::class)->http('/rental/retrieve_services', ['rental' => $rentalId]);
            $rows = is_array($res['json'] ?? null) ? $res['json'] : [];
            $map = [];
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['ID'], $row['name'])) {
                    $map[mb_strtolower(trim((string) $row['name']))] = (int) $row['ID'];
                }
            }

            return $map;
        });

        return app(SmsPoolService::class)->matchServiceName($map, $serviceName);
    }

    private function error(array $json, string $fallback): string
    {
        if (isset($json['errors'][0]['message'])) {
            return (string) $json['errors'][0]['message'];
        }
        if (isset($json['message'])) {
            return trim(strip_tags((string) $json['message'])) ?: $fallback;
        }

        return $fallback;
    }
}
