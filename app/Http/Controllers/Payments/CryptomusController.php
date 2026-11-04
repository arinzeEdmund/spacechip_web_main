<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\AiraloService;
use App\Services\CryptomusService;
use App\Services\EsimFulfillment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Crypto payments for eSIMs via Cryptomus (web only).
 *
 * invoice → (Cryptomus hosted page) → return page polls status. Fulfilment happens on whichever
 * arrives first: a status poll here or the signed webhook (CryptomusWebhookController).
 */
class CryptomusController extends Controller
{
    public function invoice(Request $request, CryptomusService $cryptomus, AiraloService $airalo, EsimFulfillment $esims): JsonResponse
    {
        $type = (string) $request->input('type', '');
        $id = (string) $request->input('id', '');
        $bundleId = (string) $request->input('bundle', '');
        $packageType = (string) $request->input('package_type', 'DATA-ONLY');
        $topupEsimId = trim((string) $request->input('topup_esim_id', ''));
        $kind = in_array($request->input('kind'), ['crypto', 'card'], true) ? $request->input('kind') : 'crypto';

        if ($type === '' || $id === '' || $bundleId === '') {
            return response()->json(['message' => 'Missing checkout parameters.'], 422);
        }

        $asset = $airalo->getAssetDetails($type, $id, $packageType);
        if (! $asset) {
            return response()->json(['message' => 'Asset not found.'], 404);
        }

        $bundle = collect($asset['bundles'] ?? [])->first(fn ($b) => (string) ($b['id'] ?? '') === $bundleId);
        if (! $bundle) {
            return response()->json(['message' => 'Bundle not found.'], 404);
        }

        $usdPrice = $bundle['price'] ?? null;
        if (! is_numeric($usdPrice) || (float) $usdPrice <= 0) {
            return response()->json(['message' => 'Invalid amount.'], 422);
        }

        if ($topupEsimId !== '' && ! $esims->canTopUp((int) Auth::id(), $topupEsimId)) {
            return response()->json(['message' => 'This eSIM cannot be renewed.'], 422);
        }

        $orderId = 'sc_crypto_'.Auth::id().'_'.str_replace('-', '_', (string) Str::uuid());
        $returnUrl = url('/crypto/return?order_id='.$orderId);

        $res = $cryptomus->createInvoice([
            'amount' => number_format((float) $usdPrice, 2, '.', ''),
            'currency' => 'USD',
            'order_id' => $orderId,
            'url_return' => $returnUrl,
            'url_success' => $returnUrl,
            'url_callback' => url('/api/cryptomus/webhook'),
            'lifetime' => 3600,
        ]);

        if (! is_array($res) || (int) data_get($res, 'state', 1) !== 0) {
            return response()->json(['message' => (string) (data_get($res, 'message') ?? 'Failed to create Cryptomus invoice.')], 502);
        }

        $payUrl = (string) data_get($res, 'result.url', '');
        $uuid = (string) data_get($res, 'result.uuid', '');
        if ($payUrl === '') {
            return response()->json(['message' => 'Cryptomus did not return a payment URL.'], 502);
        }

        Payment::create([
            'user_id' => Auth::id(),
            'provider' => 'cryptomus',
            'provider_reference' => $orderId,
            'status' => 'created',
            'asset_type' => $type,
            'asset_id' => $id,
            'bundle_id' => $bundleId,
            'package_type' => $packageType,
            'currency' => 'USD',
            'amount_minor' => (int) round((float) $usdPrice * 100),
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
                    'price' => (float) $usdPrice,
                    'price_formatted' => (string) ($bundle['price_formatted'] ?? ''),
                ],
                'topup' => $topupEsimId !== '' ? ['esim_id' => $topupEsimId] : null,
                'invoice_uuid' => $uuid,
                'pay_url' => $payUrl,
                'kind' => $kind,
                'raw' => $res,
            ],
        ]);

        return response()->json(['order_id' => $orderId, 'uuid' => $uuid, 'url' => $payUrl]);
    }

    public function return()
    {
        return view('cryptomus-return', ['orderId' => (string) request('order_id', '')]);
    }

    /** Read-only status from our own record (no provider call). */
    public function showStatus(Request $request): JsonResponse
    {
        $orderId = (string) $request->query('order_id', '');
        if ($orderId === '') {
            return response()->json(['message' => 'Missing order_id.'], 422);
        }

        $payment = $this->ownedPayment($orderId);
        if (! $payment) {
            return response()->json(['message' => 'Payment not found.'], 404);
        }

        return response()->json($this->statusPayload(
            $payment,
            (string) ($payment->status ?? ''),
            (bool) (data_get($payment->provider_payload, 'status.is_final') ?? false),
        ));
    }

    /** Refresh status from Cryptomus and, once paid, provision the eSIM (throttled). */
    public function status(Request $request, CryptomusService $cryptomus, EsimFulfillment $esims): JsonResponse
    {
        $orderId = (string) $request->input('order_id', '');
        if ($orderId === '') {
            return response()->json(['message' => 'Missing order_id.'], 422);
        }

        $payment = $this->ownedPayment($orderId);
        if (! $payment) {
            return response()->json(['message' => 'Payment not found.'], 404);
        }

        $res = $cryptomus->paymentInfo(['order_id' => $orderId]);
        if (! is_array($res) || (int) data_get($res, 'state', 1) !== 0) {
            return response()->json(['message' => 'Unable to fetch payment status.'], 502);
        }

        $result = (array) data_get($res, 'result', []);
        $status = (string) ($result['status'] ?? '');
        $isFinal = (bool) ($result['is_final'] ?? false);

        $payment->provider_payload = array_merge($payment->provider_payload ?? [], ['status' => $result]);
        $payment->status = $status !== '' ? $status : $payment->status;
        $payment->save();

        $paid = $isFinal && in_array($status, ['paid', 'paid_over'], true);
        if ($paid && ($payment->fulfillment_payload['ok'] ?? false) !== true && EsimFulfillment::retryDue($payment->fulfillment_payload['attempted_at'] ?? null)) {
            $esims->fulfilPayment($payment, (string) (Auth::user()?->email ?? ''), 'cm_'.$orderId);
        }

        return response()->json($this->statusPayload($payment, $status, $isFinal));
    }

    private function statusPayload(Payment $payment, string $status, bool $isFinal): array
    {
        $fp = is_array($payment->fulfillment_payload) ? $payment->fulfillment_payload : [];

        return [
            'order_id' => $payment->provider_reference,
            'status' => $status,
            'is_final' => $isFinal,
            'fulfilled' => (bool) ($fp['ok'] ?? false),
            'synced' => (bool) ($fp['synced'] ?? false),
            'fulfillment_error' => (string) ($fp['error'] ?? ''),
        ];
    }

    private function ownedPayment(string $orderId): ?Payment
    {
        $payment = Payment::where('provider', 'cryptomus')->where('provider_reference', $orderId)->first();

        return $payment && (int) $payment->user_id === (int) Auth::id() ? $payment : null;
    }
}
