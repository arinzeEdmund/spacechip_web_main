<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Carbon;

class MyEsimService
{
    public function listForUser(int $userId, string $filter, int $page, int $perPage, QrCodeService $qr): array
    {
        $filter = in_array($filter, ['valid', 'expired'], true) ? $filter : 'valid';
        $page = max(1, $page);
        $perPage = max(5, min(30, $perPage));

        $rows = Payment::query()
            ->where('user_id', $userId)
            ->whereIn('provider', ['paystack', 'cryptomus'])
            ->whereNotNull('fulfillment_payload')
            ->orderByDesc('created_at')
            ->get();

        $items = [];

        foreach ($rows as $p) {
            $fulfillment = is_array($p->fulfillment_payload) ? $p->fulfillment_payload : [];
            $providerPayload = is_array($p->provider_payload) ? $p->provider_payload : [];
            $bundle = is_array(data_get($providerPayload, 'bundle')) ? (array) data_get($providerPayload, 'bundle') : [];

            if (empty($bundle) && is_array($fulfillment['bundle_details'] ?? null)) {
                $bundle = (array) $fulfillment['bundle_details'];
            }

            $validityStr = (string) ($bundle['validity'] ?? data_get($providerPayload, 'bundle.validity', ''));
            $days = $this->parseDays($validityStr);
            $purchasedAt = $p->created_at ? $p->created_at->copy() : Carbon::now();
            $expiresAt = $days ? $purchasedAt->copy()->addDays($days) : null;

            $isExpired = $expiresAt ? $expiresAt->isPast() : false;
            if ($filter === 'expired' && ! $isExpired) {
                continue;
            }
            if ($filter === 'valid' && $isExpired) {
                continue;
            }

            $glo = is_array($fulfillment['gloesim'] ?? null) ? $fulfillment['gloesim'] : [];
            $airalo = is_array($fulfillment['airalo'] ?? null) ? $fulfillment['airalo'] : [];
            $isAiralo = isset($fulfillment['airalo']) || !isset($fulfillment['gloesim']);

            $lpaValue = (string) ($fulfillment['lpa'] ?? (data_get($glo, 'qr_code_text') ?? ''));
            $activationValue = (string) ($fulfillment['activation_code'] ?? '');
            $smdpValue = (string) ($fulfillment['smdp_address'] ?? (data_get($glo, 'smdp_address') ?? ''));
            $qrPayload = $qr->esimQrPayload($lpaValue !== '' ? $lpaValue : $activationValue, $smdpValue);
            $iccidValue = (string) ($fulfillment['iccid'] ?? (data_get($airalo, 'sims.0.iccid') ?? (data_get($airalo, 'iccid') ?? (data_get($glo, 'iccid') ?? ''))));
            $canRenew = (bool) ($fulfillment['can_renew'] ?? ($isAiralo ? true : (bool) data_get($glo, 'can_renew')));
            $isFulfilled = (bool) ($fulfillment['ok'] ?? false) || (string) $p->status === 'fulfilled';
            if (trim($iccidValue) === '' || ! $isFulfilled) {
                continue;
            }

            $items[] = [
                'id' => (int) $p->id,
                'provider' => (string) $p->provider,
                'reference' => (string) $p->provider_reference,
                'asset_type' => (string) $p->asset_type,
                'asset_id' => (string) $p->asset_id,
                'bundle_id' => (string) $p->bundle_id,
                'package_type' => (string) $p->package_type,
                'purchased_at' => $purchasedAt->toIso8601String(),
                'expires_at' => $expiresAt ? $expiresAt->toIso8601String() : null,
                'status' => $isExpired ? 'expired' : 'valid',
                'asset' => [
                    'name' => (string) data_get($providerPayload, 'asset.name', ''),
                    'code' => (string) data_get($providerPayload, 'asset.code', ''),
                    'flag' => (string) data_get($providerPayload, 'asset.flag', ''),
                    'flag_url' => (string) data_get($providerPayload, 'asset.flag_url', ''),
                ],
                'bundle' => [
                    'name' => (string) ($bundle['name'] ?? ''),
                    'data' => (string) ($bundle['data'] ?? ''),
                    'validity' => $validityStr,
                    'price_formatted' => (string) ($bundle['price_formatted'] ?? ''),
                ],
                'esim' => [
                    'iccid' => $iccidValue,
                    'esim_id' => (string) ($fulfillment['esim_id'] ?? ''),
                    'activation_code' => $activationValue,
                    'lpa' => $lpaValue,
                    'can_renew' => $canRenew,
                    'puk_code' => (string) ($fulfillment['puk_code'] ?? ''),
                    'number' => (string) ($fulfillment['number'] ?? ''),
                    'esim_status' => (string) ($fulfillment['esim_status'] ?? ''),
                    'direct_installation_link_ios' => (string) ($fulfillment['direct_installation_link_ios'] ?? ''),
                    'direct_installation_link_android' => (string) ($fulfillment['direct_installation_link_android'] ?? ''),
                    'qr_code_url' => (string) ($fulfillment['qr_code_url'] ?? ''),
                    'qr_payload' => $qrPayload,
                    'smdp_address' => $smdpValue,
                    'qr_code_data_url' => $qrPayload !== '' ? $qr->svgDataUrl($qrPayload) : '',
                ],
            ];
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

    private function parseDays(?string $value): ?int
    {
        if (! is_string($value)) {
            return null;
        }
        if (preg_match('/(\d+)/', $value, $m)) {
            $days = (int) $m[1];

            return $days > 0 ? $days : null;
        }

        return null;
    }

    private function dedupe(array $items): array
    {
        $deduped = [];
        $seenKeys = [];

        foreach ($items as $it) {
            $iccid = trim((string) data_get($it, 'esim.iccid', ''));
            $bundleId = trim((string) ($it['bundle_id'] ?? ''));
            $provider = (string) ($it['provider'] ?? '');
            $reference = (string) ($it['reference'] ?? '');
            $paymentId = (int) ($it['id'] ?? 0);

            $key = $iccid !== ''
                ? 'iccid:'.$iccid
                : ($bundleId !== ''
                    ? 'bundle:'.$bundleId
                    : ($reference !== '' ? 'ref:'.$provider.':'.$reference : 'payment:'.$paymentId));

            if (isset($seenKeys[$key])) {
                continue;
            }
            $seenKeys[$key] = true;
            $deduped[] = $it;
        }

        return $deduped;
    }
}
