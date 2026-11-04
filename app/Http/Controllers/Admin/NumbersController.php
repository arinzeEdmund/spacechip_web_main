<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialNumberOrder;
use App\Models\SocialNumberRental;
use App\Models\VirtualNumberMessage;
use App\Models\VirtualNumberProduct;
use App\Models\VirtualNumberSubscription;
use App\Services\Admin\AuditLogger;
use App\Services\SmsPoolService;
use App\Services\TwilioService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Admin views of virtual-number subscriptions and social-number orders/rentals, with
 * cancel actions (social cancels refund the customer when no code was received).
 */
class NumbersController extends Controller
{
    private const SOCIAL_CLOSED = ['FINISHED', 'CANCELED', 'BANNED', 'TIMEOUT'];

    public function __construct(private AuditLogger $audit) {}

    public function virtual(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        return view('admin.numbers.virtual', [
            'subscriptions' => VirtualNumberSubscription::query()->with(['user', 'product'])->withCount('messages')
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('phone_number', 'like', "%{$q}%")->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%"))))
                ->latest()->paginate(25)->withQueryString(),
            'statusCounts' => VirtualNumberSubscription::query()->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
            'products' => VirtualNumberProduct::query()->withCount('subscriptions')->orderBy('country_iso')->get(),
            'messages' => VirtualNumberMessage::query()->with('subscription.user')->latest()->limit(15)->get(),
            'mrr' => (int) VirtualNumberSubscription::where('status', 'active')->where('currency', 'USD')->sum('monthly_amount_minor'),
            'filters' => compact('status', 'q'),
        ]);
    }

    public function social(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        return view('admin.numbers.social', [
            'orders' => SocialNumberOrder::query()->with('user')
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('phone', 'like', "%{$q}%")->orWhere('product_name', 'like', "%{$q}%")->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%"))))
                ->latest()->paginate(25, ['*'], 'orders_page')->withQueryString(),
            'rentals' => SocialNumberRental::query()->with('user')->latest()->paginate(15, ['*'], 'rentals_page')->withQueryString(),
            'statusCounts' => SocialNumberOrder::query()->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
            'economics' => [
                'revenue' => (int) SocialNumberOrder::whereIn('status', ['RECEIVED', 'FINISHED'])->sum('sell_amount_minor'),
                'cost' => (int) SocialNumberOrder::whereIn('status', ['RECEIVED', 'FINISHED'])->sum('provider_cost_minor'),
                'refunded' => (int) SocialNumberOrder::whereNotNull('refunded_at')->sum('sell_amount_minor'),
                'success_rate' => ($total = SocialNumberOrder::count()) > 0 ? round(SocialNumberOrder::whereIn('status', ['RECEIVED', 'FINISHED'])->count() / $total * 100, 1) : null,
            ],
            'topProducts' => SocialNumberOrder::query()->selectRaw('product_name, COUNT(*) c')->groupBy('product_name')->orderByDesc('c')->limit(6)->pluck('c', 'product_name'),
            'filters' => compact('status', 'q'),
            'closed' => self::SOCIAL_CLOSED,
        ]);
    }

    public function cancelVirtual(VirtualNumberSubscription $subscription, TwilioService $twilio): RedirectResponse
    {
        if ($subscription->status === 'canceled') {
            return back()->with('error', 'Already canceled.');
        }
        $released = false;
        if ($sid = (string) $subscription->twilio_phone_number_sid) {
            try {
                $twilio->releaseNumber($sid);
                $released = true;
            } catch (Throwable $e) {
                return back()->with('error', 'Twilio could not release the number: '.$e->getMessage());
            }
        }
        $subscription->forceFill(['status' => 'canceled', 'canceled_at' => now()])->save();
        $this->audit->log('virtual_number.canceled', "Canceled virtual number {$subscription->phone_number} (#{$subscription->id})", $subscription, ['released' => $released]);

        return back()->with('status', 'Subscription canceled'.($released ? ' and number released.' : '.'));
    }

    public function cancelSocialOrder(SocialNumberOrder $order, SmsPoolService $smsPool, WalletService $wallet): RedirectResponse
    {
        if (in_array($order->status, self::SOCIAL_CLOSED, true)) {
            return back()->with('error', 'This order is already closed.');
        }
        try {
            $smsPool->release((string) $order->provider_order_id);
        } catch (Throwable) {
            // Provider-side cancel is best effort; the customer is still refunded below.
        }

        $refunded = false;
        if (! $order->refunded_at && (int) $order->sell_amount_minor > 0 && trim((string) $order->sms_code) === '') {
            $wallet->credit((int) $order->user_id, (int) $order->sell_amount_minor, 'refund', [
                'reason' => 'Canceled by admin',
                'social_number_order_id' => $order->id,
                'provider_order_id' => $order->provider_order_id,
            ], $order->payment_id ? (int) $order->payment_id : null);
            $order->refunded_at = now();
            $order->payment?->forceFill(['status' => 'refunded'])->save();
            $refunded = true;
        }
        $order->forceFill(['status' => 'CANCELED', 'canceled_at' => now()])->save();
        $this->audit->log('social_order.canceled', "Canceled social number order #{$order->id}".($refunded ? ' and refunded wallet' : ''), $order, ['refunded' => $refunded]);

        return back()->with('status', 'Order canceled'.($refunded ? ' and refunded to wallet.' : '.'));
    }

    public function cancelRental(SocialNumberRental $rental): RedirectResponse
    {
        $rental->auto_renew = false;
        if (! in_array($rental->status, ['canceled', 'expired'], true)) {
            $rental->status = 'canceled';
            $rental->canceled_at = now();
        }
        $rental->save();
        $this->audit->log('rental.canceled', "Canceled rental #{$rental->id} ({$rental->phone})", $rental);

        return back()->with('status', 'Rental canceled; it will not renew.');
    }
}
