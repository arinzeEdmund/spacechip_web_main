<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Break-glass for the admin console: prints a one-time sign-in code (valid 10 minutes) for an admin, for when
 * email delivery is down. It needs shell access to the server, which already implies full control, and every use
 * should be treated as sensitive. Optionally removes a lost authenticator app.
 */
class AdminLoginCode extends Command
{
    protected $signature = 'admin:login-code {email} {--reset-authenticator : Also remove the authenticator app}';

    protected $description = 'Print a one-time admin sign-in code (break-glass when email is unavailable)';

    public function handle(): int
    {
        $user = User::query()->where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();
        if (! $user || ! $user->isAdmin()) {
            $this->error('No admin account with that email.');

            return self::FAILURE;
        }

        if ($this->option('reset-authenticator')) {
            $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null]);
            $this->warn('Authenticator app removed.');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->forceFill([
            'admin_code_hash' => hash_hmac('sha256', $code, (string) config('app.key')),
            'admin_code_expires_at' => now()->addMinutes(10),
            'admin_code_attempts' => 0,
        ])->save();

        $this->info("Code for {$user->email}: {$code}  (valid 10 minutes, single use)");

        return self::SUCCESS;
    }
}
