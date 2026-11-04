<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Legacy 5SIM account (social numbers moved to SMSPool). Only the balance check used by the
 * admin provider-status page remains.
 */
class FiveSimService
{
    public function isConfigured(): bool
    {
        return trim((string) config('services.fivesim.token')) !== '';
    }

    /** @return array{ok: bool, status: int, json?: mixed, error?: string} */
    public function profile(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'status' => 500, 'error' => '5SIM is not configured.'];
        }

        try {
            $res = Http::baseUrl(trim((string) config('services.fivesim.base_url', 'https://5sim.net/v1')) ?: 'https://5sim.net/v1')
                ->acceptJson()
                ->timeout(25)
                ->retry(2, 250, throw: false)
                ->withToken(trim((string) config('services.fivesim.token')))
                ->get('/user/profile');
        } catch (\Throwable) {
            return ['ok' => false, 'status' => 502, 'error' => '5SIM request failed.'];
        }

        if ($res->ok()) {
            return ['ok' => true, 'status' => $res->status(), 'json' => $res->json()];
        }

        $json = $res->json();
        $error = trim((string) (is_array($json) ? ($json['message'] ?? $json['error'] ?? '') : '')) ?: trim($res->body()) ?: '5SIM error.';

        return ['ok' => false, 'status' => $res->status(), 'error' => $error, 'json' => $json];
    }
}
