<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\AiraloService;
use App\Services\EsimFulfillment;
use App\Services\ExchangeRateService;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Card payments for eSIMs via Paystack.
 *
 * initialize → (Paystack popup) → verify → [status polls while the eSIM is pending].
 * The latest result per reference is cached (and mirrored on the Payment row) so repeat
 * calls never provision twice; a cache lock serialises concurrent verifies.
 */
class PaystackController extends Controller
{
    private const PREPARING = 'Payment confirmed. Preparing your eSIM…';

    public function __construct(
        private PaystackService $paystack,
        private EsimFulfillment $esims,
    ) {}

    public function initialize(AiraloService $airalo, ExchangeRateService $exchangeRates): JsonResponse
    {
        $type = request('type');
        $id = request('id');
        $bundleId = request('bundle');
        $packageType = request('package_type', 'DATA-ONLY');
        $topupEsimId = trim((string) request('topup_esim_id', ''));
        $currency = strtoupper((string) request('currency', 'NGN'));

        if (! in_array($currency, ['NGN', 'USD'], true)) {
            return $this->error('Unsupported currency.', 422);
        }
        if (! $type || ! $id || ! $bundleId) {
            return $this->error('Missing checkout parameters.', 422);
        }

        $asset = $airalo->getAssetDetails((string) $type, (string) $id, (string) $packageType);
        if (! $asset) {
            return $this->error('Asset not found.', 404);
        }

        $bundle = collect($asset['bundles'] ?? [])->first(fn ($b) => (string) ($b['id'] ?? '') === (string) $bundleId);
        if (! $bundle) {
            return $this->error('Bundle not found.', 404);
        }

        $price = $bundle['price'] ?? null;
        if (! is_numeric($price) || (float) $price <= 0) {
            return $this->error('Invalid amount.', 422);
        }

        // Catalogue prices are USD; NGN checkouts convert at the live rate.
        $rate = null;
        $rateSource = null;
        $amountNgn = null;
        if ($currency === 'USD') {
            $amountMinor = (int) round((float) $price * 100);
        } else {
            $quote = $exchangeRates->usdToNgn();
            $rate = (float) ($quote['rate'] ?? 0);
            $rateSource = (string) ($quote['source'] ?? 'unavailable');
            if ($rate <= 0) {
                return $this->error('Unable to fetch USD to NGN exchange rate. Set ESIM_USD_TO_NGN_RATE as a fallback or try again shortly.', 422);
            }
            $amountNgn = (float) $price * $rate;
            $amountMinor = (int) round($amountNgn * 100);
        }

        if ($amountMinor <= 0) {
            return $this->error('Invalid amount.', 422);
        }

        $email = (string) (Auth::user()?->email ?? '');
        if (trim($email) === '') {
            return $this->error('Missing user email.', 422);
        }

        if ($topupEsimId !== '' && ! $this->esims->canTopUp((int) Auth::id(), $topupEsimId)) {
            return $this->error('This eSIM cannot be renewed.', 422);
        }

        $reference = (string) Str::uuid();
        $this->record($reference, [
            'status' => 'initialized',
            'asset_type' => (string) $type,
            'asset_id' => (string) $id,
            'bundle_id' => (string) $bundleId,
            'package_type' => (string) $packageType,
            'currency' => $currency,
            'amount_minor' => $amountMinor,
            'provider_payload' => [
                'asset' => [
                    'name' => (string) ($asset['name'] ?? ''),
                    'code' => (string) ($asset['code'] ?? ''),
                    'flag' => (string) ($asset['flag'] ?? ''),
                    'flag_url' => (string) ($asset['flag_url'] ?? ''),
                ],
                'bundle' => [
                    'name' => (string) ($bundle['name'] ?? ''),
                    'data' => (string) ($bundle['data'] ?? ''),
                    'validity' => (string) ($bundle['validity'] ?? ''),
                    'price' => (float) $price,
                    'price_formatted' => (string) ($bundle['price_formatted'] ?? ''),
                ],
                'topup' => $topupEsimId !== '' ? ['esim_id' => $topupEsimId] : null,
            ],
        ]);

        if (! $this->paystack->isConfigured()) {
            return $this->error('Paystack secret key is not configured.', 500);
        }

        try {
            $response = $this->paystack->initialize([
                'email' => $email,
                'amount' => $amountMinor,
                'currency' => $currency,
                'reference' => $reference,
                'metadata' => [
                    'type' => (string) $type,
                    'id' => (string) $id,
                    'bundle' => (string) $bundleId,
                    'package_type' => (string) $packageType,
                    'topup_esim_id' => $topupEsimId !== '' ? $topupEsimId : null,
                ],
            ]);
        } catch (Throwable) {
            return $this->error('Failed to initialize Paystack transaction.', 502);
        }

        if (! $response->successful()) {
            return $this->error('Failed to initialize Paystack transaction.', 502);
        }

        $accessCode = data_get($response->json(), 'data.access_code');
        if (! is_string($accessCode) || $accessCode === '') {
            return $this->error('Paystack did not return an access code.', 502);
        }

        return response()->json([
            'reference' => $reference,
            'access_code' => $accessCode,
            'amount' => $amountMinor,
            'currency' => $currency,
            'email' => $email,
            'conversion' => [
                'usd_price' => (float) $price,
                'usd_to_ngn_rate' => (float) $rate,
                'rate_source' => $rateSource,
                'ngn_amount_raw' => $amountNgn,
                'ngn_amount_adjusted' => $amountNgn,
                'ngn_amount_minor' => $amountMinor,
                'currency' => $currency,
            ],
        ]);
    }

