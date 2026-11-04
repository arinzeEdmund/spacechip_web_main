<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WalletTransaction;
use App\Services\Admin\AdminMetrics;
use App\Services\Admin\AuditLogger;
use App\Services\Admin\EsimFulfillmentRetrier;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Admin payments and eSIM orders: filtering, detail, raw JSON, re-running a stuck eSIM
 * fulfilment, and wallet refunds. Every mutating action is audit-logged.
 */
class PaymentController extends Controller
{
    public const ESIM_ASSETS = ['country', 'region', 'global'];

    public function index(Request $request): View
    {
        return $this->listing($request, 'admin.payments.index');
    }

    /** eSIM orders are payments for eSIM assets, shown with provisioning details. */
    public function esims(Request $request): View
    {
        $request->merge(['asset' => 'esim']);

        return $this->listing($request, 'admin.payments.esims');
    }

    private function listing(Request $request, string $view): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $provider = (string) $request->query('provider', '');
        $asset = (string) $request->query('asset', '');
        $from = $request->date('from');
        $to = $request->date('to');

        $base = Payment::query()
            ->when($asset === 'esim', fn ($q) => $q->whereIn('asset_type', self::ESIM_ASSETS))
            ->when($asset !== '' && $asset !== 'esim', fn ($q) => $q->where('asset_type', $asset));

        $payments = (clone $base)->with('user')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('provider_reference', 'like', "%{$q}%")
                ->orWhere('bundle_id', 'like', "%{$q}%")
                ->orWhere('id', ctype_digit($q) ? (int) $q : 0)
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"))))
            ->when($status === 'attention', fn ($q) => AdminMetrics::needsAttention($q))
            ->when($status === 'failed', fn ($q) => $q->where('status', 'like', 'failed%'))
            ->when(! in_array($status, ['', 'attention', 'failed'], true), fn ($q) => $q->where('status', $status))
            ->when($provider !== '', fn ($q) => $q->where('provider', $provider))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->endOfDay()))
            ->latest()->paginate(30)->withQueryString();

        return view($view, [
            'payments' => $payments,
            'filters' => compact('q', 'status', 'provider', 'asset') + ['from' => $from?->toDateString(), 'to' => $to?->toDateString()],
            'statusCounts' => (clone $base)->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
            'providers' => Payment::query()->distinct()->pluck('provider'),
            'assets' => Payment::query()->distinct()->pluck('asset_type'),
            'totals' => [
                'settled' => (int) (clone $base)->whereIn('status', AdminMetrics::SETTLED)->where('currency', 'USD')->sum('amount_minor'),
                'attention' => AdminMetrics::needsAttention(clone $base)->count(),
            ],
        ]);
    }

    public function show(Payment $payment, EsimFulfillmentRetrier $retrier): View
    {
        $payment->load('user');

        return view('admin.payments.show', [
            'payment' => $payment,
            'walletTx' => WalletTransaction::where('payment_id', $payment->id)->latest()->get(),
            'canRetry' => $retrier->canRetry($payment),
            'canRefund' => ! in_array($payment->status, ['refunded', 'failed_refunded', 'initialized', 'failed_insufficient_wallet'], true),
        ]);
    }

    public function json(Payment $payment): JsonResponse
    {
        return response()->json($payment->toArray(), 200, [], JSON_PRETTY_PRINT);
    }

    public function retry(Payment $payment, EsimFulfillmentRetrier $retrier, AuditLogger $audit): RedirectResponse
    {
        try {
            $result = $retrier->retry($payment);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        $audit->log('payment.fulfillment_retried', "Retried fulfilment for payment #{$payment->id}: {$result['message']}", $payment, $result);

        return back()->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    public function refund(Request $request, Payment $payment, WalletService $wallet, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        if (in_array($payment->status, ['refunded', 'failed_refunded'], true)) {
            return back()->with('error', 'This payment was already refunded.');
        }
        $minor = (int) round(((float) $data['amount']) * 100);

        try {
            $wallet->credit((int) $payment->user_id, $minor, 'refund', [
                'reason' => $data['reason'],
                'admin_id' => $request->user()->id,
                'reference' => $payment->provider_reference,
            ], $payment->id);
        } catch (Throwable $e) {
            return back()->with('error', 'Refund failed: '.$e->getMessage());
        }

        $previous = $payment->status;
        $payment->status = 'refunded';
        $payment->save();
        $audit->log('payment.refunded', "Refunded {$wallet->formatUsd($minor)} to wallet for payment #{$payment->id}: {$data['reason']}", $payment, ['amount_minor' => $minor, 'previous_status' => $previous]);

        return back()->with('status', "Refunded {$wallet->formatUsd($minor)} to the customer’s wallet.");
    }
}
