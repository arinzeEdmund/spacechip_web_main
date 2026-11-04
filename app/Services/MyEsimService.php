<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * Builds the "My eSIMs" list from fulfilled card/crypto payments.
 *
 * Expiry is estimated from the bundle's validity ("7 days" → purchase + 7d) because providers
 * don't report it consistently. Repeat purchases for the same ICCID (top-ups) collapse to the
 * most recent one.
 */
class MyEsimService
{
    public function __construct(private QrCodeService $qr) {}

    public function listForUser(int $userId, string $filter, int $page, int $perPage): array
    {
        $filter = in_array($filter, ['valid', 'expired'], true) ? $filter : 'valid';
        $page = max(1, $page);
        $perPage = max(5, min(30, $perPage));

        $payments = Payment::query()
            ->where('user_id', $userId)
            ->whereIn('provider', ['paystack', 'cryptomus'])
            ->whereNotNull('fulfillment_payload')
            ->orderByDesc('created_at')
            ->get();

        $items = [];
        foreach ($payments as $payment) {
            $item = $this->present($payment);
            if ($item && $item['status'] === $filter) {
                $items[] = $item;
            }
        }

        $items = $this->dedupe($items);
        $total = count($items);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($items, $offset, $perPage);

        return [
            'filter' => $filter,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'has_more' => ($offset + count($slice)) < $total,
            'items' => $slice,
        ];
    }

    /** One list row, or null when the payment hasn't produced an installable eSIM. */
    private function present(Payment $p): ?array
    {
        $f = is_array($p->fulfillment_payload) ? $p->fulfillment_payload : [];
        $meta = is_array($p->provider_payload) ? $p->provider_payload : [];
        $bundle = (array) (data_get($meta, 'bundle') ?: ($f['bundle_details'] ?? []));

        // Raw provider payloads, for records stored before fields were flattened.
        $glo = is_array($f['gloesim'] ?? null) ? $f['gloesim'] : [];
        $airalo = is_array($f['airalo'] ?? null) ? $f['airalo'] : [];
        $isAiralo = isset($f['airalo']) || ! isset($f['gloesim']);

        $iccid = (string) ($f['iccid'] ?? data_get($airalo, 'sims.0.iccid') ?? data_get($airalo, 'iccid') ?? data_get($glo, 'iccid') ?? '');
        $fulfilled = (bool) ($f['ok'] ?? false) || $p->status === 'fulfilled';
        if (trim($iccid) === '' || ! $fulfilled) {
            return null;
        }

        $validity = (string) ($bundle['validity'] ?? '');
        $days = preg_match('/(\d+)/', $validity, $m) && (int) $m[1] > 0 ? (int) $m[1] : null;
        $purchasedAt = $p->created_at?->copy() ?? Carbon::now();
        $expiresAt = $days ? $purchasedAt->copy()->addDays($days) : null;

        $lpa = (string) ($f['lpa'] ?? data_get($glo, 'qr_code_text') ?? '');
        $activationCode = (string) ($f['activation_code'] ?? '');
        $smdp = (string) ($f['smdp_address'] ?? data_get($glo, 'smdp_address') ?? '');
        $qrPayload = $this->qr->esimQrPayload($lpa !== '' ? $lpa : $activationCode, $smdp);

        return [
            'id' => (int) $p->id,
            'provider' => (string) $p->provider,
            'reference' => (string) $p->provider_reference,
            'asset_type' => (string) $p->asset_type,
            'asset_id' => (string) $p->asset_id,
            'bundle_id' => (string) $p->bundle_id,
            'package_type' => (string) $p->package_type,
            'purchased_at' => $purchasedAt->toIso8601String(),
            'expires_at' => $expiresAt?->toIso8601String(),
            'status' => $expiresAt?->isPast() ? 'expired' : 'valid',
            'asset' => [
                'name' => (string) data_get($meta, 'asset.name', ''),
                'code' => (string) data_get($meta, 'asset.code', ''),
                'flag' => (string) data_get($meta, 'asset.flag', ''),
                'flag_url' => (string) data_get($meta, 'asset.flag_url', ''),
            ],
            'bundle' => [
                'name' => (string) ($bundle['name'] ?? ''),
                'data' => (string) ($bundle['data'] ?? ''),
                'validity' => $validity,
                'price_formatted' => (string) ($bundle['price_formatted'] ?? ''),
            ],
            'esim' => [
                'iccid' => $iccid,
                'esim_id' => (string) ($f['esim_id'] ?? ''),
                'activation_code' => $activationCode,
                'lpa' => $lpa,
                // Airalo SIMs are always renewable; GloEsim reports it per SIM.
                'can_renew' => (bool) ($f['can_renew'] ?? ($isAiralo ?: (bool) data_get($glo, 'can_renew'))),
                'puk_code' => (string) ($f['puk_code'] ?? ''),
                'number' => (string) ($f['number'] ?? ''),
                'esim_status' => (string) ($f['esim_status'] ?? ''),
                'direct_installation_link_ios' => (string) ($f['direct_installation_link_ios'] ?? ''),
                'direct_installation_link_android' => (string) ($f['direct_installation_link_android'] ?? ''),
                'qr_code_url' => (string) ($f['qr_code_url'] ?? ''),
                'qr_payload' => $qrPayload,
                'smdp_address' => $smdp,
                'qr_code_data_url' => $qrPayload !== '' ? $this->qr->svgDataUrl($qrPayload) : '',
            ],
        ];
    }

    /** Keep the newest row per ICCID (falling back to bundle, reference, then payment id). */
    private function dedupe(array $items): array
    {
        $seen = [];

        return array_values(array_filter($items, function (array $it) use (&$seen) {
            $iccid = trim($it['esim']['iccid']);
            $bundleId = trim($it['bundle_id']);
            $key = match (true) {
                $iccid !== '' => 'iccid:'.$iccid,
                $bundleId !== '' => 'bundle:'.$bundleId,
                $it['reference'] !== '' => 'ref:'.$it['provider'].':'.$it['reference'],
                default => 'payment:'.$it['id'],
            };

            return ! isset($seen[$key]) && ($seen[$key] = true);
        }));
    }
}
