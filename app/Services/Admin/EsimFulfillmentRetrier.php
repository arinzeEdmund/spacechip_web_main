<?php

namespace App\Services\Admin;

use App\Models\Payment;
use App\Services\AiraloService;
use RuntimeException;

/**
 * Re-attempts eSIM provisioning for a payment that was collected but never fulfilled.
 * Guarded so it can never provision twice for the same payment.
 */
class EsimFulfillmentRetrier
{
    private const ESIM_ASSETS = ['country', 'region', 'global'];

    public function __construct(private AiraloService $airalo) {}

    public function canRetry(Payment $payment): bool
    {
        $payload = (array) ($payment->fulfillment_payload ?? []);

        return in_array($payment->asset_type, self::ESIM_ASSETS, true)
            && in_array($payment->status, ['paid', 'paid_pending_fulfillment'], true)
            && empty($payload['esim_id']) && empty($payload['order_id']) && empty($payload['iccid']);
    }

    /** @return array{ok:bool, pending:bool, message:string} */
    public function retry(Payment $payment): array
    {
        if (! $this->canRetry($payment)) {
            throw new RuntimeException('This payment is not eligible for a fulfilment retry (already provisioned or not an eSIM order).');
        }

        $email = (string) ($payment->user?->email ?? '');
        $topupEsimId = (string) data_get($payment->provider_payload, 'wallet.topup_esim_id', '');
        $result = $topupEsimId !== ''
            ? $this->airalo->topupEsim($topupEsimId, (string) $payment->bundle_id, (string) $payment->provider_reference, $email)
            : $this->airalo->fulfillEsim((string) $payment->bundle_id, (string) $payment->provider_reference, $email, (string) $payment->package_type);

        $base = ['attempted_at' => now()->toIso8601String(), 'retried_by_admin' => true];

        if (($result['ok'] ?? false) === true) {
            $payment->status = 'fulfilled';
            $payment->fulfillment_payload = array_filter($base + [
                'ok' => true,
                'synced' => true,
                'esim_id' => $result['esim_id'] ?? null,
                'order_id' => $result['order_id'] ?? null,
                'iccid' => (string) ($result['iccid'] ?? ''),
                'activation_code' => (string) ($result['activation_code'] ?? ''),
                'lpa' => (string) ($result['lpa'] ?? $result['activation_code'] ?? ''),
                'smdp_address' => (string) ($result['smdp_address'] ?? ''),
                'qr_code_url' => (string) ($result['qr_code_url'] ?? ''),
                'airalo' => $result['raw'] ?? $result,
            ], fn ($v) => $v !== null);
            $payment->save();

            return ['ok' => true, 'pending' => false, 'message' => 'eSIM provisioned successfully.'];
        }

        $pending = (bool) ($result['pending'] ?? false);
        $payment->status = 'paid_pending_fulfillment';
        $payment->fulfillment_payload = array_filter($base + [
            'ok' => false,
            'pending' => $pending,
            'error' => (string) ($result['error'] ?? 'Provider returned no eSIM.'),
            'esim_id' => $result['esim_id'] ?? null,
            'order_id' => $result['order_id'] ?? null,
        ], fn ($v) => $v !== null);
        $payment->save();

        return ['ok' => false, 'pending' => $pending, 'message' => $pending ? 'Provider accepted the order; it is still processing.' : 'Provider error: '.($result['error'] ?? 'unknown')];
    }
}
