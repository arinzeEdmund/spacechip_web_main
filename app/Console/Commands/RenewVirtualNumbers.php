<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\VirtualNumberSubscription;
use App\Services\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RenewVirtualNumbers extends Command
{
    protected $signature = 'virtual-numbers:renew';

    protected $description = 'Charge monthly renewals for active virtual number subscriptions';

    public function handle(WalletService $wallet): int
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

            return 0;
        }

        $secretKey = (string) (config('services.paystack.secret_key') ?: env('PAYSTACK_SECRET_KEY', ''));
        $secretKey = trim($secretKey, " \t\n\r\0\x0B\"'");

        foreach ($due as $sub) {
            $billing = trim((string) ($sub->billing_method ?? 'paystack'));
            if ($billing === '') {
                $billing = 'paystack';
            }

            if ($billing === 'wallet') {
                $reference = 'vn_renew_'.(string) $sub->id.'_'.str_replace('-', '_', (string) Str::uuid());
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
                    'provider_payload' => [
                        'subscription_id' => (int) $sub->id,
                        'phone_number' => (string) ($sub->phone_number ?? ''),
                    ],
                ]);

                try {
                    $wallet->debit((int) $sub->user_id, (int) $sub->monthly_amount_minor, 'virtual_number_renewal', [
                        'subscription_id' => (int) $sub->id,
                        'reference' => $reference,
                    ], (int) $payment->id, (int) env('WALLET_MIN_BALANCE_MINOR', 0));
                } catch (\Throwable $e) {
                    $sub->status = 'past_due';
                    $sub->renewal_failed_count = (int) $sub->renewal_failed_count + 1;
                    $sub->last_renewal_error = $e->getMessage();
                    $sub->save();

                    $payment->status = 'failed_insufficient_wallet';
                    $payment->fulfillment_payload = ['ok' => false, 'error' => $e->getMessage()];
                    $payment->save();

                    continue;
                }

                $sub->status = 'active';
                $sub->renewal_failed_count = 0;
                $sub->last_renewal_error = null;
                $sub->last_charge_reference = $reference;
                $sub->current_period_start = now();
                $sub->current_period_end = now()->addMonth();
                $sub->save();

                $payment->status = 'fulfilled';
                $payment->fulfillment_payload = ['ok' => true];
                $payment->save();

                continue;
            }

            if ($secretKey === '') {
                $sub->status = 'past_due';
                $sub->renewal_failed_count = (int) $sub->renewal_failed_count + 1;
                $sub->last_renewal_error = 'PAYSTACK_SECRET_KEY not configured.';
                $sub->save();

                continue;
            }

            $authCode = trim((string) $sub->paystack_authorization_code);
            $customerCode = trim((string) $sub->paystack_customer_code);
            if ($authCode === '' || $customerCode === '') {
                $sub->status = 'past_due';
                $sub->renewal_failed_count = (int) $sub->renewal_failed_count + 1;
                $sub->last_renewal_error = 'Missing Paystack authorization.';
                $sub->save();

                continue;
            }

            $reference = 'vn_renew_'.(string) $sub->id.'_'.str_replace('-', '_', (string) Str::uuid());
            $email = trim((string) ($sub->paystack_email ?? ''));
            if ($email === '') {
                $email = (string) ($sub->user?->email ?? '');
            }
            if (trim($email) === '') {
                $sub->status = 'past_due';
                $sub->renewal_failed_count = (int) $sub->renewal_failed_count + 1;
                $sub->last_renewal_error = 'Missing user email.';
                $sub->save();

                continue;
            }

            $res = Http::withToken($secretKey)
                ->acceptJson()
                ->timeout(30)
                ->post('https://api.paystack.co/transaction/charge_authorization', [
                    'authorization_code' => $authCode,
                    'email' => $email,
                    'amount' => (int) $sub->monthly_amount_minor,
                    'currency' => strtoupper((string) $sub->currency),
                    'reference' => $reference,
                    'metadata' => [
                        'purpose' => 'virtual_number_renewal',
                        'subscription_id' => (int) $sub->id,
                    ],
                ]);

            if (! $res->successful()) {
                $sub->status = 'past_due';
                $sub->renewal_failed_count = (int) $sub->renewal_failed_count + 1;
                $sub->last_renewal_error = 'Paystack charge failed.';
                $sub->save();

                continue;
            }

            $json = $res->json();
            $ok = (bool) data_get($json, 'status', false);
            $dataStatus = (string) data_get($json, 'data.status', '');
            if (! $ok || ! in_array($dataStatus, ['success', 'pending'], true)) {
                $sub->status = 'past_due';
                $sub->renewal_failed_count = (int) $sub->renewal_failed_count + 1;
                $sub->last_renewal_error = 'Paystack charge not successful.';
                $sub->save();

                continue;
            }

            $sub->status = 'active';
            $sub->renewal_failed_count = 0;
            $sub->last_renewal_error = null;
            $sub->last_charge_reference = $reference;
            $sub->current_period_start = now();
            $sub->current_period_end = now()->addMonth();
            $sub->save();
        }

        $this->info('Processed renewals: '.$due->count());

        return 0;
    }
}
