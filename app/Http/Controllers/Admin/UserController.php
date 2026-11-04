<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Admin customer management: search, profile, suspend, verify, admin role, session revoke
 * and manual wallet adjustments. Every action is audit-logged.
 */
class UserController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $filter = (string) $request->query('filter', 'all');
        $sort = (string) $request->query('sort', 'newest');

        $users = User::query()
            ->withCount(['payments', 'socialNumberOrders', 'virtualNumberSubscriptions'])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")
                ->orWhere('id', ctype_digit($q) ? (int) $q : 0)))
            ->when($filter === 'verified', fn ($q) => $q->whereNotNull('email_verified_at'))
            ->when($filter === 'unverified', fn ($q) => $q->whereNull('email_verified_at'))
            ->when($filter === 'suspended', fn ($q) => $q->whereNotNull('suspended_at'))
            ->when($filter === 'admins', fn ($q) => $q->where('is_admin', true))
            ->when($filter === 'funded', fn ($q) => $q->where('wallet_balance_minor', '>', 0))
            ->when($sort === 'newest', fn ($q) => $q->latest())
            ->when($sort === 'oldest', fn ($q) => $q->oldest())
            ->when($sort === 'balance', fn ($q) => $q->orderByDesc('wallet_balance_minor'))
            ->when($sort === 'last_login', fn ($q) => $q->orderByDesc('last_login_at'))
            ->paginate(25)->withQueryString();

        $counts = [
            'all' => User::count(),
            'verified' => User::whereNotNull('email_verified_at')->count(),
            'unverified' => User::whereNull('email_verified_at')->count(),
            'suspended' => User::whereNotNull('suspended_at')->count(),
            'admins' => User::where('is_admin', true)->count(),
            'funded' => User::where('wallet_balance_minor', '>', 0)->count(),
        ];

        return view('admin.users.index', compact('users', 'q', 'filter', 'sort', 'counts'));
    }

    public function show(User $user): View
    {
        $user->loadCount(['payments', 'socialNumberOrders', 'socialNumberRentals', 'virtualNumberSubscriptions']);

        return view('admin.users.show', [
            'user' => $user,
            'payments' => $user->payments()->latest()->limit(50)->get(),
            'wallet' => $user->walletTransactions()->latest()->limit(50)->get(),
            'virtualSubs' => $user->virtualNumberSubscriptions()->with('product')->latest()->get(),
            'socialOrders' => $user->socialNumberOrders()->latest()->limit(50)->get(),
            'rentals' => $user->socialNumberRentals()->latest()->get(),
            'logins' => $user->loginActivities()->latest('created_at')->limit(30)->get(),
            'tokens' => $user->tokens()->latest()->get(),
            'audit' => AdminAuditLog::with('admin')->where('subject_type', 'User')->where('subject_id', $user->id)->latest('created_at')->limit(30)->get(),
            'lifetimeSpend' => (int) $user->payments()->whereIn('status', ['paid', 'fulfilled', 'paid_pending_fulfillment'])->where('currency', 'USD')->where('asset_type', '!=', 'wallet_deposit')->sum('amount_minor'),
        ]);
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot suspend your own account.');
        }
        $user->forceFill(['suspended_at' => now(), 'suspension_reason' => $data['reason']])->save();
        $user->tokens()->delete();
        $this->audit->log('user.suspended', "Suspended {$user->email}: {$data['reason']}", $user, ['reason' => $data['reason']]);

        return back()->with('status', "{$user->name} has been suspended and signed out of the mobile app.");
    }

    public function unsuspend(User $user): RedirectResponse
    {
        $user->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();
        $this->audit->log('user.unsuspended', "Reinstated {$user->email}", $user);

        return back()->with('status', "{$user->name} can sign in again.");
    }

    public function verifyEmail(User $user): RedirectResponse
    {
        if (! $user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => now(), 'email_otp' => null, 'email_otp_expires_at' => null])->save();
            $this->audit->log('user.email_verified', "Manually verified {$user->email}", $user);
        }

        return back()->with('status', 'Email marked as verified.');
    }

    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot change your own admin access.');
        }
        $user->forceFill(['is_admin' => ! $user->is_admin])->save();
        $this->audit->log($user->is_admin ? 'user.admin_granted' : 'user.admin_revoked', ($user->is_admin ? 'Granted' : 'Revoked')." admin access for {$user->email}", $user);

        return back()->with('status', $user->is_admin ? 'Admin access granted.' : 'Admin access revoked.');
    }

    /** Identity-check decision, made after the admin has checked ID outside the app. */
    public function kyc(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:none,required,approved'], 'note' => ['nullable', 'string', 'max:255']]);
        $user->forceFill(['kyc_status' => $data['status'], 'kyc_note' => $data['note'] ?? null, 'kyc_decided_at' => now()])->save();
        $this->audit->log('user.kyc_'.$data['status'], "Identity check for {$user->email} set to {$data['status']}", $user, ['note' => $data['note'] ?? null]);

        return back()->with('status', 'Identity check status updated.');
    }

    public function revokeSessions(User $user): RedirectResponse
    {
        $count = $user->tokens()->count();
        $user->tokens()->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        $this->audit->log('user.sessions_revoked', "Revoked {$count} API token(s) and remember-me for {$user->email}", $user, ['tokens' => $count]);

        return back()->with('status', "Signed out of {$count} device(s).");
    }

    public function adjustWallet(Request $request, User $user, WalletService $wallet): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $minor = (int) round(((float) $data['amount']) * 100);
        $meta = ['reason' => $data['reason'], 'admin_id' => $request->user()->id, 'admin_email' => $request->user()->email];

        try {
            $data['direction'] === 'credit'
                ? $wallet->credit($user->id, $minor, 'admin_credit', $meta)
                : $wallet->debit($user->id, $minor, 'admin_debit', $meta);
        } catch (Throwable $e) {
            return back()->with('error', 'Wallet not changed: '.$e->getMessage());
        }

        $formatted = $wallet->formatUsd($minor);
        $this->audit->log("wallet.{$data['direction']}", ucfirst($data['direction'])."ed {$formatted} to {$user->email}: {$data['reason']}", $user, ['amount_minor' => $minor, 'reason' => $data['reason']]);

        return back()->with('status', ucfirst($data['direction'])."ed {$formatted}. New balance: ".$wallet->formatUsd($wallet->balanceMinor($user->id)).'.');
    }
}
