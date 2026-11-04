<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\VirtualNumberSubscription;
use App\Services\PaystackService;
use App\Services\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Monthly renewal for virtual numbers whose period has ended (50 per run, oldest first).
 *
 * Wallet subscriptions are debited; Paystack subscriptions charge the card authorization saved
 * at checkout. A failed renewal marks the subscription `past_due` and records why; the next
 * run tries again.
 */
class RenewVirtualNumbers extends Command
{
    protected $signature = 'virtual-numbers:renew';

    protected $description = 'Charge monthly renewals for active virtual number subscriptions';

    public function handle(WalletService $wallet, PaystackService $paystack): int
    {
        $due = VirtualNumberSubscription::query()
            ->with('user')
            ->where('status', 'active')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now())
            ->orderBy('current_period_end')
            ->limit(50)
            ->get();

        if ($due->isEmpty()) {
            $this->info('No renewals due.');

            return self::SUCCESS;
        }

        foreach ($due as $sub) {
            $reference = 'vn_renew_'.$sub->id.'_'.str_replace('-', '_', (string) Str::uuid());

            trim((string) $sub->billing_method) === 'wallet'
                ? $this->renewFromWallet($sub, $reference, $wallet)
                : $this->renewWithCard($sub, $reference, $paystack);
        }

        $this->info('Processed renewals: '.$due->count());

        return self::SUCCESS;
    }

    private function renewFromWallet(VirtualNumberSubscription $sub, string $reference, WalletService $wallet): void
    {
        $payment = Payment::create([
            'user_id' => $sub->user_id,
            'provider' => 'wallet',
            'provider_reference' => $reference,
            'status' => 'paid',
            'asset_type' => 'virtual_number_renewal',
            'asset_id' => (string) $sub->id,
            'bundle_id' => (string) $sub->virtual_number_product_id,
            'package_type' => 'MONTHLY',
            'currency' => 'USD',
            'amount_minor' => (int) $sub->monthly_amount_minor,
            'provider_payload' => ['subscription_id' => (int) $sub->id, 'phone_number' => (string) ($sub->phone_number ?? '')],
        ]);

        try {
            $wallet->debit((int) $sub->user_id, (int) $sub->monthly_amount_minor, 'virtual_number_renewal', [
                'subscription_id' => (int) $sub->id,
                'reference' => $reference,
            ], (int) $payment->id, (int) config('wallet.min_balance_minor', 0));
        } catch (Throwable $e) {
            $this->markPastDue($sub, $e->getMessage());
            $payment->update(['status' => 'failed_insufficient_wallet', 'fulfillment_payload' => ['ok' => false, 'error' => $e->getMessage()]]);

            return;
        }

        $this->markRenewed($sub, $reference);
        $payment->update(['status' => 'fulfilled', 'fulfillment_payload' => ['ok' => true]]);
    }

    private function renewWithCard(VirtualNumberSubscription $sub, string $reference, PaystackService $paystack): void
    {
        if (! $paystack->isConfigured()) {
            $this->markPastDue($sub, 'PAYSTACK_SECRET_KEY not configured.');

            return;
        }

        $authCode = trim((string) $sub->paystack_authorization_code);
        if ($authCode === '' || trim((string) $sub->paystack_customer_code) === '') {
            $this->markPastDue($sub, 'Missing Paystack authorization.');

            return;
        }

        $email = trim((string) ($sub->paystack_email ?? '')) ?: trim((string) ($sub->user?->email ?? ''));
        if ($email === '') {
            $this->markPastDue($sub, 'Missing user email.');

            return;
        }

        try {
            $res = $paystack->chargeAuthorization([
                'authorization_code' => $authCode,
                'email' => $email,
                'amount' => (int) $sub->monthly_amount_minor,
                'currency' => strtoupper((string) $sub->currency),
                'reference' => $reference,
                'metadata' => ['purpose' => 'virtual_number_renewal', 'subscription_id' => (int) $sub->id],
            ]);
        } catch (Throwable) {
            $this->markPastDue($sub, 'Paystack charge failed.');

            return;
        }

        if (! $res->successful()) {
            $this->markPastDue($sub, 'Paystack charge failed.');

            return;
        }

        // `pending` charges settle asynchronously and are treated as paid.
        if (! $res->json('status') || ! in_array((string) $res->json('data.status'), ['success', 'pending'], true)) {
            $this->markPastDue($sub, 'Paystack charge not successful.');

            return;
        }

        $this->markRenewed($sub, $reference);
    }

    private function markPastDue(VirtualNumberSubscription $sub, string $error): void
    {
        $sub->update([
            'status' => 'past_due',
            'renewal_failed_count' => (int) $sub->renewal_failed_count + 1,
            'last_renewal_error' => $error,
        ]);
    }

    private function markRenewed(VirtualNumberSubscription $sub, string $reference): void
    {
        $sub->update([
            'status' => 'active',
            'renewal_failed_count' => 0,
            'last_renewal_error' => null,
            'last_charge_reference' => $reference,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
    }
}
