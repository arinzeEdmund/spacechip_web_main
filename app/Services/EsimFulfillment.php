<?php

namespace App\Services;

use App\Mail\NoticeMail;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Steps shared by every eSIM payment flow (Paystack, Cryptomus, wallet):
 * top-up eligibility, provisioning through Airalo, normalising the delivered eSIM,
 * and the delivery email.
 */
class EsimFulfillment
{
    /** Pending orders are re-polled at most this often. */
    public const RETRY_SECONDS = 5;

    public function __construct(
        private AiraloService $airalo,
        private QrCodeService $qr,
    ) {}

    /** Whether the user was delivered this eSIM and the provider marked it renewable. */
    public function canTopUp(int $userId, string $esimId): bool
    {
        return $this->deliveredEsims($userId)->contains(
            fn (array $fp) => trim((string) ($fp['esim_id'] ?? '')) === $esimId && (bool) ($fp['can_renew'] ?? false),
        );
    }

    /** Whether the user was delivered this eSIM (by provider id, or optionally ICCID). */
    public function owns(int $userId, string $esimId, bool $matchIccid = false): bool
    {
        return $this->deliveredEsims($userId)->contains(
            fn (array $fp) => trim((string) ($fp['esim_id'] ?? '')) === $esimId
                || ($matchIccid && trim((string) ($fp['iccid'] ?? '')) === $esimId),
        );
    }

    /** Buy a new eSIM, or top up `$topupEsimId` when given. */
    public function purchase(string $bundleId, string $reference, string $email, string $packageType, string $topupEsimId = ''): array
    {
        return $topupEsimId !== ''
            ? $this->airalo->topupEsim($topupEsimId, $bundleId, $reference, $email)
            : $this->airalo->fulfillEsim($bundleId, $reference, $email, $packageType);
    }

    /**
     * Provision a paid order recorded on `$payment` and store the outcome in its
     * `fulfillment_payload`. Safe to call repeatedly: an order the provider accepted but hasn't
     * delivered yet is re-checked rather than bought again. Emails the user once installable.
     */
    public function fulfilPayment(Payment $payment, string $email, string $reference): void
    {
        $previous = is_array($payment->fulfillment_payload) ? $payment->fulfillment_payload : [];
        $pendingEsimId = (string) ($previous['esim_id'] ?? '');
        $wasPending = (bool) ($previous['pending'] ?? false);
        $topupEsimId = trim((string) data_get($payment->provider_payload, 'topup.esim_id', ''));

        if ($topupEsimId !== '' && ! $this->owns((int) $payment->user_id, $topupEsimId)) {
            return;
        }

        $result = $wasPending && $pendingEsimId !== ''
            ? $this->airalo->getEsimDetails($pendingEsimId)
            : $this->purchase((string) $payment->bundle_id, $reference, $email, (string) $payment->package_type, $topupEsimId);

        if (($result['ok'] ?? false) !== true) {
            $payment->fulfillment_payload = array_filter([
                'ok' => false,
                'pending' => (bool) ($result['pending'] ?? $wasPending),
                'attempted_at' => now()->toIso8601String(),
                'error' => (string) ($result['error'] ?? ''),
                'topup_esim_id' => $topupEsimId ?: null,
                'esim_id' => $result['esim_id'] ?? ($pendingEsimId ?: null),
                'order_id' => $result['order_id'] ?? $previous['order_id'] ?? null,
            ], fn ($v) => $v !== null);
            $payment->save();

            return;
        }

        $esim = $this->resolve($result);
        $payment->fulfillment_payload = array_filter([
            'ok' => $esim['synced'],
            'pending' => ! $esim['synced'],
            'synced' => $esim['synced'],
            'attempted_at' => now()->toIso8601String(),
            'order_id' => (string) ($result['order_id'] ?? $previous['order_id'] ?? ''),
            'topup_esim_id' => $topupEsimId ?: null,
            ...array_diff_key($esim, array_flip(['qr_payload', 'synced'])),
        ], fn ($v) => $v !== null && $v !== '');
        $payment->save();

        if ($esim['synced']) {
            $this->sendReadyEmail($email, $esim);
        }
    }

