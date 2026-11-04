<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\SocialNumberRental;
use App\Services\RentalProviders;
use App\Services\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Renews monthly rentals (SMSPool or LongSMS, per rental) from the wallet, starting two days before the period ends
 * (runs hourly, 50 rentals per run). A failed renewal leaves the rental `past_due` and is
 * retried next run; after the period has lapsed and several attempts failed, the rental is
 * expired so we stop charging for a number the provider has already reclaimed.
 */
class RenewSocialNumberRentals extends Command
{
    protected $signature = 'social-rentals:renew';

    protected $description = 'Renew monthly SMSPool social number rentals that are close to expiry';

    /**
     * How many hourly renewal attempts to make once the paid period has fully
     * lapsed before giving up and marking the rental expired.
     */
    private const MAX_RENEWAL_ATTEMPTS = 4;

    /** Renew this many days before the current period ends. */
    private const RENEW_LEAD_DAYS = 2;

    public function handle(RentalProviders $providers, WalletService $wallet): int
    {
        $due = SocialNumberRental::query()
            ->whereIn('status', ['active', 'past_due'])
            ->where('auto_renew', true)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now()->addDays(self::RENEW_LEAD_DAYS))
            ->orderBy('current_period_end')
            ->limit(50)
            ->get();

        if ($due->isEmpty()) {
            $this->info('No social rental renewals due.');

            return self::SUCCESS;
        }

        foreach ($due as $rental) {
            // Give up on a rental whose paid period lapsed over a day ago and
            // that has already failed the renewal several times — the number is
            // gone at the provider, so stop retrying (and stop creating wallet
            // charges / payment rows every hour).
            if (
                $rental->current_period_end
                && $rental->current_period_end->lt(now()->subDay())
                && (int) $rental->renewal_failed_count >= self::MAX_RENEWAL_ATTEMPTS
            ) {
                $rental->status = 'expired';
                $rental->auto_renew = false;
                $rental->last_renewal_error = 'Renewal stopped after repeated failures; the number has expired.';
                $rental->save();

                Log::warning('Social rental renewal abandoned; number expired at provider', [
                    'rental_id' => $rental->id,
                    'user_id' => $rental->user_id,
                    'failed_count' => (int) $rental->renewal_failed_count,
                    'period_end' => $rental->current_period_end->toIso8601String(),
                ]);

                continue;
            }

            $amountMinor = (int) $rental->monthly_amount_minor;
            if ($amountMinor <= 0) {
                $this->markPastDue($rental, 'Invalid monthly amount.');

                continue;
            }

            // Renew through the supplier this rental was bought from, regardless of which one is active now.
            $rent = $providers->for($rental->provider);
            $reference = 'snrent_renew_'.(string) $rental->id.'_'.str_replace('-', '_', (string) Str::uuid());
            $payment = Payment::create([
                'user_id' => $rental->user_id,
                'provider' => 'wallet',
                'provider_reference' => $reference,
                'status' => 'created',
                'asset_type' => 'social_number_rental_renewal',
                'asset_id' => (string) $rental->id,
                'bundle_id' => (string) $rental->product,
                'package_type' => 'MONTHLY',
                'currency' => 'USD',
                'amount_minor' => $amountMinor,
                'provider_payload' => [
                    'rental_id' => (int) $rental->id,
                    'provider_order_id' => (string) $rental->provider_order_id,
                    'phone' => trim((string) $rental->phone_country_code.(string) $rental->phone),
                ],
            ]);

            try {
                $wallet->debit((int) $rental->user_id, $amountMinor, 'social_rental_renewal', [
                    'rental_id' => (int) $rental->id,
                    'reference' => $reference,
                    'provider' => $rent->key(),
                ], (int) $payment->id, (int) config('wallet.min_balance_minor', 0));
            } catch (\Throwable $e) {
                $this->markPastDue($rental, $e->getMessage());
                $payment->update(['status' => 'failed_insufficient_wallet', 'fulfillment_payload' => ['ok' => false, 'error' => $e->getMessage()]]);

                continue;
            }

            // Extend by the same period the rental was bought on (SMSPool sells fixed day buckets).
            $renewalDays = (int) (data_get($rental->provider_payload, 'create.days')
                ?? data_get($rental->provider_payload, 'quote.days')
                ?? 30);
            $res = $rent->prolong((string) $rental->provider_order_id, 'month', 1, $renewalDays);
            if (($res['ok'] ?? false) !== true) {
                $error = (string) ($res['error'] ?? 'Rental extension failed.');
                try {
                    $wallet->credit((int) $rental->user_id, $amountMinor, 'refund', [
                        'rental_id' => (int) $rental->id,
                        'reference' => $reference,
                        'provider' => $rent->key(),
                        'reason' => $error,
                    ], (int) $payment->id);
                } catch (\Throwable) {
                }

                $this->markPastDue($rental, $error);
                $payment->update(['status' => 'paid_failed_provision_refunded', 'fulfillment_payload' => ['ok' => false, 'error' => $error]]);

                continue;
            }

            $payload = is_array($res['json']['data'] ?? null) ? $res['json']['data'] : [];
            $end = $rent->timestampToCarbon($payload['until'] ?? null) ?: ($rental->current_period_end?->copy() ?? now())->addMonth();

            $rental->status = 'active';
            $rental->renewal_failed_count = 0;
            $rental->last_renewal_error = null;
            $rental->last_charge_reference = $reference;
            $rental->last_renewed_at = now();
            $rental->current_period_start = now();
            $rental->current_period_end = $end;
            $rental->provider_payload = array_merge(is_array($rental->provider_payload) ? $rental->provider_payload : [], ['last_prolong' => $res['json'] ?? null]);
            $rental->save();

            $payment->update(['status' => 'fulfilled', 'fulfillment_payload' => ['ok' => true, 'provider_payload' => $res['json'] ?? null]]);
        }

        $this->info('Processed social rental renewals: '.$due->count());

        return self::SUCCESS;
    }

    private function markPastDue(SocialNumberRental $rental, string $error): void
    {
        $rental->status = 'past_due';
        $rental->renewal_failed_count = (int) $rental->renewal_failed_count + 1;
        $rental->last_renewal_error = $error;
        $rental->save();
    }
}