    /** Confirm the charge with Paystack, then provision the eSIM exactly once. */
    public function verify(AiraloService $airalo): JsonResponse
    {
        $reference = (string) request('reference', '');
        if (trim($reference) === '') {
            return $this->error('Missing reference.', 422);
        }

        $payment = $this->ownedPayment($reference);
        if (! $payment) {
            return $this->notFound();
        }

        $cached = Cache::get($this->cacheKey($reference));
        if (is_array($cached) && ($cached['ok'] ?? false) === true) {
            return response()->json($cached);
        }

        if (($payment->fulfillment_payload['ok'] ?? false) === true) {
            $result = ['ok' => true, 'status' => $payment->status, 'reference' => $payment->provider_reference, 'fulfillment' => $payment->fulfillment_payload];
            Cache::put($this->cacheKey($reference), $result, now()->addHours(6));

            return response()->json($result);
        }

        $lock = Cache::lock('fulfillment_lock_'.sha1($reference), 60);
        if (! $lock->get()) {
            return $this->error('Fulfillment in progress, please wait.', 429);
        }

        try {
            if (! $this->paystack->isConfigured()) {
                return $this->error('Paystack secret key is not configured.', 500);
            }

            try {
                $response = $this->paystack->verify($reference);
            } catch (Throwable) {
                return $this->error('Failed to verify Paystack transaction.', 502);
            }

            if (! $response->successful()) {
                return $this->error('Failed to verify Paystack transaction.', 502);
            }

            $data = data_get($response->json(), 'data');
            $tx = is_array($data) ? $data : [];
            $status = $tx['status'] ?? null;
            if ($status !== 'success') {
                return response()->json(['ok' => false, 'status' => $status, 'reference' => $tx['reference'] ?? null, 'data' => $data]);
            }

            // The charge must match this order exactly: payer, currency, amount and checkout metadata.
            if (! $this->matchesPayment($tx, $payment)) {
                return $this->notFound();
            }

            $email = (string) (Auth::user()?->email ?? '');
            if (trim($email) === '') {
                return $this->error('Missing user email.', 422);
            }

            $type = (string) $payment->asset_type;
            $id = (string) $payment->asset_id;
            $bundleId = (string) $payment->bundle_id;
            $packageType = (string) $payment->package_type;
            $order = compact('type', 'id') + ['bundle' => $bundleId, 'package_type' => $packageType];

            $asset = $airalo->getAssetDetails($type, $id, $packageType);
            $bundle = collect($asset['bundles'] ?? [])->first(fn ($b) => (string) ($b['id'] ?? '') === $bundleId);

            $topupEsimId = trim((string) (data_get($payment->provider_payload, 'topup.esim_id') ?? data_get($tx, 'metadata.topup_esim_id') ?? ''));
            if ($topupEsimId !== '' && ! $this->esims->owns((int) Auth::id(), $topupEsimId, matchIccid: true)) {
                return $this->notFound();
            }

            $paid = [
                'currency' => (string) ($tx['currency'] ?? 'NGN'),
                'amount_minor' => (int) ($tx['amount'] ?? 0),
                'asset_type' => $type,
                'asset_id' => $id,
                'bundle_id' => $bundleId,
                'package_type' => $packageType,
            ];
            $envelope = ['ok' => true, 'status' => $status, 'reference' => $tx['reference'] ?? null, 'data' => $tx];
            $purchase = $this->esims->purchase($bundleId, $reference, $email, $packageType, $topupEsimId);

            if (($purchase['ok'] ?? false) !== true) {
                if (! ($purchase['pending'] ?? false)) {
                    $error = (string) ($purchase['error'] ?? '');

                    return response()->json($envelope + ['fulfillment' => [
                        'ok' => false,
                        'message' => $error !== ''
                            ? 'Payment confirmed, but eSIM fulfillment failed: '.$error
                            : 'Payment confirmed, but eSIM fulfillment failed. Please try again shortly or contact support.',
                    ]]);
                }

                // Ordered but not yet delivered: status polls will finish the job.
                $result = $envelope + ['fulfillment' => [
                    'ok' => false,
                    'pending' => true,
                    'attempted_at' => now()->toIso8601String(),
                    ...$order,
                    'topup_esim_id' => $topupEsimId ?: null,
                    'esim_id' => $purchase['esim_id'] ?? null,
                    'order_id' => $purchase['order_id'] ?? null,
                    'message' => self::PREPARING,
                ]];
                $this->rememberResult($reference, $result, now()->addHours(6));
                $this->record($reference, $paid + [
                    'status' => 'paid_pending_fulfillment',
                    'fulfillment_payload' => array_filter([
                        'ok' => false,
                        'pending' => true,
                        'attempted_at' => now()->toIso8601String(),
                        'error' => (string) ($purchase['error'] ?? ''),
                        'topup_esim_id' => $topupEsimId ?: null,
                        'esim_id' => $purchase['esim_id'] ?? null,
                        'order_id' => $purchase['order_id'] ?? null,
                        'activation_code' => (string) ($purchase['activation_code'] ?? ''),
                        'iccid' => (string) ($purchase['iccid'] ?? ''),
                        'qr_code_url' => (string) ($purchase['qr_code_url'] ?? ''),
                        'smdp_address' => (string) ($purchase['smdp_address'] ?? ''),
                    ], fn ($v) => $v !== null),
                ]);

                return response()->json($result);
            }

            $esim = $this->esims->resolve($purchase);

            if (! $esim['synced']) {
                $result = $envelope + ['fulfillment' => [
                    'ok' => false,
                    'pending' => true,
                    'synced' => false,
                    'attempted_at' => now()->toIso8601String(),
                    ...$order,
                    'esim_id' => $esim['esim_id'] ?: null,
                    'iccid' => $esim['iccid'] ?: null,
                    'message' => self::PREPARING,
                ]];
                $this->rememberResult($reference, $result, now()->addHours(6));
                $this->record($reference, $paid + [
                    'status' => 'paid_pending_fulfillment',
                    'fulfillment_payload' => array_filter([
                        'ok' => false,
                        'pending' => true,
                        'synced' => false,
                        'attempted_at' => now()->toIso8601String(),
                        ...array_diff_key($esim, array_flip(['qr_payload', 'synced'])),
                    ], fn ($v) => $v !== null && $v !== ''),
                ]);

                return response()->json($result);
            }

            $this->esims->sendReadyEmail($email, $esim, $bundle);

            $result = $envelope + ['fulfillment' => $this->deliveredPayload($order, $topupEsimId, (string) ($purchase['order_id'] ?? ''), $esim)];
            $this->rememberResult($reference, $result, now()->addDays(2));
            $this->record($reference, $paid + [
                'status' => 'fulfilled',
                'fulfillment_payload' => ['ok' => true, 'attempted_at' => now()->toIso8601String(), ...$result['fulfillment']],
            ]);

            return response()->json($result);
        } finally {
            $lock->release();
        }
    }

