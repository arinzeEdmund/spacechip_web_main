<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AdminTwoFactor;
use App\Services\Admin\AuditLogger;
use App\Services\Admin\LoginRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Separate admin sign-in (never shared with the customer login):
 * 1. email + password, 2. a code from the authenticator app OR emailed to the admin, 3. the admin session.
 */
class AuthController extends Controller
{
    private const PENDING_MINUTES = 10;

    public function __construct(private AdminTwoFactor $twoFactor, private LoginRecorder $logins, private AuditLogger $audit) {}

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('admin')->check() && session('admin_2fa.user_id') === Auth::guard('admin')->id()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'max:255']]);
        $email = mb_strtolower(trim($data['email']));
        $key = 'admin-login:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Too many attempts. Try again in '.ceil(RateLimiter::availableIn($key) / 60).' minute(s).'])->onlyInput('email');
        }

        $user = User::query()->where('email', $email)->first();
        // Constant-ish work whether or not the account exists, and one generic message for every failure.
        $passwordOk = Hash::check($data['password'], $user ? (string) $user->password : $this->dummyHash());
        if (! $user || ! $passwordOk || ! $user->isAdmin() || $user->isSuspended()) {
            RateLimiter::hit($key, 900);
            $this->logins->failure($email, $user, $request, 'admin');

            return back()->withErrors(['email' => 'These credentials do not match an admin account.'])->onlyInput('email');
        }
        RateLimiter::clear($key);

        $request->session()->regenerate();
        $request->session()->put('admin_pending', ['user_id' => $user->id, 'at' => now()->timestamp]);

        // Admins without an authenticator get an emailed code straight away.
        if (! $this->twoFactor->hasAuthenticator($user)) {
            $this->twoFactor->sendEmailCode($user, $request->ip());
        }

        return redirect()->route('admin.two-factor');
    }

    public function showChallenge(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('admin.login')->withErrors(['email' => 'Please sign in first.']);
        }

        return view('admin.auth.two-factor', [
            'hasAuthenticator' => $this->twoFactor->hasAuthenticator($user),
            'emailMasked' => $this->mask($user->email),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('admin.login')->withErrors(['email' => 'Your sign-in expired. Please start again.']);
        }
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);

        $key = 'admin-2fa:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 6)) {
            $request->session()->forget('admin_pending');

            return redirect()->route('admin.login')->withErrors(['email' => 'Too many wrong codes. Wait a few minutes and sign in again.']);
        }

        if (! $this->twoFactor->verify($user, $data['code'])) {
            RateLimiter::hit($key, 600);
            $this->logins->failure($user->email, $user, $request, 'admin');

            return back()->withErrors(['code' => 'That code is not valid or has expired.']);
        }
        RateLimiter::clear($key);

        Auth::guard('admin')->login($user);
        $request->session()->regenerate();
        $request->session()->forget('admin_pending');
        $request->session()->put('admin_2fa', ['user_id' => $user->id, 'at' => now()->timestamp]);
        $request->session()->put('admin_last_activity', now()->timestamp);

        $this->logins->success($user, $request, 'admin');
        $this->audit->log('admin.signed_in', 'Admin signed in', $user);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('admin.login');
        }
        $sent = $this->twoFactor->sendEmailCode($user, $request->ip());

        return back()->with('status', $sent ? 'We emailed a 6-digit code to '.$this->mask($user->email).'. It works for 10 minutes.' : 'A code was sent recently. Check your inbox or wait a minute.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::guard('admin')->user();
        if ($user) {
            $this->audit->log('admin.signed_out', 'Admin signed out', $user);
        }
        Auth::guard('admin')->logout();
        $request->session()->forget(['admin_2fa', 'admin_pending', 'admin_last_activity']);
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', 'Signed out.');
    }

    /** A real hash to compare against when the account doesn't exist, so timing doesn't reveal which emails are admins. */
    private function dummyHash(): string
    {
        return Cache::rememberForever('admin-login-dummy-hash', fn () => Hash::make(bin2hex(random_bytes(16))));
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = (array) $request->session()->get('admin_pending', []);
        if (! isset($pending['user_id']) || now()->timestamp - (int) ($pending['at'] ?? 0) > self::PENDING_MINUTES * 60) {
            return null;
        }
        $user = User::query()->find($pending['user_id']);

        return $user && $user->isAdmin() && ! $user->isSuspended() ? $user : null;
    }

    private function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 2).str_repeat('•', max(1, mb_strlen($local) - 2)).'@'.$domain;
    }
}
