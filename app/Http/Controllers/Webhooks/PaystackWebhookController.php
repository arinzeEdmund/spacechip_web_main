<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaystackService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Paystack webhook for WALLET DEPOSITS: credits a paid deposit even if the customer closed the app before the
 * client-side verify call. Safe by construction: HMAC-SHA512 over the raw body (constant-time), the payment is
 * re-verified with Paystack's API (amount, currency, status), only a payment row we created is credited, the
 * credit happens once under the same lock the client verify uses, and every answer is a generic 200/4xx.
 * Other products still settle through their own verify flows.
 */
class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaystackService $paystack, WalletService $wallet): JsonResponse
    {
        $secret = (string) config('services.paystack.secret_key');
        $signature = (string) $request->header('x-paystack-signature', '');
        $expected = $secret !== '' ? hash_hmac('sha512', $request->getContent(), $secret) : '';

        if ($expected === '' || $signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        if ((string) $request->input('event') !== 'charge.success') {
            return response()->json(['ok' => true]); // acknowledged, nothing to do
        }

        $reference = trim((string) data_get($request->all(), 'data.reference', ''));
        if ($reference === '') {
            return response()->json(['ok' => true]);
        }

        $lock = Cache::lock('wallet_verify_lock_'.sha1($reference), 60); // same lock as the client verify call
        if (! $lock->get()) {
            return response()->json(['message' => 'Busy.'], 409); // Paystack retries
        }

        try {
            $payment = Payment::query()->where('provider', 'paystack')->where('provider_reference', $reference)->first();
            if (! $payment || (string) $payment->asset_type !== 'wallet_deposit') {
                return response()->json(['ok' => true]); // not ours / not a deposit: ignore
            }
            if ($payment->status === 'fulfilled') {
                return response()->json(['ok' => true]); // duplicate delivery
            }

            // Never trust the payload: ask Paystack.
            try {
                $res = $paystack->verify($reference);
            } catch (Throwable) {
                return response()->json(['message' => 'Verification unavailable.'], 502);
            }
            $tx = (array) data_get($res->json(), 'data', []);
            if (! $res->successful() || (string) ($tx['status'] ?? '') !== 'success') {
                return response()->json(['ok' => true]);
            }
            $currency = strtoupper((string) ($tx['currency'] ?? ''));
            $amount = (int) ($tx['amount'] ?? 0);
            if ($currency === '' || $amount <= 0 || $currency !== strtoupper((string) $payment->currency) || $amount < (int) $payment->amount_minor) {
                Log::warning('Paystack webhook amount/currency mismatch', ['reference' => $reference]);

                return response()->json(['ok' => true]); // never credit a mismatch
            }

            $usdMinor = (int) data_get($payment->provider_payload, 'wallet.usd_amount_minor', 0);
            if ($usdMinor <= 0) {
                return response()->json(['ok' => true]);
            }

            $entry = $wallet->credit((int) $payment->user_id, $usdMinor, 'deposit', [
                'reference' => $reference,
                'paystack_currency' => $currency,
                'paystack_amount_minor' => $amount,
                'via' => 'webhook',
            ], (int) $payment->id);

            $balance = $wallet->balanceMinor((int) $payment->user_id);
            $payment->update(['status' => 'fulfilled', 'fulfillment_payload' => [
                'ok' => true,
                'wallet_transaction_id' => (int) $entry->id,
                'credited_usd_minor' => $usdMinor,
                'credited_usd_formatted' => $wallet->formatUsd($usdMinor),
                'balance_usd_minor' => $balance,
                'balance_usd_formatted' => $wallet->formatUsd($balance),
            ]]);

            return response()->json(['ok' => true]);
        } finally {
            optional($lock)->release();
        }
    }
}