    /** Read-only view of the latest result (no provisioning). */
    public function showStatus(): JsonResponse
    {
        $reference = (string) request('reference', '');
        if (trim($reference) === '') {
            return $this->error('Missing reference.', 422);
        }

        $payment = $this->ownedPayment($reference);
        if (! $payment) {
            return $this->notFound();
        }

        $cached = Cache::get($this->cacheKey($reference));
        if (is_array($cached)) {
            return response()->json($cached);
        }

        return response()->json([
            'ok' => true,
            'status' => (string) ($payment->status ?? ''),
            'reference' => $reference,
            'data' => null,
            'fulfillment' => is_array($payment->fulfillment_payload) ? $payment->fulfillment_payload : null,
        ]);
    }

    /** Poll a pending order: re-check the eSIM (or retry the purchase) at most every few seconds. */
    public function status(AiraloService $airalo): JsonResponse
    {
        $reference = (string) request('reference', '');
        if (trim($reference) === '') {
            return $this->error('Missing reference.', 422);
        }

        if (! $this->ownedPayment($reference)) {
            return $this->notFound();
        }

        $result = Cache::get($this->cacheKey($reference));
        if (! is_array($result)) {
            return $this->notFound();
        }

        $meta = (array) data_get($result, 'fulfillment', []);
        $done = ($result['ok'] ?? false) === true && ($meta['ok'] ?? null) === true && ($meta['synced'] ?? null) === true;
        if ($done || ! EsimFulfillment::retryDue($meta['attempted_at'] ?? null)) {
            return response()->json($result);
        }

        $email = (string) (Auth::user()?->email ?? '');
        if (trim($email) === '') {
            return $this->error('Missing user email.', 422);
        }

        $order = [
            'type' => (string) ($meta['type'] ?? ''),
            'id' => (string) ($meta['id'] ?? ''),
            'bundle' => (string) ($meta['bundle'] ?? ''),
            'package_type' => (string) ($meta['package_type'] ?? 'DATA-ONLY'),
        ];
        $pendingEsimId = trim((string) ($meta['esim_id'] ?? ''));
        $topupEsimId = trim((string) ($meta['topup_esim_id'] ?? ''));

        // Known eSIM → just look it up; otherwise the purchase itself never went through, so retry it.
        $attempt = match (true) {
            $pendingEsimId !== '' => $airalo->getEsimDetails($pendingEsimId),
            $order['bundle'] !== '' => $this->esims->purchase($order['bundle'], $reference, $email, $order['package_type'], $topupEsimId),
            default => ['ok' => false, 'error' => 'Missing bundle id.'],
        };

        $esim = $this->esims->normalize($attempt, ['esim_id' => $pendingEsimId, 'iccid' => trim((string) ($meta['iccid'] ?? ''))]);

        if (! $esim['synced'] || ($attempt['ok'] ?? false) !== true) {
            $result['fulfillment'] = array_filter([
                ...$meta,
                'ok' => false,
                'pending' => true,
                'synced' => false,
                'attempted_at' => now()->toIso8601String(),
                'error' => (string) ($attempt['error'] ?? ''),
                'esim_id' => $esim['esim_id'],
                'iccid' => $esim['iccid'] ?: null,
                'message' => self::PREPARING,
            ], fn ($v) => $v !== null);
            $this->rememberResult($reference, $result, now()->addHours(6));

            return response()->json($result);
        }

        $this->esims->sendReadyEmail($email, $esim);

        $result['ok'] = true;
        $result['fulfillment'] = $this->deliveredPayload($order, $topupEsimId, null, $esim);
        $this->rememberResult($reference, $result, now()->addDays(2));
        $this->record($reference, [
            'status' => 'fulfilled',
            'currency' => (string) (data_get($result, 'data.currency') ?? 'NGN'),
            'amount_minor' => (int) (data_get($result, 'data.amount') ?? 0),
            'asset_type' => $order['type'],
            'asset_id' => $order['id'],
            'bundle_id' => $order['bundle'],
            'package_type' => $order['package_type'],
            'fulfillment_payload' => ['ok' => true, 'attempted_at' => now()->toIso8601String(), ...$result['fulfillment']],
        ]);

        return response()->json($result);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    /** Response shape of a delivered eSIM (also stored as the payment's fulfilment payload). */
    private function deliveredPayload(array $order, string $topupEsimId, ?string $orderId, array $esim): array
    {
        return [
            'ok' => true,
            'synced' => true,
            ...$order,
            'topup_esim_id' => $topupEsimId ?: null,
            'esim_id' => $esim['esim_id'],
            ...($orderId !== null ? ['order_id' => $orderId] : []),
            'iccid' => $esim['iccid'],
            'activation_code' => $esim['activation_code'],
            'lpa' => $esim['lpa'],
            'puk_code' => $esim['puk_code'],
            'number' => is_scalar($esim['number']) ? (string) $esim['number'] : '',
            'esim_status' => $esim['esim_status'],
            'direct_installation_link_ios' => $esim['direct_installation_link_ios'],
            'direct_installation_link_android' => $esim['direct_installation_link_android'],
            'qr_code_url' => $esim['qr_code_url'],
            'qr_payload' => $esim['qr_payload'],
            'qr_code_data_url' => $this->esims->qrDataUrl($esim['qr_payload']),
            'smdp_address' => $esim['smdp_address'],
            'airalo' => $esim['airalo'],
        ];
    }

    private function matchesPayment(array $tx, Payment $payment): bool
    {
        $payer = trim(mb_strtolower((string) data_get($tx, 'customer.email', '')));
        $user = trim(mb_strtolower((string) (Auth::user()?->email ?? '')));
        if ($payer === '' || $payer !== $user) {
            return false;
        }

        $currency = (string) ($tx['currency'] ?? '');
        $amount = (int) ($tx['amount'] ?? 0);
        if ($currency === '' || $amount <= 0 || strtoupper($currency) !== strtoupper((string) $payment->currency) || $amount !== (int) $payment->amount_minor) {
            return false;
        }

        // Metadata is optional, but when present it must describe this order.
        $meta = is_array($tx['metadata'] ?? null) ? $tx['metadata'] : [];
        foreach (['type' => 'asset_type', 'id' => 'asset_id', 'bundle' => 'bundle_id', 'package_type' => 'package_type'] as $key => $column) {
            $value = (string) ($meta[$key] ?? '');
            if ($value !== '' && $value !== (string) $payment->{$column}) {
                return false;
            }
        }

        return true;
    }

    private function ownedPayment(string $reference): ?Payment
    {
        $payment = Payment::where('provider', 'paystack')->where('provider_reference', $reference)->first();

        return $payment && (int) $payment->user_id === (int) Auth::id() ? $payment : null;
    }

    private function record(string $reference, array $attributes): void
    {
        Payment::updateOrCreate(
            ['provider' => 'paystack', 'provider_reference' => $reference],
            ['user_id' => Auth::id()] + $attributes,
        );
    }

    private function rememberResult(string $reference, array $result, \DateTimeInterface $ttl): void
    {
        Cache::put($this->cacheKey($reference), $result, $ttl);
    }

    private function cacheKey(string $reference): string
    {
        return 'paystack.fulfillment.v1.'.sha1($reference);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    /** Ownership and integrity failures all look like a missing payment, so nothing leaks. */
    private function notFound(): JsonResponse
    {
        return $this->error('Payment not found.', 404);
    }
}
