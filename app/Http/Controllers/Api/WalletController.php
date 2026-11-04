<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\NoticeMail;
use App\Models\Payment;
use App\Models\VirtualNumberProduct;
use App\Models\VirtualNumberSubscription;
use App\Models\WalletTransaction;
use App\Services\AiraloService;
use App\Services\EsimFulfillment;
use App\Services\ExchangeRateService;
use App\Services\PaystackService;
use App\Services\TwilioService;
use App\Services\VirtualNumberCatalog;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * USD wallet: balance and ledger, Paystack top-ups, and paying for eSIMs and virtual numbers
 * from the balance. Purchases debit first and refund automatically if provisioning fails.
 */
class WalletController extends Controller
{
    public function __construct(private WalletService $wallet) {}

    public function balance(): JsonResponse
    {
        $balance = $this->wallet->balanceMinor((int) Auth::id());

        return response()->json(['ok' => true, 'balance_minor' => $balance, 'balance_formatted' => $this->wallet->formatUsd($balance)]);
    }

    public function transactions(): JsonResponse
    {
        $items = WalletTransaction::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'direction', 'action', 'amount_minor', 'balance_after_minor', 'currency', 'payment_id', 'meta', 'created_at'])
            ->map(fn (WalletTransaction $t) => [
                'id' => (int) $t->id,
                'direction' => (string) $t->direction,
                'action' => (string) $t->action,
                'amount_minor' => (int) $t->amount_minor,
                'balance_after_minor' => (int) $t->balance_after_minor,
                'currency' => (string) ($t->currency ?? 'USD'),
                'payment_id' => $t->payment_id ? (int) $t->payment_id : null,
                'meta' => is_array($t->meta) ? $t->meta : null,
                'created_at' => $t->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return response()->json(['ok' => true, 'items' => $items]);
    }

    // ─── Deposits (Paystack) ────────────────────────────────────────────────────

    /** Start a Paystack charge for a USD amount (converted to NGN when Paystack settles in naira). */
    public function depositInitialize(Request $request, ExchangeRateService $exchangeRates, PaystackService $paystack): JsonResponse
    {
        $minUsd = (float) config('wallet.min_deposit_usd', 1);
        $maxUsd = (float) config('wallet.max_deposit_usd', 5000);
        $usd = (float) $request->input('amount_usd', 0);

        if ($usd <= 0) {
            return response()->json(['message' => 'Invalid amount.'], 422);
        }
        if ($usd < $minUsd) {
            return response()->json(['message' => 'Minimum deposit is $'.number_format($minUsd, 2).'.'], 422);
        }
        if ($usd > $maxUsd) {
            return response()->json(['message' => 'Maximum deposit is $'.number_format($maxUsd, 2).'.'], 422);
        }

        $usdMinor = (int) round($usd * 100);
        $currency = strtoupper((string) config('services.paystack.currency', 'NGN')) ?: 'NGN';

        $rate = null;
        $amountMinor = $usdMinor;
        if ($currency === 'NGN') {
            $rate = (float) ($exchangeRates->usdToNgn()['rate'] ?? 0);
            if ($rate <= 0) {
                return response()->json(['message' => 'Unable to fetch USD to NGN exchange rate. Set ESIM_USD_TO_NGN_RATE as a fallback or try again shortly.'], 422);
            }
            $amountMinor = (int) round(($usdMinor / 100) * $rate * 100);
        }

        if ($usdMinor <= 0 || $amountMinor <= 0) {
            return response()->json(['message' => 'Invalid amount.'], 422);
        }

        // Deposits can be routed through a single owner address (services.paystack.owner_email).
        $email = trim((string) config('services.paystack.owner_email', '')) ?: trim((string) (Auth::user()?->email ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return response()->json(['message' => 'Please enter a valid email address.'], 422);
        }

        $reference = (string) Str::uuid();
        $payment = Payment::create([
            'user_id' => Auth::id(),
            'provider' => 'paystack',
            'provider_reference' => $reference,
            'status' => 'initialized',
            'asset_type' => 'wallet_deposit',
            'asset_id' => (string) Auth::id(),
            'bundle_id' => 'wallet',
            'package_type' => 'deposit',
            'currency' => $currency,
            'amount_minor' => $amountMinor,
            'provider_payload' => [
                'wallet' => [
                    'usd_amount_minor' => $usdMinor,
                    'usd_amount_formatted' => $this->wallet->formatUsd($usdMinor),
                    'usd_to_ngn_rate' => $rate,
                    'paystack_email' => $email,
                ],
            ],
        ]);

        if (! $paystack->isConfigured()) {
            return response()->json(['message' => 'Paystack secret key is not configured.'], 500);
        }

        try {
            $response = $paystack->initialize([
                'email' => $email,
                'amount' => $amountMinor,
                'currency' => $currency,
                'reference' => $reference,
                'metadata' => ['purpose' => 'wallet_deposit', 'usd_amount_minor' => $usdMinor],
            ]);
        } catch (Throwable) {
            $payment->update(['status' => 'init_failed']);

            return response()->json(['message' => 'Failed to initialize Paystack transaction.'], 502);
        }

        if (! $response->successful()) {
            $payment->update([
                'status' => 'init_failed',
                'provider_payload' => array_merge($payment->provider_payload ?? [], ['paystack' => $response->json()]),
            ]);
            $message = trim((string) (data_get($response->json(), 'message') ?? ''));

            return response()->json(['message' => $message !== '' ? $message : 'Failed to initialize Paystack transaction.'], 502);
        }

        $accessCode = data_get($response->json(), 'data.access_code');
        if (! is_string($accessCode) || $accessCode === '') {
            return response()->json(['message' => 'Paystack did not return an access code.'], 502);
        }

        return response()->json([
            'ok' => true,
            'reference' => $reference,
            'access_code' => $accessCode,
            'amount' => $amountMinor,
            'currency' => $currency,
            'email' => $email,
            'wallet_credit_usd_minor' => $usdMinor,
            'wallet_credit_usd_formatted' => $this->wallet->formatUsd($usdMinor),
        ]);
    }

    /** Confirm a deposit with Paystack and credit the wallet exactly once (locked per reference). */
    public function depositVerify(Request $request, PaystackService $paystack): JsonResponse
    {
        $reference = trim((string) $request->input('reference', ''));
        if ($reference === '') {
            return response()->json(['message' => 'Missing reference.'], 422);
        }

        $lock = Cache::lock('wallet_verify_lock_'.sha1($reference), 60);
        if (! $lock->get()) {
            return response()->json(['message' => 'Verification in progress, please wait.'], 429);
        }

        try {
            $payment = Payment::where('provider', 'paystack')->where('provider_reference', $reference)->first();
            if (! $payment || (int) $payment->user_id !== (int) Auth::id() || (string) $payment->asset_type !== 'wallet_deposit') {
                return response()->json(['message' => 'Payment record not found.'], 404);
            }

            if ($payment->status === 'fulfilled' && ($payment->fulfillment_payload['ok'] ?? false) === true) {
                return response()->json(['ok' => true, 'wallet' => $payment->fulfillment_payload, 'already_fulfilled' => true]);
            }

            if (! $paystack->isConfigured()) {
                return response()->json(['message' => 'Paystack secret key is not configured.'], 500);
            }

            try {
                $response = $paystack->verify($reference);
            } catch (Throwable) {
                return response()->json(['message' => 'Failed to connect to Paystack for verification.'], 502);
            }

            if (! $response->successful()) {
                return response()->json(['message' => 'Paystack verification request failed.'], 502);
            }

            $tx = (array) data_get($response->json(), 'data', []);
            $status = (string) ($tx['status'] ?? '');
            if ($status !== 'success') {
                return response()->json(['message' => 'Payment status is: '.$status], 422);
            }

            $txCurrency = strtoupper((string) ($tx['currency'] ?? ''));
            $txAmount = (int) ($tx['amount'] ?? 0);
            if ($txCurrency === '' || $txAmount <= 0 || $txCurrency !== strtoupper((string) $payment->currency)) {
                return response()->json(['message' => 'Transaction details mismatch.'], 404);
            }

            // Credit what we quoted at initialise time; Paystack metadata is the fallback.
            $usdMinor = (int) data_get($payment->provider_payload, 'wallet.usd_amount_minor', 0) ?: (int) data_get($tx, 'metadata.usd_amount_minor', 0);
            if ($usdMinor <= 0) {
                return response()->json(['message' => 'Unable to determine credit amount.'], 422);
            }

            $userId = (int) Auth::id();
            $entry = $this->wallet->credit($userId, $usdMinor, 'deposit', [
                'reference' => $reference,
                'paystack_currency' => $txCurrency,
                'paystack_amount_minor' => $txAmount,
            ], (int) $payment->id);

            $balance = $this->wallet->balanceMinor($userId);
            $payload = [
                'ok' => true,
                'wallet_transaction_id' => (int) $entry->id,
                'credited_usd_minor' => $usdMinor,
                'credited_usd_formatted' => $this->wallet->formatUsd($usdMinor),
                'balance_usd_minor' => $balance,
                'balance_usd_formatted' => $this->wallet->formatUsd($balance),
            ];
            $payment->update(['status' => 'fulfilled', 'fulfillment_payload' => $payload]);

            $this->notify(new NoticeMail(
                subjectLine: 'Wallet funded successfully',
                kicker: 'Wallet deposit confirmed',
                heading: 'Deposit successful',
                rows: ['Amount' => $payload['credited_usd_formatted'], 'New balance' => $payload['balance_usd_formatted']],
                footnote: 'You can now pay for eSIMs and virtual numbers using your wallet balance.',
            ));

            return response()->json(['ok' => true, 'wallet' => $payload]);
        } finally {
            $lock->release();
        }
    }

    // ─── Purchases ──────────────────────────────────────────────────────────────

    public function payEsim(Request $request, AiraloService $airalo, EsimFulfillment $esims): JsonResponse
    {
        $type = (string) $request->input('type', '');
        $id = (string) $request->input('id', '');
        $bundleId = (string) $request->input('bundle', '');
        $packageType = (string) $request->input('package_type', 'DATA-ONLY');
        $topupEsimId = trim((string) $request->input('topup_esim_id', ''));
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

        $price = $bundle['price'] ?? null;
        $amountMinor = is_numeric($price) ? (int) round((float) $price * 100) : 0;
        if ($amountMinor <= 0) {
            return response()->json(['message' => 'Invalid amount.'], 422);
        }

        $userId = (int) Auth::id();
        if ($topupEsimId !== '' && ! $esims->canTopUp($userId, $topupEsimId)) {
            return response()->json(['message' => 'This eSIM cannot be renewed.'], 422);
        }

        $action = $topupEsimId !== '' ? 'topup_esim' : 'buy_esim';
        $reference = 'w_'.Str::uuid();
        $payment = Payment::create([
            'user_id' => $userId,
            'provider' => 'wallet',
            'provider_reference' => $reference,
            'status' => 'paid',
            'asset_type' => $type,
            'asset_id' => $id,
            'bundle_id' => $bundleId,
            'package_type' => $packageType,
            'currency' => 'USD',
            'amount_minor' => $amountMinor,
            'provider_payload' => [
                'wallet' => ['action' => $action, 'topup_esim_id' => $topupEsimId ?: null],
                'asset' => ['name' => (string) ($asset['name'] ?? ''), 'code' => (string) ($asset['code'] ?? '')],
                'bundle' => [
                    'name' => (string) ($bundle['name'] ?? ''),
                    'data' => (string) ($bundle['data'] ?? ''),
                    'validity' => (string) ($bundle['validity'] ?? ''),
                    'price' => (float) $price,
                    'price_formatted' => (string) ($bundle['price_formatted'] ?? ''),
                ],
            ],
        ]);

        $order = ['type' => $type, 'id' => $id, 'bundle' => $bundleId, 'package_type' => $packageType, 'topup_esim_id' => $topupEsimId ?: null];
        if ($failed = $this->debit($payment, $action, $order + ['reference' => $reference])) {
            return $failed;
        }

        $purchase = $esims->purchase($bundleId, $reference, (string) (Auth::user()?->email ?? ''), $packageType, $topupEsimId);

        if (($purchase['ok'] ?? false) !== true) {
            if ($purchase['pending'] ?? false) {
                // Charged and ordered; delivery completes asynchronously, so no refund.
                $payment->update([
                    'status' => 'paid_pending_fulfillment',
                    'fulfillment_payload' => array_filter([
                        'ok' => false,
                        'pending' => true,
                        'attempted_at' => now()->toIso8601String(),
                        'error' => (string) ($purchase['error'] ?? ''),
                        'topup_esim_id' => $topupEsimId ?: null,
                        'esim_id' => $purchase['esim_id'] ?? null,
                        'order_id' => $purchase['order_id'] ?? null,
                    ], fn ($v) => $v !== null),
                ]);

                return response()->json(['ok' => true, 'reference' => $reference, 'fulfillment' => ['ok' => false, 'pending' => true, 'message' => 'Payment confirmed. Preparing your eSIM…']]);
            }

            $this->refund($payment, 'failed_refunded', (string) ($purchase['error'] ?? 'Fulfillment failed'));

            return response()->json(['message' => 'Payment confirmed, but fulfillment failed. Wallet was refunded.'], 502);
        }

        $payload = array_filter([
            'ok' => true,
            'synced' => true,
            'attempted_at' => now()->toIso8601String(),
            ...$order,
            'esim_id' => $purchase['esim_id'] ?? null,
            'order_id' => $purchase['order_id'] ?? null,
            'activation_code' => (string) ($purchase['activation_code'] ?? ''),
            'lpa' => (string) ($purchase['lpa'] ?? $purchase['activation_code'] ?? ''),
            'puk_code' => (string) ($purchase['puk_code'] ?? ''),
            'iccid' => (string) ($purchase['iccid'] ?? ''),
            'number' => is_scalar($purchase['number'] ?? null) ? (string) $purchase['number'] : '',
            'esim_status' => (string) ($purchase['esim_status'] ?? ''),
            'direct_installation_link_ios' => (string) ($purchase['direct_installation_link_ios'] ?? ''),
            'direct_installation_link_android' => (string) ($purchase['direct_installation_link_android'] ?? ''),
            'qr_code_url' => (string) ($purchase['qr_code_url'] ?? ''),
            'smdp_address' => (string) ($purchase['smdp_address'] ?? ''),
            'airalo' => $purchase['raw'] ?? $purchase,
        ], fn ($v) => $v !== null);

        $payment->update(['status' => 'fulfilled', 'fulfillment_payload' => $payload]);

        return response()->json(['ok' => true, 'fulfillment' => $payload]);
    }

    /** Buy a Twilio number for one month (see VirtualNumberCatalog::monthlyPrice for pricing). */
    public function payVirtualNumber(Request $request, TwilioService $twilio, VirtualNumberCatalog $catalog): JsonResponse
    {
        $productId = (int) $request->input('product_id', 0);
        $phoneNumber = trim((string) $request->input('phone_number', ''));
        $numberType = VirtualNumberCatalog::numberType($request->input('number_type'));
        if ($productId <= 0 || $phoneNumber === '') {
            return response()->json(['message' => 'Missing checkout parameters.'], 422);
        }

        $product = VirtualNumberProduct::query()->where('active', true)->find($productId);
        if (! $product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        // The wallet is USD; Twilio prices numbers in USD.
        [, $amountMinor] = $catalog->monthlyPrice($product, $numberType);

        if ($amountMinor <= 0) {
            return response()->json(['message' => 'Invalid amount.'], 422);
        }

        $order = ['product_id' => $product->id, 'phone_number' => $phoneNumber, 'number_type' => $numberType ?: null];
        $reference = 'wvn_'.Str::uuid();
        $payment = Payment::create([
            'user_id' => Auth::id(),
            'provider' => 'wallet',
            'provider_reference' => $reference,
            'status' => 'paid',
            'asset_type' => 'virtual_number',
            'asset_id' => (string) $product->id,
            'bundle_id' => (string) $product->id,
            'package_type' => 'MONTHLY',
            'currency' => 'USD',
            'amount_minor' => $amountMinor,
            'provider_payload' => ['virtual_number' => $order + ['country_iso' => (string) $product->country_iso]],
        ]);

        if ($failed = $this->debit($payment, 'buy_virtual_number', $order + ['reference' => $reference])) {
            return $failed;
        }

        $purchase = $twilio->purchaseNumber(
            $phoneNumber,
            (string) (config('services.twilio.sms_webhook_url') ?: url('/api/twilio/sms')),
            (string) (config('services.twilio.voice_webhook_url') ?: url('/api/twilio/voice')),
        );
        if (($purchase['ok'] ?? false) !== true) {
            $this->refund($payment, 'paid_failed_provision_refunded', (string) ($purchase['error'] ?? 'Twilio provisioning failed'));

            return response()->json(['message' => 'Number provisioning failed. Wallet was refunded.'], 502);
        }

        $now = now();
        $sub = VirtualNumberSubscription::create([
            'user_id' => Auth::id(),
            'virtual_number_product_id' => $product->id,
            'status' => 'active',
            'phone_number' => (string) ($purchase['phone_number'] ?? $phoneNumber),
            'country_iso' => (string) $product->country_iso,
            'cap_sms' => true,
            'cap_voice' => true,
            'provider' => 'twilio',
            'billing_method' => 'wallet',
            'twilio_phone_number_sid' => (string) ($purchase['sid'] ?? ''),
            'currency' => 'USD',
            'monthly_amount_minor' => $amountMinor,
            'current_period_start' => $now,
            'current_period_end' => $now->copy()->addMonth(),
            'renewal_failed_count' => 0,
        ]);

        $payload = [
            'ok' => true,
            'subscription_id' => $sub->id,
            'phone_number' => $sub->phone_number,
            'status' => $sub->status,
            'current_period_end' => $sub->current_period_end?->toIso8601String(),
        ];
        $payment->update(['status' => 'fulfilled', 'fulfillment_payload' => $payload]);

        $this->notify(NoticeMail::numberActivated($sub, $product));

        return response()->json(['ok' => true, 'subscription' => $payload]);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    /** Take payment from the wallet; on insufficient funds mark the payment failed and return the error response. */
    private function debit(Payment $payment, string $action, array $meta): ?JsonResponse
    {
        try {
            $this->wallet->debit((int) $payment->user_id, (int) $payment->amount_minor, $action, $meta, (int) $payment->id, (int) config('wallet.min_balance_minor', 0));

            return null;
        } catch (Throwable $e) {
            $payment->update(['status' => 'failed_insufficient_wallet', 'fulfillment_payload' => ['ok' => false, 'error' => $e->getMessage()]]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** Return the payment's amount to the wallet after a provisioning failure. */
    private function refund(Payment $payment, string $status, string $reason): void
    {
        try {
            $this->wallet->credit((int) $payment->user_id, (int) $payment->amount_minor, 'refund', ['reason' => $reason, 'reference' => $payment->provider_reference], (int) $payment->id);
        } catch (Throwable) {
            // Recorded on the payment below; support can settle it from the admin console.
        }

        $payment->update(['status' => $status, 'fulfillment_payload' => ['ok' => false, 'error' => $reason]]);
    }

    /** Email the signed-in user; notification failures never fail the request. */
    private function notify(NoticeMail $mail): void
    {
        $email = trim((string) (Auth::user()?->email ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        try {
            Mail::to($email)->send($mail);
        } catch (Throwable) {
        }
    }
}
