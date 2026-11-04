<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin Paystack REST client. Returns raw responses so each flow keeps its own error mapping;
 * transport failures surface as ConnectionException.
 */
class PaystackService
{
    private const BASE_URL = 'https://api.paystack.co';

    /** Secret key with stray quotes/whitespace removed (a common .env copy-paste artefact). */
    public function secretKey(): string
    {
        return trim((string) config('services.paystack.secret_key', ''), " \t\n\r\0\x0B\"'");
    }

    public function isConfigured(): bool
    {
        return $this->secretKey() !== '';
    }

    /** Start a checkout; the response's `data.access_code` opens the Paystack popup. */
    public function initialize(array $payload): Response
    {
        return $this->http()->post('/transaction/initialize', $payload);
    }

    public function verify(string $reference): Response
    {
        return $this->http()->get('/transaction/verify/'.urlencode($reference));
    }

    /** Charge a saved card authorization (subscription renewals). */
    public function chargeAuthorization(array $payload): Response
    {
        return $this->http()->post('/transaction/charge_authorization', $payload);
    }

    public function balance(): Response
    {
        return $this->http(10)->get('/balance');
    }

    private function http(int $timeout = 30): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)->withToken($this->secretKey())->acceptJson()->timeout($timeout);
    }
}
