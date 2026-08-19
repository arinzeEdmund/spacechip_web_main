<?php

namespace App\Http\Controllers\Auth;

use App\Mail\OtpVerificationMail;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class OtpVerificationController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('otp_user_id')) {
            return redirect()->route('register');
        }

        return view('auth.verify-otp', [
            'email' => $this->maskedEmail($request),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ]);

        $userId = $request->session()->get('otp_user_id');
        if (! $userId) {
            return redirect()->route('register')->withErrors(['otp' => 'Session expired. Please register again.']);
        }

        $user = User::find($userId);
        if (! $user) {
            return redirect()->route('register')->withErrors(['otp' => 'Account not found. Please register again.']);
        }

        // Already verified — just log in
        if ($user->hasVerifiedEmail()) {
            $request->session()->forget('otp_user_id');
            Auth::login($user);
            return redirect()->intended(route('dashboard', absolute: false));
        }

        if (
            $user->email_otp !== $request->input('otp') ||
            ! $user->email_otp_expires_at ||
            now()->isAfter($user->email_otp_expires_at)
        ) {
            return back()->withErrors(['otp' => 'The code is incorrect or has expired.']);
        }

        // Mark verified and clear OTP
        $user->forceFill([
            'email_verified_at'    => now(),
            'email_otp'            => null,
            'email_otp_expires_at' => null,
        ])->save();

        event(new Verified($user));

        $request->session()->forget('otp_user_id');

        Auth::login($user);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function resend(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('otp_user_id');
        if (! $userId) {
            return redirect()->route('register');
        }

        $rateLimiterKey = 'otp-resend:' . $userId;
        if (RateLimiter::tooManyAttempts($rateLimiterKey, 3)) {
            $seconds = RateLimiter::availableIn($rateLimiterKey);
            return back()->withErrors(['otp' => "Too many resend attempts. Please wait {$seconds} seconds."]);
        }
        RateLimiter::hit($rateLimiterKey, 600);

        $user = User::find($userId);
        if (! $user) {
            return redirect()->route('register');
        }

        $otp = RegisteredUserController::generateAndStoreOtp($user);

        try {
            Mail::to($user->email)->send(new OtpVerificationMail($otp, $user->name));
        } catch (\Throwable) {
        }

        return back()->with('status', 'otp-resent');
    }

    private function maskedEmail(Request $request): string
    {
        $userId = $request->session()->get('otp_user_id');
        $user   = $userId ? User::find($userId) : null;
        if (! $user) {
            return '****';
        }
        [$local, $domain] = explode('@', $user->email, 2);
        $masked = substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 2));
        return $masked . '@' . $domain;
    }
}
