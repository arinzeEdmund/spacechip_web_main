<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin wallet ledger across all customers.
 */
class WalletController extends Controller
{
    public function __invoke(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $direction = (string) $request->query('direction', '');
        $action = (string) $request->query('action', '');

        $transactions = WalletTransaction::query()->with('user')
            ->when($q !== '', fn ($query) => $query->whereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")))
            ->when(in_array($direction, ['credit', 'debit'], true), fn ($q) => $q->where('direction', $direction))
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->latest()->paginate(40)->withQueryString();

        return view('admin.wallet', [
            'transactions' => $transactions,
            'filters' => compact('q', 'direction', 'action'),
            'actions' => WalletTransaction::query()->distinct()->orderBy('action')->pluck('action'),
            'summary' => [
                'float' => (int) User::sum('wallet_balance_minor'),
                'credits_30d' => (int) WalletTransaction::where('direction', 'credit')->where('created_at', '>=', now()->subDays(30))->sum('amount_minor'),
                'debits_30d' => (int) WalletTransaction::where('direction', 'debit')->where('created_at', '>=', now()->subDays(30))->sum('amount_minor'),
                'refunds_30d' => (int) WalletTransaction::where('action', 'refund')->where('created_at', '>=', now()->subDays(30))->sum('amount_minor'),
                'admin_adjustments' => WalletTransaction::whereIn('action', ['admin_credit', 'admin_debit'])->count(),
            ],
            'topHolders' => User::where('wallet_balance_minor', '>', 0)->orderByDesc('wallet_balance_minor')->limit(5)->get(),
        ]);
    }
}
