<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CryptomusService
{
    public function createInvoice(array $payload): ?array
    {
        return $this->post('/v1/payment', $payload);
    }

    public function paymentInfo(array $payload): ?array
    {
        return $this->post('/v1/payment/info', $payload);
    }

    public function verifyWebhookSignature(string $rawBody, string $providedSign): bool
    {
        $key = $this->paymentKey();
        if ($key === '') {
            return false;
        }

        $hash = md5(base64_encode($rawBody).$key);

        return hash_equals($hash, $providedSign);
    }

    public function signPayload(array $payload): string
    {
        $key = $this->paymentKey();
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $json = is_string($json) ? $json : '';

        return md5(base64_encode($json).$key);
    }

    private function post(string $path, array $payload): ?array
    {
        $merchant = $this->merchant();
        $key = $this->paymentKey();
        $apiUrl = rtrim((string) config('services.cryptomus.api_url', 'https://api.cryptomus.com'), '/');

        if ($merchant === '' || $key === '') {
            return null;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $json = is_string($json) ? $json : '';
        $sign = md5(base64_encode($json).$key);

        $verifySsl = (bool) config('services.cryptomus.verify_ssl', true);
        $forceIpv4 = (bool) config('services.cryptomus.force_ipv4', false);

        $options = [
            'verify' => $verifySsl,
            'connect_timeout' => 15,
        ];

        if ($forceIpv4) {
            $options['curl'] = [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ];
        }

        try {
            $res = Http::baseUrl($apiUrl)
                ->acceptJson()
                ->timeout(60)
                ->withOptions($options)
                ->withHeaders([
                    'merchant' => $merchant,
                    'sign' => $sign,
                ])
                ->post($path, $payload);
        } catch (\Throwable $e) {
            return [
                'state' => 1,
                'message' => $e->getMessage(),
            ];
        }

        if (! $res->successful()) {
            $body = $res->json();

            return [
                'state' => 1,
                'message' => $res->reason(),
                'status' => $res->status(),
                'body' => is_array($body) ? $body : null,
            ];
        }

        $out = $res->json();

        return is_array($out) ? $out : null;
    }

    private function merchant(): string
    {
        $merchant = (string) (config('services.cryptomus.merchant') ?: env('CRYPTOMUS_MERCHANT', ''));

        return trim($merchant, " \t\n\r\0\x0B\"'");
    }

    private function paymentKey(): string
    {
        $key = (string) (config('services.cryptomus.payment_key') ?: env('CRYPTOMUS_PAYMENT_KEY', ''));

        return trim($key, " \t\n\r\0\x0B\"'");
    }
}
