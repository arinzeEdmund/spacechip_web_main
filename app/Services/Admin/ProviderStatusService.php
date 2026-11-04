<?php

namespace App\Services\Admin;

use App\Services\FiveSimService;
use App\Services\GloEsimService;
use App\Services\LongSmsRentService;
use App\Services\PaystackService;
use App\Services\SmsPoolService;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Live status of every upstream provider: configured?, reachable?, account balance.
 * Results are cached for five minutes so the dashboard stays fast and rate limits are respected.
 */
class ProviderStatusService
{
    private const CACHE_KEY = 'admin.provider_status.v1';

    private const TTL_SECONDS = 300;

    public function __construct(
        private GloEsimService $gloEsim,
        private FiveSimService $fiveSim,
        private SmsPoolService $smsPool,
        private LongSmsRentService $longSms,
        private TwilioService $twilio,
        private SettingsService $settings,
        private PaystackService $paystack,
    ) {}

    /** @return array{checked_at:string, providers:array<int,array<string,mixed>>} */
    public function all(bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget(self::CACHE_KEY);
        }

        // Fresh for 5 minutes; after that the last snapshot is served instantly and refreshed
        // in the background once the response is sent (stale-while-revalidate).
        $snapshot = Cache::flexible(self::CACHE_KEY, [self::TTL_SECONDS, 3600], fn () => [
            'checked_at' => now()->toIso8601String(),
            'providers' => [
                $this->safely('paystack', 'Paystack', 'Card payments', fn () => $this->paystack()),
                $this->safely('airalo', 'Airalo', 'eSIM supply', fn () => $this->airalo()),
                $this->safely('gloesim', 'GloEsim', 'eSIM supply (dealer)', fn () => $this->gloesim()),
                $this->safely('smspool', 'SMSPool', 'Social numbers & rentals', fn () => $this->smspool()),
                $this->safely('longsms', 'LongSMS', 'Monthly rentals (alternative)', fn () => $this->longsms()),
                $this->safely('fivesim', '5SIM', 'Social numbers (legacy)', fn () => $this->fivesim()),
                $this->safely('twilio', 'Twilio', 'Virtual numbers', fn () => $this->twilioStatus()),
                $this->safely('cryptomus', 'Cryptomus', 'Crypto payments', fn () => $this->configuredOnly(config('services.cryptomus.merchant'), config('services.cryptomus.payment_key'))),
                $this->safely('mail', 'Email ('.config('mail.default').')', 'Receipts & OTP codes', fn () => $this->configuredOnly(config('mail.default') !== 'log' ? 'ok' : null)),
            ],
        ]);

        $threshold = (float) $this->settings->get('provider_low_balance_usd');
        foreach ($snapshot['providers'] as &$p) {
            $p['low_balance'] = $p['balance'] !== null && $p['currency'] === 'USD' && $p['balance'] < $threshold;
        }

