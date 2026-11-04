<?php

namespace App\Services\Admin;

use App\Mail\AdminLoginCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Second step of admin sign-in. Two methods are interchangeable: the authenticator app (if enrolled) or a
 * one-time code emailed to the admin. Either one satisfies the challenge.
 */
class AdminTwoFactor
{
    public const CODE_TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public function __construct(private Totp $totp) {}

    public function hasAuthenticator(User $user): bool
    {
        return $user->two_factor_secret && $user->two_factor_confirmed_at;
    }

    /** Emails a fresh code. Returns false when rate limited (1/minute, 6/hour). */
    public function sendEmailCode(User $user, ?string $ip = null): bool
    {
        $key = 'admin-code:'.$user->id;
        if (RateLimiter::tooManyAttempts($key.':min', 1) || RateLimiter::tooManyAttempts($key.':hour', 6)) {
            return false;
        }
        RateLimiter::hit($key.':min', 60);
        RateLimiter::hit($key.':hour', 3600);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->forceFill([
            'admin_code_hash' => $this->hash($code),
            'admin_code_expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'admin_code_attempts' => 0,
        ])->save();

        Mail::to($user->email)->send(new AdminLoginCodeMail($code, (string) $user->name, $ip));

        return true;
    }

    /** True if the code is a valid authenticator code OR a valid emailed code. Consumed/replay-protected. */
    public function verify(User $user, string $input): bool
    {
        $input = preg_replace('/\D+/', '', $input) ?? '';
        if (strlen($input) !== 6) {
            return false;
        }

        if ($this->hasAuthenticator($user)) {
            $step = $this->totp->verify((string) $user->two_factor_secret, $input);
            if ($step !== null && $step > (int) $user->two_factor_last_step) {
                $user->forceFill(['two_factor_last_step' => $step])->save();

                return true;
            }
        }

        return $this->verifyEmailCode($user, $input);
    }

    private function verifyEmailCode(User $user, string $input): bool
    {
        if (! $user->admin_code_hash || ! $user->admin_code_expires_at || $user->admin_code_expires_at->isPast()) {
            return false;
        }
        if ((int) $user->admin_code_attempts >= self::MAX_ATTEMPTS) {
            return false;
        }
        if (hash_equals((string) $user->admin_code_hash, $this->hash($input))) {
            $user->forceFill(['admin_code_hash' => null, 'admin_code_expires_at' => null, 'admin_code_attempts' => 0])->save();

            return true;
        }
        $user->forceFill(['admin_code_attempts' => (int) $user->admin_code_attempts + 1])->save();

        return false;
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
