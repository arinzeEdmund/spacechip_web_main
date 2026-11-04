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
use Illuminate\Support\Facades\Event;
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
}
