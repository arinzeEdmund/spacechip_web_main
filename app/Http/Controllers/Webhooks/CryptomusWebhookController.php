<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\CryptomusService;
use App\Services\EsimFulfillment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cryptomus payment webhook. Verifies the signature, records the status, and provisions the
 * eSIM once the payment is final and paid.
 *
 * Always answers 200 for well-signed calls (even unknown orders) so Cryptomus stops retrying.
 */
class CryptomusWebhookController extends Controller
{
    public function __invoke(Request $request, CryptomusService $cryptomus, EsimFulfillment $esims): JsonResponse
    {
        $data = $request->json()->all();
        if (! is_array($data)) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        // The signature covers the JSON body without its own `sign` field.
        $sign = (string) ($data['sign'] ?? '');
        unset($data['sign']);
        if ($sign === '' || ! hash_equals($cryptomus->signPayload($data), $sign)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $ok = response()->json(['ok' => true]);
        $orderId = (string) ($data['order_id'] ?? '');
        $status = (string) ($data['status'] ?? '');

        $payment = $orderId !== '' ? Payment::where('provider', 'cryptomus')->where('provider_reference', $orderId)->first() : null;
        if (! $payment) {
            return $ok;
        }

        $payment->provider_payload = array_merge($payment->provider_payload ?? [], ['webhook' => $data]);
        $payment->status = $status;
        $payment->save();

        $paid = ($data['is_final'] ?? false) && in_array($status, ['paid', 'paid_over'], true);
        $email = (string) ($payment->user?->email ?? '');

        if ($paid && $email !== '' && ($payment->fulfillment_payload['ok'] ?? false) !== true) {
            $esims->fulfilPayment($payment, $email, 'cm_'.$orderId);
        }

        return $ok;
    }
}