        return $snapshot;
    }

    private function safely(string $key, string $name, string $role, callable $probe): array
    {
        $base = ['key' => $key, 'name' => $name, 'role' => $role, 'configured' => false, 'ok' => false, 'balance' => null, 'currency' => null, 'detail' => null];
        try {
            return array_merge($base, $probe());
        } catch (Throwable $e) {
            return array_merge($base, ['configured' => true, 'detail' => mb_substr($e->getMessage(), 0, 160)]);
        }
    }

    private function configuredOnly(mixed ...$values): array
    {
        $configured = collect($values)->every(fn ($v) => trim((string) $v) !== '');

        return ['configured' => $configured, 'ok' => $configured, 'detail' => $configured ? 'Configured' : 'Not configured'];
    }

    private function paystack(): array
    {
        if (! $this->paystack->isConfigured()) {
            return ['detail' => 'Missing PAYSTACK_SECRET_KEY'];
        }
        $res = $this->paystack->balance();
        if (! $res->ok()) {
            return ['configured' => true, 'detail' => 'HTTP '.$res->status()];
        }
        $first = collect($res->json('data', []))->first();

        return [
            'configured' => true,
            'ok' => true,
            'balance' => $first ? round(((int) ($first['balance'] ?? 0)) / 100, 2) : null,
            'currency' => $first['currency'] ?? null,
            'detail' => 'Settlement balance',
        ];
    }

    private function airalo(): array
    {
        $configured = trim((string) config('services.airalo.client_id')) !== '' && trim((string) config('services.airalo.client_secret')) !== '';
        if (! $configured) {
            return ['detail' => 'Missing Airalo credentials'];
        }
        $base = rtrim((string) config('services.airalo.base_url', 'https://partners-api.airalo.com/v2'), '/');
        $res = Http::asForm()->acceptJson()->timeout(10)->post($base.'/token', [
            'client_id' => config('services.airalo.client_id'),
            'client_secret' => config('services.airalo.client_secret'),
            'grant_type' => 'client_credentials',
        ]);

        return ['configured' => true, 'ok' => $res->ok(), 'detail' => $res->ok() ? (config('services.airalo.sandbox') ? 'Sandbox · authenticated' : 'Authenticated') : 'Auth failed (HTTP '.$res->status().')'];
    }

    private function gloesim(): array
    {
        $configured = trim((string) config('services.gloesim.dealer_email')) !== '' && trim((string) config('services.gloesim.dealer_password')) !== '';
        if (! $configured) {
            return ['detail' => 'Missing dealer credentials'];
        }
        $json = $this->gloEsim->balance();
        $amount = $this->findNumber($json, ['balance', 'amount', 'available_balance']);

        return ['configured' => true, 'ok' => $json !== null, 'balance' => $amount, 'currency' => $amount !== null ? strtoupper((string) config('services.gloesim.currency', 'USD')) : null, 'detail' => $json === null ? 'No response' : 'Dealer balance'];
    }

    private function smspool(): array
    {
        if (! $this->smsPool->isConfigured()) {
            return ['detail' => 'Missing SMSPOOL_API_KEY'];
        }
        $res = $this->smsPool->balance();
        $amount = $this->findNumber($res['json'] ?? null, ['balance']);

        return ['configured' => true, 'ok' => ($res['ok'] ?? false) === true, 'balance' => $amount, 'currency' => $amount !== null ? 'USD' : null, 'detail' => ($res['ok'] ?? false) ? 'Account balance' : (string) ($res['error'] ?? 'Request failed')];
    }

    private function longsms(): array
    {
        if (! $this->longSms->isConfigured()) {
            return ['detail' => 'Missing LONGSMS_API_KEY'];
        }
        $res = $this->longSms->balance();
        $amount = $this->findNumber($res['json'] ?? null, ['balance']);

        return ['configured' => true, 'ok' => ($res['ok'] ?? false) === true, 'balance' => $amount, 'currency' => $amount !== null ? 'USD' : null, 'detail' => ($res['ok'] ?? false) ? 'Account balance' : (string) ($res['error'] ?? 'Request failed')];
    }

    private function fivesim(): array
    {
        if (! $this->fiveSim->isConfigured()) {
            return ['detail' => 'Missing FIVESIM_TOKEN'];
        }
        $res = $this->fiveSim->profile();
        $amount = $this->findNumber($res['json'] ?? $res, ['balance']);

        return ['configured' => true, 'ok' => ($res['ok'] ?? false) === true, 'balance' => $amount, 'currency' => $amount !== null ? 'RUB' : null, 'detail' => ($res['ok'] ?? false) ? 'Account balance' : (string) ($res['error'] ?? 'Request failed')];
    }

    private function twilioStatus(): array
    {
        if (! $this->twilio->isConfigured()) {
            return ['detail' => 'Missing Twilio credentials'];
        }
        $sid = (string) config('services.twilio.account_sid');
        $res = Http::withBasicAuth($sid, (string) config('services.twilio.auth_token'))
            ->acceptJson()->timeout(10)
            ->get("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Balance.json");

        return [
            'configured' => true,
            'ok' => $res->ok(),
            'balance' => $res->ok() ? round((float) $res->json('balance'), 2) : null,
            'currency' => $res->ok() ? strtoupper((string) $res->json('currency')) : null,
            'detail' => $res->ok() ? 'Account balance' : 'HTTP '.$res->status(),
        ];
    }

    /** Find the first numeric value under any of the given keys, searching nested arrays. */
    private function findNumber(mixed $data, array $keys): ?float
    {
        if (! is_array($data)) {
            return null;
        }
        foreach ($keys as $k) {
            if (isset($data[$k]) && is_numeric($data[$k])) {
                return round((float) $data[$k], 2);
            }
        }
        foreach ($data as $v) {
            if (is_array($v) && ($n = $this->findNumber($v, $keys)) !== null) {
                return $n;
            }
        }

        return null;
    }
}
