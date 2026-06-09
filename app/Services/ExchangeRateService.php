<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    public function usdToNgn(): array
    {
        $cacheKey = 'exchange_rates.usd_to_ngn.v1';
        $cached = Cache::get($cacheKey);
        if ($this->isUsableRatePayload($cached)) {
            return $cached;
        }

        $url = (string) config('services.exchange_rates.usd_latest_url', 'https://open.er-api.com/v6/latest/USD');
        $cacheMinutes = max(5, (int) config('services.exchange_rates.cache_minutes', 60));
        $markupMultiplier = max(0.01, (float) config('services.exchange_rates.esim_markup_multiplier', 1));

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->retry(1, 200, throw: false)
                ->get($url);

            $rate = (float) $response->json('rates.NGN', 0);
            if ($response->successful() && $rate > 0) {
                $payload = [
                    'ok' => true,
                    'rate' => round($rate * $markupMultiplier, 4),
                    'base_rate' => $rate,
                    'markup_multiplier' => $markupMultiplier,
                    'source' => 'ExchangeRate-API open endpoint',
                    'fetched_at' => now()->toIso8601String(),
                ];

                Cache::put($cacheKey, $payload, now()->addMinutes($cacheMinutes));

                return $payload;
            }

            Log::warning('USD to NGN exchange rate lookup failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('USD to NGN exchange rate lookup exception', [
                'message' => $e->getMessage(),
            ]);
        }

        $fallback = (float) config('services.exchange_rates.fallback_usd_to_ngn', 0);
        if ($fallback > 0) {
            return [
                'ok' => true,
                'rate' => round($fallback * $markupMultiplier, 4),
                'base_rate' => $fallback,
                'markup_multiplier' => $markupMultiplier,
                'source' => 'ESIM_USD_TO_NGN_RATE fallback',
                'fetched_at' => now()->toIso8601String(),
            ];
        }

        return [
            'ok' => false,
            'rate' => 0,
            'source' => 'unavailable',
            'error' => 'Unable to fetch USD to NGN exchange rate.',
        ];
    }

    private function isUsableRatePayload(mixed $payload): bool
    {
        return is_array($payload) && (bool) ($payload['ok'] ?? false) && (float) ($payload['rate'] ?? 0) > 0;
    }
}
