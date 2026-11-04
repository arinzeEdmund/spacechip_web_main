<?php

namespace App\Services\Admin;

use App\Models\Payment;
use App\Models\SocialNumberOrder;
use App\Models\SocialNumberRental;
use App\Models\User;
use App\Models\VirtualNumberSubscription;
use App\Models\WalletTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Aggregations behind the admin overview. All money is USD minor units unless stated. */
class AdminMetrics
{
    /** Payment statuses that represent money actually collected. */
    public const SETTLED = ['paid', 'fulfilled', 'paid_pending_fulfillment'];

    public const ESIM_ASSETS = ['country', 'region', 'global'];

    /**
     * Customer paid but has not received the product. For eSIMs "paid" is an
     * in-between state (fulfilment never completed); for numbers "paid" is final.
     */
    public static function needsAttention(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q
            ->where('status', 'paid_pending_fulfillment')
            ->orWhere(fn ($q) => $q->where('status', 'paid')->whereIn('asset_type', self::ESIM_ASSETS)));
    }

    public function __construct(private SettingsService $settings) {}

    public function kpis(): array
    {
        $now = CarbonImmutable::now();
        $revenue = fn (?CarbonImmutable $from) => (int) Payment::query()
            ->whereIn('status', self::SETTLED)
            ->where('currency', 'USD')
            ->where('asset_type', '!=', 'wallet_deposit')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->sum('amount_minor');

        $walletSpend = fn (?CarbonImmutable $from) => (int) WalletTransaction::query()
            ->where('direction', 'debit')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->sum('amount_minor');

        $walletRefunds = fn (?CarbonImmutable $from) => (int) WalletTransaction::query()
            ->where('direction', 'credit')->where('action', 'refund')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->sum('amount_minor');

        $stale = $now->subMinutes((int) $this->settings->get('stale_payment_minutes'));

        return [
            'revenue_today' => $revenue($now->startOfDay()),
            'revenue_7d' => $revenue($now->subDays(7)),
            'revenue_30d' => $revenue($now->subDays(30)),
            'revenue_all' => $revenue(null),
            'wallet_spend_30d' => $walletSpend($now->subDays(30)),
            'wallet_refunds_30d' => $walletRefunds($now->subDays(30)),
            'wallet_float' => (int) User::sum('wallet_balance_minor'),
            'wallets_funded' => User::where('wallet_balance_minor', '>', 0)->count(),

            'users_total' => User::count(),
            'users_verified' => User::whereNotNull('email_verified_at')->count(),
            'users_new_7d' => User::where('created_at', '>=', $now->subDays(7))->count(),
            'users_suspended' => User::whereNotNull('suspended_at')->count(),
            'users_admins' => User::where('is_admin', true)->count(),
            'users_active_24h' => User::where('last_login_at', '>=', $now->subDay())->count(),

            'payments_total' => Payment::count(),
            'payments_settled' => Payment::whereIn('status', self::SETTLED)->count(),
            'payments_failed' => Payment::where('status', 'like', 'failed%')->count(),
            'payments_abandoned' => Payment::where('status', 'initialized')->count(),
            'payments_stuck' => self::needsAttention(Payment::query())->where('updated_at', '<', $stale)->count(),

            'esims_sold' => Payment::whereIn('asset_type', ['country', 'region', 'global'])->whereIn('status', self::SETTLED)->count(),
            'virtual_active' => VirtualNumberSubscription::where('status', 'active')->count(),
            'virtual_past_due' => VirtualNumberSubscription::where('status', 'past_due')->count(),
            'social_orders_total' => SocialNumberOrder::count(),
            'social_orders_received' => SocialNumberOrder::whereIn('status', ['RECEIVED', 'FINISHED'])->count(),
            'social_orders_open' => SocialNumberOrder::whereNotIn('status', ['RECEIVED', 'FINISHED', 'CANCELED', 'BANNED', 'TIMEOUT'])->count(),
            'rentals_active' => SocialNumberRental::where('status', 'active')->count(),
        ];
    }

    /** Daily series for the last $days days: revenue (USD minor) and sign-ups. */
    public function dailySeries(int $days = 30): Collection
    {
        $from = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        $revenue = Payment::query()
            ->selectRaw('DATE(created_at) d, SUM(amount_minor) v')
            ->whereIn('status', self::SETTLED)->where('currency', 'USD')->where('asset_type', '!=', 'wallet_deposit')
            ->where('created_at', '>=', $from)
            ->groupBy(DB::raw('DATE(created_at)'))->pluck('v', 'd');

        $signups = User::query()
            ->selectRaw('DATE(created_at) d, COUNT(*) v')
            ->where('created_at', '>=', $from)
            ->groupBy(DB::raw('DATE(created_at)'))->pluck('v', 'd');

        return collect(range(0, $days - 1))->map(function (int $i) use ($from, $revenue, $signups) {
            $day = $from->addDays($i)->toDateString();

            return ['date' => $day, 'revenue' => (int) ($revenue[$day] ?? 0), 'signups' => (int) ($signups[$day] ?? 0)];
        });
    }

    /** Revenue split by product line over the last 30 days. */
    public function revenueMix(): Collection
    {
        $labels = ['country' => 'eSIM · country', 'region' => 'eSIM · region', 'global' => 'eSIM · global', 'social_number' => 'Social numbers', 'social_number_rental' => 'Number rentals', 'virtual_number' => 'Virtual numbers'];

        return Payment::query()
            ->selectRaw('asset_type, COUNT(*) c, SUM(amount_minor) v')
            ->whereIn('status', self::SETTLED)->where('currency', 'USD')->where('asset_type', '!=', 'wallet_deposit')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('asset_type')->orderByDesc('v')->get()
            ->map(fn ($r) => ['label' => $labels[$r->asset_type] ?? ucfirst(str_replace('_', ' ', (string) $r->asset_type)), 'count' => (int) $r->c, 'amount' => (int) $r->v]);
    }

    public function paymentStatusBreakdown(): Collection
    {
        return Payment::query()->selectRaw('status, COUNT(*) c')->groupBy('status')->orderByDesc('c')->pluck('c', 'status');
    }
}
