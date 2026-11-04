<?php

namespace App\Services;

use App\Contracts\RentalProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Monthly rentals via LongSMS (https://longsms.net) — long-term numbers only.
 *
 * API: POST {base}/api/client/v1/handler with an `X-Api-Key` header and a JSON body `{action: ...}`.
 * Envelope: {status: SUCCESS, data: {...}} | {status: ERROR, error: {num, code, message}}.
 *
 * Differences from SMSPool that matter here:
 *  - The rental is identified by its E.164 number (that is what we store as provider_order_id).
 *  - SMS arrive through one account-wide incremental feed (`listInbound`); we filter it per number.
 *  - There is no cancel/refund call. A rental simply runs until its paid period ends.
 *  - Pricing uses the same markup / minimum as SMSPool rentals (services.smspool.rent_*).
 */
class LongSmsRentService implements RentalProvider
{
    public const KEY = 'longsms_rent';

    private const DAYS = 30;

    public function key(): string
    {
        return self::KEY;
    }

    public function isConfigured(): bool
    {
        return trim((string) config('services.longsms.api_key')) !== '';
    }

    /** Apps (LongSMS "long projects") we offer. Codes come from config so they can be adjusted without a deploy. */
    public function apps(): array
    {
        $out = [];
        foreach ((array) config('services.longsms.projects', []) as $key => $app) {
            if (! is_array($app) || trim((string) ($app['code'] ?? '')) === '') {
                continue;
            }
            $out[(string) $key] = [
                'key' => (string) $key,
                'name' => (string) ($app['name'] ?? Str::headline((string) $key)),
                'service' => (string) $app['code'],
                'icon' => (string) ($app['icon'] ?? ''),
                'desc' => (string) ($app['desc'] ?? ''),
            ];
        }

        return $out;
    }

    public function app(string $key): ?array
    {
        return $this->apps()[$key] ?? null;
    }

    /** Countries come from config: LongSMS has no country-list call (US = country_id 1). */
    public function countries(): array
    {
        $out = [];
        foreach ((array) config('services.longsms.countries', []) as $row) {
            if (! is_array($row) || ! isset($row['id'], $row['iso'])) {
                continue;
            }
            $iso = strtoupper((string) $row['iso']);
            $out[] = [
                'country' => $iso,
                'name' => (string) ($row['name'] ?? $iso),
                'iso' => $iso,
                'prefix' => (string) ($row['prefix'] ?? ''),
                'rental_id' => (int) $row['id'],
                'country_id' => (int) $row['id'],
                'pool' => 0,
                'pricing' => [],
                'region' => '',
                'is_refundable' => false,
            ];
        }

        return $out;
    }

    public function country(string $code): ?array
    {
        $code = strtoupper(trim($code));
        foreach ($this->countries() as $country) {
            if ($country['country'] === $code) {
                return $country;
            }
        }

        return null;
    }

    /**
     * Price / availability via previewNumber (no charge). If the preview carries no price we fall back to
     * services.longsms.monthly_cost_usd; with neither we refuse to sell rather than guess a price.
     */
    public function quote(string $product, string $country, string $provider = 'all_providers', string $dtype = 'month', int $dcount = 1): array
    {
        $app = $this->app($product);
        $countryRow = $this->country($country);
        if (! $app || ! $countryRow) {
            return ['ok' => false, 'status' => 422, 'error' => 'Invalid product or country.'];
        }

        $res = $this->call('previewNumber', [
            'country_id' => $countryRow['country_id'],
            'duration_days' => self::DAYS,
            'long_project_code' => $app['service'],
            'allocation' => 'auto',
        ]);

        $count = 0;
        $costUsd = null;
        if (($res['ok'] ?? false) === true) {
            $count = 1;
            $costUsd = $this->firstNumber($res['data'] ?? [], ['amount', 'price', 'total', 'cost']);
        } elseif (($res['num'] ?? 0) !== 1101 && ($res['num'] ?? 0) !== 1102) {
            return ['ok' => false, 'status' => (int) ($res['status'] ?? 502), 'error' => (string) ($res['error'] ?? 'Unable to quote monthly rental.')];
        }

        $costUsd ??= is_numeric(config('services.longsms.monthly_cost_usd')) ? (float) config('services.longsms.monthly_cost_usd') : null;
        if ($costUsd === null || $costUsd <= 0) {
            return ['ok' => false, 'status' => 404, 'error' => $count === 0 ? 'No monthly rental numbers are available for this app and country.' : 'Monthly price is unavailable for this app and country.'];
        }

        $providerCostMinor = (int) round($costUsd * 100);
        $sellAmountMinor = $this->sellAmountMinor($providerCostMinor);

        return [
            'ok' => true,
            'app' => $app,
            'country' => $countryRow,
            'service' => ['service' => $app['service'], 'service_id' => $app['service']],
            'provider' => $provider,
            'dtype' => 'month',
            'dcount' => 1,
            'days' => self::DAYS,
            'count' => $count,
            'providers' => [],
            'rental_id' => (int) $countryRow['country_id'],
            'provider_cost_minor' => $providerCostMinor,
            'sell_amount_minor' => $sellAmountMinor,
            'sell_price' => number_format($sellAmountMinor / 100, 2, '.', ''),
            'raw' => [],
        ];
    }

    /** $service is the LongSMS project code. Normalised to json.data.{id,pnumber,ccode,until,days,cost_minor}. */
    public function create(string $service, string $country, string $dtype = 'month', int $dcount = 1, ?string $provider = null): array
    {
        $countryRow = $this->country($country);
        if (! $countryRow || trim($service) === '') {
            return ['ok' => false, 'status' => 422, 'error' => 'Invalid product or country.'];
        }

        $res = $this->call('getNumber', [
            'country_id' => $countryRow['country_id'],
            'duration_days' => self::DAYS,
            'long_project_code' => $service,
            'allocation' => 'auto',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        if (($res['ok'] ?? false) !== true) {
            return ['ok' => false, 'status' => (int) ($res['status'] ?? 502), 'error' => (string) ($res['error'] ?? 'Monthly rental purchase failed.')];
        }

        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        $number = trim((string) ($data['number'] ?? ''));
        $amount = $this->firstNumber($data, ['amount']);

        return ['ok' => true, 'status' => 200, 'json' => ['data' => [
            'id' => $number,
            'pnumber' => $number,
            'ccode' => '',
            'until' => $data['expire_at'] ?? null,
            'days' => self::DAYS,
            'cost_minor' => $amount !== null ? (int) round($amount * 100) : null,
            'activation_id' => $data['activation_id'] ?? null,
            'raw' => $data,
        ]]];
    }

    /** Numbers are live once bought; this just confirms the activation record exists. */
    public function activate(string|int $id): array
    {
        $res = $this->call('getStatus', ['number' => (string) $id], 'GET');
        if (($res['ok'] ?? false) !== true) {
            return ['ok' => false, 'status' => (int) ($res['status'] ?? 502), 'error' => (string) ($res['error'] ?? 'Activation check failed.')];
        }

        return ['ok' => true, 'status' => 200, 'json' => ['activated' => true, 'raw' => $res['data'] ?? []]];
    }

    /** Inbox for one number, filtered out of LongSMS's account-wide feed. Normalised to json.data[] of {sender,text,date}. */
    public function sms(string|int $id): array
    {
        $number = $this->normaliseNumber((string) $id);
        $feed = $this->inboxFeed();
        if (($feed['ok'] ?? false) !== true) {
            return $feed;
        }

        $data = [];
        foreach ($feed['items'] as $item) {
            if (! is_array($item) || $this->normaliseNumber((string) ($item['number'] ?? '')) !== $number) {
                continue;
            }
            $received = (string) ($item['received_at'] ?? '');
            $ts = $received !== '' ? strtotime($received.' UTC') : false;
            $data[] = [
                'sender' => (string) ($item['sender'] ?? 'SMS'),
                'text' => (string) ($item['content'] ?? $item['code'] ?? ''),
                'date' => $ts !== false ? $ts : time(),
            ];
        }

        return ['ok' => true, 'status' => 200, 'json' => ['data' => $data, 'raw' => ['count' => count($data)]]];
    }

    public function prolong(string|int $id, string $dtype = 'month', int $dcount = 1, int $days = self::DAYS): array
    {
        $res = $this->call('setStatus', [
            'number' => (string) $id,
            'status' => 'PROLONG',
            'duration_days' => max(1, $days),
        ]);
        if (($res['ok'] ?? false) !== true) {
            return ['ok' => false, 'status' => (int) ($res['status'] ?? 502), 'error' => (string) ($res['error'] ?? 'Rental extension failed.')];
        }

        $data = is_array($res['data'] ?? null) ? $res['data'] : [];

        return ['ok' => true, 'status' => 200, 'json' => ['data' => [
            'until' => $data['expire_at'] ?? $data['new_expire_at'] ?? null,
            'raw' => $data,
        ]]];
    }

    /** LongSMS cannot cancel or refund a rental through the API (error 1302). */
    public function delete(string|int $id): array
    {
        return ['ok' => false, 'status' => 422, 'error' => 'LongSMS rentals cannot be cancelled or refunded through the API; they run until the paid period ends.'];
    }

    public function balance(): array
    {
        $res = $this->call('getBalance', [], 'GET');
        if (($res['ok'] ?? false) !== true) {
            return $res;
        }

        return ['ok' => true, 'status' => 200, 'json' => $res['data'] ?? []];
    }

    /** Same margin as SMSPool rentals. */
    public function sellAmountMinor(int $providerCostMinor): int
    {
        $multiplier = max(1.0, (float) config('services.smspool.rent_markup_multiplier', 1.35));
        $min = max(1, (int) config('services.smspool.rent_minimum_sell_minor', 500));

        return max($min, (int) ceil($providerCostMinor * $multiplier));
    }

    public function formatUsd(int $amountMinor): string
    {
        return '$'.number_format($amountMinor / 100, 2);
    }

    /** LongSMS timestamps are UTC date-time strings ("2026-11-01 00:00:00"); numeric epoch is accepted too. */
    public function timestampToCarbon(mixed $value): ?Carbon
    {
        if (is_numeric($value)) {
            return (int) $value > 0 ? Carbon::createFromTimestamp((int) $value) : null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse($value, 'UTC')->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The account's full inbound feed (paged through next_after_id), cached briefly because LongSMS asks for
     * >=3s between listInbound calls and many customers may refresh at once.
     *
     * @return array{ok:bool,items?:array,status?:int,error?:string}
     */
    private function inboxFeed(): array
    {
        return Cache::remember('longsms.inbox_feed.v1', 5, function () {
            $items = [];
            $afterId = 0;
            for ($page = 0; $page < 20; $page++) {
                $res = $this->call('listInbound', ['after_id' => $afterId, 'limit' => 50]);
                if (($res['ok'] ?? false) !== true) {
                    return ['ok' => false, 'status' => (int) ($res['status'] ?? 502), 'error' => (string) ($res['error'] ?? 'Inbox request failed.')];
                }
                $data = is_array($res['data'] ?? null) ? $res['data'] : [];
                foreach ((array) ($data['items'] ?? []) as $item) {
                    $items[] = $item;
                }
                $next = (int) ($data['next_after_id'] ?? 0);
                if (! ($data['has_more'] ?? false) || $next <= $afterId) {
                    break;
                }
                $afterId = $next;
            }

            return ['ok' => true, 'items' => $items];
        });
    }

    /**
     * One API call. Returns ['ok','status','data'] on success or ['ok'=>false,'status','error','num'] on failure.
     * Balance / account problems are worded so customers never see our supplier account details.
     */
    private function call(string $action, array $params = [], string $method = 'POST'): array
    {
        $apiKey = trim((string) config('services.longsms.api_key'));
        if ($apiKey === '') {
            return ['ok' => false, 'status' => 500, 'error' => 'LongSMS is not configured.'];
        }

        $url = $this->baseUrl().'/api/client/v1/handler';

        try {
            $request = Http::timeout(30)->acceptJson()->withHeaders(['X-Api-Key' => $apiKey]);
            $res = strtoupper($method) === 'GET'
                ? $request->get($url, ['action' => $action] + $params)
                : $request->asJson()->post($url, ['action' => $action] + $params);
        } catch (\Throwable) {
            return ['ok' => false, 'status' => 502, 'error' => 'LongSMS request failed.'];
        }

        $json = $res->json();
        if (! is_array($json)) {
            return ['ok' => false, 'status' => (int) $res->status(), 'error' => 'LongSMS returned an invalid response.'];
        }

        if (($json['status'] ?? null) === 'SUCCESS' && $res->successful()) {
            return ['ok' => true, 'status' => (int) $res->status(), 'data' => is_array($json['data'] ?? null) ? $json['data'] : []];
        }

        $err = is_array($json['error'] ?? null) ? $json['error'] : [];
        $num = (int) ($err['num'] ?? 0);
        $message = trim((string) ($err['message'] ?? '')) ?: 'LongSMS error.';
        if ($num === 1201) {
            $message = 'Insufficient balance.';
        }

        return ['ok' => false, 'status' => (int) $res->status() ?: 502, 'error' => $message, 'num' => $num];
    }

    private function baseUrl(): string
    {
        return rtrim(trim((string) config('services.longsms.base_url', 'https://longsms.net')), '/') ?: 'https://longsms.net';
    }

    private function normaliseNumber(string $number): string
    {
        return preg_replace('/\D+/', '', $number) ?? '';
    }

    private function firstNumber(array $data, array $keys): ?float
    {
        foreach ($keys as $k) {
            if (isset($data[$k]) && is_numeric($data[$k])) {
                return (float) $data[$k];
            }
        }

        return null;
    }
}