    /**
     * Normalise a successful purchase. A follow-up details lookup is preferred (it has the
     * final activation data); any field it lacks falls back to the purchase response.
     *
     * `synced` means the eSIM is installable: it has an ICCID and a scannable QR payload.
     */
    public function resolve(array $purchase): array
    {
        $esimId = trim((string) ($purchase['esim_id'] ?? ''));
        $details = $esimId !== '' ? $this->airalo->getEsimDetails($esimId) : null;

        return $this->normalize(($details['ok'] ?? false) === true ? $details : [], $purchase);
    }

    /** Flatten a provider result into the stored eSIM shape; empty fields fall back to `$fallback`. */
    public function normalize(array $primary, array $fallback = []): array
    {
        $field = fn (string $key) => trim((string) (($primary[$key] ?? '') ?: ($fallback[$key] ?? '')));

        $esim = [
            'esim_id' => $field('esim_id'),
            'iccid' => $field('iccid'),
            'activation_code' => $field('activation_code'),
            'lpa' => $field('lpa'),
            'qr_code_url' => $field('qr_code_url'),
            'smdp_address' => $field('smdp_address'),
            'esim_status' => $field('esim_status'),
            'puk_code' => $field('puk_code'),
            'number' => $primary['number'] ?? $fallback['number'] ?? null,
            'direct_installation_link_ios' => $field('direct_installation_link_ios'),
            'direct_installation_link_android' => $field('direct_installation_link_android'),
            'airalo' => $primary ? ($primary['raw'] ?? $primary) : ($fallback['raw'] ?? $fallback),
        ];

        $esim['qr_payload'] = $this->qrPayload($esim);
        $esim['synced'] = $esim['iccid'] !== '' && $esim['qr_payload'] !== '';

        return $esim;
    }

    /** LPA string for the install QR, from the LPA or the activation code + SM-DP+ address. */
    public function qrPayload(array $esim): string
    {
        $lpa = trim((string) ($esim['lpa'] ?? ''));

        return $this->qr->esimQrPayload($lpa !== '' ? $lpa : (string) ($esim['activation_code'] ?? ''), (string) ($esim['smdp_address'] ?? ''));
    }

    public function qrDataUrl(string $qrPayload): string
    {
        return $qrPayload !== '' ? $this->qr->svgDataUrl($qrPayload) : '';
    }

    /** Email the install details and QR (inline + SVG attachment). Mail failures never block delivery. */
    public function sendReadyEmail(string $email, array $esim, ?array $bundle = null): void
    {
        $qrPayload = (string) ($esim['qr_payload'] ?? $this->qrPayload($esim));
        $svg = $qrPayload !== '' ? $this->qr->svgString($qrPayload) : '';

        try {
            Mail::to($email)->send(new NoticeMail(
                subjectLine: 'Your Spacechip eSIM is ready',
                kicker: 'Your eSIM delivery details',
                heading: $bundle['name'] ?? null,
                rows: [
                    'ICCID' => $esim['iccid'] ?? null,
                    'Activation Code' => $esim['activation_code'] ?? null,
                    'SM-DP+ Address' => $esim['smdp_address'] ?? null,
                    'LPA' => $esim['lpa'] ?? null,
                ],
                chips: ['Data' => $bundle['data'] ?? null, 'Validity' => $bundle['validity'] ?? null],
                links: ['QR Code' => $esim['qr_code_url'] ?? null],
                imageDataUrl: $this->qrDataUrl($qrPayload) ?: null,
                footnote: 'Open your phone settings → Cellular/Mobile Data → Add eSIM, then scan the QR code (or enter the activation code manually).',
                files: $svg !== '' ? [['data' => $svg, 'name' => 'esim-qr.svg', 'mime' => 'image/svg+xml']] : [],
            ));
        } catch (Throwable) {
            // Delivery is already recorded; the user can still see the eSIM in their dashboard.
        }
    }

    /** True once enough time has passed since the last provisioning attempt to try again. */
    public static function retryDue(?string $attemptedAt): bool
    {
        $ts = $attemptedAt ? strtotime($attemptedAt) : false;

        return $ts === false || $ts <= now()->subSeconds(self::RETRY_SECONDS)->getTimestamp();
    }

    /** Fulfilment payloads of the user's most recent orders. */
    private function deliveredEsims(int $userId): Collection
    {
        return Payment::query()
            ->where('user_id', $userId)
            ->whereNotNull('fulfillment_payload')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (Payment $p) => is_array($p->fulfillment_payload) ? $p->fulfillment_payload : []);
    }
}
