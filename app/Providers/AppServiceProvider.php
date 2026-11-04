<?php

namespace App\Providers;

use App\Models\LoginActivity;
use App\Models\Payment;
use App\Models\User;
use App\Models\VirtualNumberSubscription;
use App\Services\Admin\AdminMetrics;
use App\Services\Admin\LoginRecorder;
use App\Services\Admin\SettingsService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

/**
 * App bootstrapping: admin setting overrides, login activity recording and admin nav badges.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
    }

    public function boot(): void
    {
        $this->defineRateLimiters();

        // New passwords: 10+ characters with letters and numbers; in production also reject known-breached ones.
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->numbers()->uncompromised()
            : Password::min(10)->letters()->numbers());

        // Changing a password signs the account out of every mobile device.
        User::updated(function (User $user) {
            if ($user->wasChanged('password')) {
                $user->tokens()->delete();
            }
        });

        // Admin-edited pricing overrides win over .env defaults.
        $this->app->make(SettingsService::class)->applyConfigOverrides();

        // Session (web / Google / Facebook) logins. API token logins are recorded in their routes.
        Event::listen(Login::class, function (Login $event) {
            if ($event->guard === 'web' && $event->user instanceof User) {
                $channel = request()->is('auth/*') ? 'social' : 'web';
                app(LoginRecorder::class)->success($event->user, request(), $channel);
            }
        });

        // Sidebar badges: things that need an admin's attention.
        View::composer('components.admin.layout', function ($view) {
            $view->with('navCounts', [
                'attention' => AdminMetrics::needsAttention(Payment::query())->count(),
                'past_due' => VirtualNumberSubscription::where('status', 'past_due')->count(),
                'privacy_open' => \App\Models\PrivacyRequest::whereNull('completed_at')->count(),
                'failed_logins' => LoginActivity::where('successful', false)->where('created_at', '>=', now()->subDay())->count(),
            ]);
        });

        Event::listen(Failed::class, function (Failed $event) {
            $user = $event->user instanceof User ? $event->user : null;
            app(LoginRecorder::class)->failure($event->credentials['email'] ?? null, $user, request(), 'web');
        });
    }

    /**
     * Layered limits for the endpoints attackers hammer: sign-up, sign-in and password reset.
     * Every limiter runs BEFORE the controller, so a flood is rejected with HTTP 429 (+ Retry-After) before any
     * database lookup, password hash or email is paid for. They are keyed by client IP (IPv6 grouped by /64),
     * by the email being targeted, and, for the two endpoints that send email, by a site-wide circuit breaker.
     */
    private function defineRateLimiters(): void
    {
        $l = fn (string $key) => (int) config("security.limits.{$key}");
        $email = fn (Request $r) => sha1(mb_strtolower(trim((string) $r->input('email'))));

        RateLimiter::for('signup', fn (Request $r) => [
            Limit::perMinute($l('signup_ip_per_min'))->by('signup-ip:'.self::ipKey($r)),
            Limit::perHour($l('signup_ip_per_hour'))->by('signup-ip-h:'.self::ipKey($r)),
            Limit::perHour($l('signup_email_per_hour'))->by('signup-email:'.$email($r)),
            Limit::perMinute($l('signup_global_per_min'))->by('signup-global'),
        ]);

        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute($l('login_ip_per_min'))->by('login-ip:'.self::ipKey($r)),
            // Slows distributed guessing against one account without letting one stranger lock it for everyone.
            Limit::perHour($l('login_email_per_hour'))->by('login-email:'.$email($r)),
        ]);

        RateLimiter::for('password-reset', fn (Request $r) => [
            Limit::perMinute($l('reset_ip_per_min'))->by('reset-ip:'.self::ipKey($r)),
            Limit::perHour($l('reset_email_per_hour'))->by('reset-email:'.$email($r)),
            Limit::perMinute($l('reset_global_per_min'))->by('reset-global'),
        ]);
    }

    /** Client key: the IPv4 address, or the /64 network for IPv6 (an attacker owns a whole /64 and can rotate within it). */
    public static function ipKey(Request $request): string
    {
        $ip = (string) $request->ip();
        if (str_contains($ip, ':') && ($bin = @inet_pton($ip)) !== false && strlen($bin) === 16) {
            return bin2hex(substr($bin, 0, 8)).'::/64';
        }

        return $ip;
    }
}
