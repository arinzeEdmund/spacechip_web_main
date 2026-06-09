<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TwilioService
{
    public function isConfigured(): bool
    {
        return trim((string) config('services.twilio.account_sid')) !== '' && trim((string) config('services.twilio.auth_token')) !== '';
    }

    public function availableNumbers(string $countryIso, string $type, bool $sms = true, bool $voice = true, int $limit = 10, array $filters = []): array
    {
        $countryIso = strtoupper(trim($countryIso));
        if ($countryIso === '' || strlen($countryIso) !== 2) {
            return ['ok' => false, 'error' => 'Invalid country code.'];
        }

        $limit = max(1, min(50, $limit));
        $type = trim($type);
        $type = in_array($type, ['Local', 'Mobile', 'TollFree'], true) ? $type : 'Local';

        $query = array_merge([
            'PageSize' => $limit,
        ], $filters);

        if ($sms) {
            $query['SmsEnabled'] = 'true';
        }
        if ($voice) {
            $query['VoiceEnabled'] = 'true';
        }

        $resp = $this->get("/AvailablePhoneNumbers/{$countryIso}/{$type}.json", $query);
        if (! $resp['ok']) {
            return $resp;
        }

        $json = $resp['json'];
        $numbers = is_array($json) ? (data_get($json, 'available_phone_numbers') ?? []) : [];
        if (! is_array($numbers)) {
            $numbers = [];
        }

        $items = [];
        foreach ($numbers as $row) {
            if (! is_array($row)) {
                continue;
            }
            $items[] = [
                'friendly_name' => (string) (data_get($row, 'friendly_name') ?? ''),
                'phone_number' => (string) (data_get($row, 'phone_number') ?? ''),
                'iso_country' => (string) (data_get($row, 'iso_country') ?? $countryIso),
                'region' => (string) (data_get($row, 'region') ?? ''),
                'locality' => (string) (data_get($row, 'locality') ?? ''),
                'postal_code' => (string) (data_get($row, 'postal_code') ?? ''),
                'capabilities' => is_array(data_get($row, 'capabilities')) ? (array) data_get($row, 'capabilities') : [],
            ];
        }

        $nextPageUri = is_array($json) ? (data_get($json, 'next_page_uri') ?? null) : null;

        return ['ok' => true, 'items' => $items, 'next_page_uri' => is_string($nextPageUri) ? $nextPageUri : null, 'type' => $type];
    }

    public function availableAnyNumbers(string $countryIso, bool $sms = true, bool $voice = true, int $limit = 10, array $filters = []): array
    {
        $items = [];
        $seen = [];
        $hasMore = false;
        $typesTried = [];
        $modesTried = [];

        $modes = [
            ['sms' => $sms, 'voice' => $voice, 'label' => ($sms && $voice) ? 'sms+voice' : ($voice ? 'voice' : ($sms ? 'sms' : 'any'))],
        ];

        if ($sms && $voice) {
            $modes[] = ['sms' => false, 'voice' => true, 'label' => 'voice-only'];
            $modes[] = ['sms' => true, 'voice' => false, 'label' => 'sms-only'];
            $modes[] = ['sms' => false, 'voice' => false, 'label' => 'any'];
        }

        foreach ($modes as $mode) {
            $modesTried[] = (string) $mode['label'];
            foreach (['Local', 'Mobile', 'TollFree'] as $type) {
                $typesTried[] = $type;
                $res = $this->availableNumbers($countryIso, $type, (bool) $mode['sms'], (bool) $mode['voice'], $limit, $filters);
                if (($res['ok'] ?? false) !== true) {
                    continue;
                }

                $hasMore = $hasMore || (is_string($res['next_page_uri'] ?? null) && trim((string) $res['next_page_uri']) !== '');

                foreach (($res['items'] ?? []) as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $phone = trim((string) ($row['phone_number'] ?? ''));
                    if ($phone === '' || isset($seen[$phone])) {
                        continue;
                    }
                    $seen[$phone] = true;
                    $row['number_type'] = $type;
                    $row['match_mode'] = (string) $mode['label'];
                    $items[] = $row;
                }

                if (count($items) >= $limit) {
                    break 2;
                }
            }
        }

        return [
            'ok' => true,
            'items' => array_slice($items, 0, $limit),
            'has_more' => $hasMore,
            'types_tried' => array_values(array_unique($typesTried)),
            'modes_tried' => $modesTried,
        ];
    }

    public function availableCountries(): array
    {
        $sid = trim((string) config('services.twilio.account_sid'));
        if ($sid === '') {
            return ['ok' => false, 'error' => 'Twilio is not configured.'];
        }

        $seen = [];
        $items = [];

        $nextPath = '/AvailablePhoneNumbers.json?PageSize=1000';
        $loops = 0;

        while (is_string($nextPath) && trim($nextPath) !== '' && $loops < 10) {
            $loops++;
            $resp = $this->get($nextPath);
            if (! $resp['ok']) {
                return $resp;
            }

            $json = $resp['json'];
            $countries = is_array($json) ? (data_get($json, 'available_phone_numbers') ?? data_get($json, 'countries') ?? []) : [];
            if (! is_array($countries)) {
                $countries = [];
            }

            foreach ($countries as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $iso = strtoupper(trim((string) (data_get($row, 'country_code') ?? data_get($row, 'iso_country') ?? '')));
                if ($iso === '' || strlen($iso) !== 2 || isset($seen[$iso])) {
                    continue;
                }
                $seen[$iso] = true;

                $name = (string) (data_get($row, 'country_name')
                    ?? data_get($row, 'country')
                    ?? data_get($row, 'name')
                    ?? $iso);

                $items[] = [
                    'country_code' => $iso,
                    'country_name' => $name,
                ];
            }

            $nextUri = is_array($json) ? (data_get($json, 'next_page_uri') ?? null) : null;
            if (! is_string($nextUri) || trim($nextUri) === '') {
                break;
            }

            $needle = '/Accounts/'.$sid;
            $pos = strpos($nextUri, $needle);
            if ($pos !== false) {
                $nextPath = substr($nextUri, $pos + strlen($needle));
            } else {
                $nextPath = $nextUri;
            }
        }

        usort($items, fn ($a, $b) => strcmp((string) ($a['country_name'] ?? ''), (string) ($b['country_name'] ?? '')));

        return ['ok' => true, 'items' => $items];
    }

    public function phoneNumberPricing(string $countryIso): array
    {
        $countryIso = strtoupper(trim($countryIso));
        if ($countryIso === '' || strlen($countryIso) !== 2) {
            return ['ok' => false, 'error' => 'Invalid country code.'];
        }

        $cacheKey = 'twilio.pricing.phone_numbers.v1.'.$countryIso;
        $cached = cache()->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $sid = (string) config('services.twilio.account_sid');
        $token = (string) config('services.twilio.auth_token');
        if (trim($sid) === '' || trim($token) === '') {
            return ['ok' => false, 'error' => 'Twilio is not configured.'];
        }

        try {
            $res = Http::withBasicAuth($sid, $token)
                ->acceptJson()
                ->timeout(30)
                ->get('https://pricing.twilio.com/v1/PhoneNumbers/Countries/'.urlencode($countryIso));
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Twilio pricing request failed.'];
        }

        if (! $res->successful()) {
            $json = $res->json();
            $msg = is_array($json) ? trim((string) (data_get($json, 'message') ?? '')) : '';

            return ['ok' => false, 'status' => $res->status(), 'error' => $msg !== '' ? $msg : 'Twilio pricing request failed.'];
        }

        $json = $res->json();
        $unit = strtoupper((string) (data_get($json, 'price_unit') ?? 'USD'));
        $unit = $unit !== '' ? $unit : 'USD';

        $prices = [
            'Local' => null,
            'Mobile' => null,
            'TollFree' => null,
        ];

        $rows = data_get($json, 'phone_number_prices', []);
        if (! is_array($rows)) {
            $rows = [];
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $typeRaw = (string) (data_get($row, 'number_type') ?? '');
            $typeRaw = mb_strtolower(trim($typeRaw));
            $type = match ($typeRaw) {
                'local' => 'Local',
                'mobile' => 'Mobile',
                'toll free', 'tollfree', 'toll_free' => 'TollFree',
                default => null,
            };
            if (! $type) {
                continue;
            }

            $priceStr = (string) (data_get($row, 'current_price') ?? data_get($row, 'base_price') ?? '');
            $priceStr = trim($priceStr);
            if ($priceStr === '' || ! is_numeric($priceStr)) {
                continue;
            }

            $minor = (int) round(((float) $priceStr) * 100);
            if ($minor <= 0) {
                continue;
            }
            $prices[$type] = $minor;
        }

        $payload = ['ok' => true, 'currency' => $unit, 'prices_minor' => $prices];
        cache()->put($cacheKey, $payload, now()->addDays(3));

        return $payload;
    }

    public function formatMoney(int $amountMinor, string $currency): string
    {
        $currency = strtoupper(trim($currency));
        $value = number_format($amountMinor / 100, 2);
        if ($currency === 'USD') {
            return '$'.$value;
        }
        if ($currency === 'NGN') {
            return '₦'.$value;
        }

        return $currency.' '.$value;
    }

    public function purchaseNumber(string $phoneNumber, ?string $smsUrl, ?string $voiceUrl): array
    {
        $phoneNumber = trim($phoneNumber);
        if ($phoneNumber === '') {
            return ['ok' => false, 'error' => 'Missing phone number.'];
        }

        $payload = [
            'PhoneNumber' => $phoneNumber,
        ];

        if (is_string($smsUrl) && trim($smsUrl) !== '') {
            $payload['SmsUrl'] = $smsUrl;
            $payload['SmsMethod'] = 'POST';
        }

        if (is_string($voiceUrl) && trim($voiceUrl) !== '') {
            $payload['VoiceUrl'] = $voiceUrl;
            $payload['VoiceMethod'] = 'POST';
        }

        $resp = $this->post('/IncomingPhoneNumbers.json', $payload);
        if (! $resp['ok']) {
            return $resp;
        }

        $json = $resp['json'];

        return [
            'ok' => true,
            'sid' => (string) (data_get($json, 'sid') ?? ''),
            'phone_number' => (string) (data_get($json, 'phone_number') ?? $phoneNumber),
            'friendly_name' => (string) (data_get($json, 'friendly_name') ?? ''),
            'raw' => $json,
        ];
    }

    public function releaseNumber(string $incomingPhoneNumberSid): array
    {
        $incomingPhoneNumberSid = trim($incomingPhoneNumberSid);
        if ($incomingPhoneNumberSid === '') {
            return ['ok' => false, 'error' => 'Missing Twilio phone number SID.'];
        }

        $resp = $this->delete("/IncomingPhoneNumbers/{$incomingPhoneNumberSid}.json");
        if (! $resp['ok']) {
            return $resp;
        }

        return ['ok' => true];
    }

    public function sendMessage(string $from, string $to, string $body, array $mediaUrls = []): array
    {
        $from = trim($from);
        $to = trim($to);
        $body = (string) $body;
        if ($from === '' || $to === '') {
            return ['ok' => false, 'error' => 'Missing from/to number.'];
        }

        $payload = [
            'From' => $from,
            'To' => $to,
            'Body' => $body,
        ];

        $mediaUrls = array_values(array_filter(array_map(fn ($u) => trim((string) $u), $mediaUrls)));
        if (! empty($mediaUrls)) {
            $payload['MediaUrl'] = $mediaUrls;
        }

        $resp = $this->post('/Messages.json', $payload);
        if (! $resp['ok']) {
            return $resp;
        }

        $json = $resp['json'];

        return [
            'ok' => true,
            'sid' => (string) (data_get($json, 'sid') ?? ''),
            'status' => (string) (data_get($json, 'status') ?? ''),
            'raw' => $json,
        ];
    }

    public function fetchProtectedUrl(string $url): Response
    {
        $sid = (string) config('services.twilio.account_sid');
        $token = (string) config('services.twilio.auth_token');
        if (trim($sid) === '' || trim($token) === '') {
            throw new \RuntimeException('Twilio is not configured.');
        }

        return Http::withBasicAuth($sid, $token)
            ->timeout(30)
            ->get($url);
    }

    public function validateSignature(string $fullUrl, array $params, string $signature): bool
    {
        $authToken = (string) config('services.twilio.auth_token');
        if (trim($authToken) === '') {
            return false;
        }

        $signature = trim($signature);
        if ($signature === '') {
            return false;
        }

        ksort($params);
        $data = $fullUrl;
        foreach ($params as $k => $v) {
            $data .= (string) $k.(string) $v;
        }

        $computed = base64_encode(hash_hmac('sha1', $data, $authToken, true));

        return hash_equals($computed, $signature);
    }

    private function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    private function post(string $path, array $form = []): array
    {
        return $this->request('POST', $path, $form);
    }

    private function delete(string $path): array
    {
        return $this->request('DELETE', $path, []);
    }

    private function request(string $method, string $path, array $payload): array
    {
        $sid = (string) config('services.twilio.account_sid');
        $token = (string) config('services.twilio.auth_token');
        if (trim($sid) === '' || trim($token) === '') {
            return ['ok' => false, 'error' => 'Twilio is not configured.'];
        }

        $base = 'https://api.twilio.com/2010-04-01/Accounts/'.trim($sid);
        $client = Http::baseUrl($base)
            ->withBasicAuth($sid, $token)
            ->asForm()
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 100, function ($exception) {
                return $exception instanceof \Illuminate\Http\Client\ConnectionException;
            }, throw: false);

        try {
            /** @var Response $res */
            $res = match (strtoupper($method)) {
                'GET' => $client->get($path, $payload),
                'POST' => $client->post($path, $payload),
                'DELETE' => $client->delete($path, $payload),
                default => $client->send($method, $path, ['form_params' => $payload]),
            };
        } catch (\Throwable $e) {
            $errorMsg = 'A connection error occurred while communicating with Twilio.';
            if (app()->environment('local', 'testing')) {
                $errorMsg .= ' Error: '.$e->getMessage();
            }
            return ['ok' => false, 'error' => $errorMsg];
        }

        if (! $res->successful()) {
            $json = $res->json();
            $msg = is_array($json) ? (string) (data_get($json, 'message') ?? '') : '';
            $msg = trim($msg);

            if ($res->serverError()) {
                $msg = 'Twilio service is currently unavailable. Please try again later.';
            }

            return ['ok' => false, 'status' => $res->status(), 'error' => $msg !== '' ? $msg : 'Twilio request failed.'];
        }

        return ['ok' => true, 'json' => $res->json()];
    }
}
