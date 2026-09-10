<?php

namespace App\Console\Commands;

use App\Models\SocialNumberOrder;
use App\Models\SocialNumberRental;
use App\Services\WalletService;
use Illuminate\Console\Command;

/**
 * One-off cutover command: SMSPVA has been removed, so any still-open holding
 * bought through it can no longer receive SMS. Close them and refund the user.
 *
 *   php artisan smspva:refund-legacy --dry-run
 *   php artisan smspva:refund-legacy
 */
class RefundLegacySmsPvaHoldings extends Command
{
    protected $signature = 'smspva:refund-legacy {--dry-run : Report what would happen without touching wallets}';

    protected $description = 'Close and refund open SMSPVA social numbers / rentals after the SMSPool cutover';

    public function handle(WalletService $wallet): int
    {
        $dry = (bool) $this->option('dry-run');

        $orders = SocialNumberOrder::query()
            ->where('provider', 'like', 'smspva%')
            ->whereIn('status', ['PENDING', 'WAITING', 'RECEIVED'])
            ->whereNull('refunded_at')
            ->get();

        $orderRefunded = 0;
        foreach ($orders as $order) {
            $amount = (int) $order->sell_amount_minor;
            $hasCode = trim((string) $order->sms_code) !== '';
            $this->line(sprintf('order #%d user=%d status=%s amount=%d%s', $order->id, $order->user_id, $order->status, $amount, $hasCode ? ' (code received — no refund)' : ''));

            if ($dry) {
                continue;
            }

            if (! $hasCode && $amount > 0) {
                $wallet->credit((int) $order->user_id, $amount, 'refund', [
                    'reason' => 'SMSPVA retired — number closed on provider migration.',
                    'provider' => 'smspva',
                    'social_number_order_id' => $order->id,
                    'provider_order_id' => $order->provider_order_id,
                ], $order->payment_id ? (int) $order->payment_id : null);
                $order->refunded_at = now();
                if ($order->payment) {
                    $order->payment->status = 'refunded';
                    $order->payment->save();
                }
                $orderRefunded++;
            }

            if (! in_array((string) $order->status, ['FINISHED', 'CANCELED', 'BANNED', 'TIMEOUT'], true)) {
                $order->status = $hasCode ? 'FINISHED' : 'CANCELED';
                $order->canceled_at = $order->canceled_at ?: now();
            }
            $order->save();
        }

        $rentals = SocialNumberRental::query()
            ->where('provider', 'like', 'smspva%')
            ->whereIn('status', ['active', 'pending_activation', 'past_due'])
            ->get();

        $rentalRefunded = 0;
        foreach ($rentals as $rental) {
            $amount = (int) $rental->monthly_amount_minor;
            $inPeriod = $rental->current_period_end && $rental->current_period_end->isFuture();
            $this->line(sprintf('rental #%d user=%d status=%s amount=%d%s', $rental->id, $rental->user_id, $rental->status, $amount, $inPeriod ? ' (within paid period — refunding)' : ''));

            if ($dry) {
                continue;
            }

            if ($inPeriod && $amount > 0) {
                $wallet->credit((int) $rental->user_id, $amount, 'refund', [
                    'reason' => 'SMSPVA retired — monthly rental ended on provider migration.',
                    'provider' => 'smspva_rent',
                    'social_number_rental_id' => $rental->id,
                    'provider_order_id' => $rental->provider_order_id,
                ], $rental->payment_id ? (int) $rental->payment_id : null);
                $rentalRefunded++;
            }

            $rental->auto_renew = false;
            $rental->status = 'canceled';
            $rental->canceled_at = $rental->canceled_at ?: now();
            $rental->save();
        }

        $this->info(sprintf(
            '%s%d orders (%d refunded), %d rentals (%d refunded).',
            $dry ? 'DRY RUN — would process ' : 'Processed ',
            $orders->count(),
            $orderRefunded,
            $rentals->count(),
            $rentalRefunded,
        ));

        return self::SUCCESS;
    }
}
