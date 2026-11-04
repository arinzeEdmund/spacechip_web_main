<?php

use App\Http\Middleware\EnforcePlatformSettings;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Http\Middleware\UseAdminGuard;
use App\Http\Middleware\BlockSanctionedCountries;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\RecordPurchaseWaiver;
use App\Http\Middleware\RequireKycAboveThreshold;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'admin.guard' => UseAdminGuard::class,
            'region.block' => BlockSanctionedCountries::class,
            'kyc.gate' => RequireKycAboveThreshold::class,
            'purchase.waiver' => RecordPurchaseWaiver::class,
            'admin.2fa' => EnsureAdminTwoFactor::class,
            'prevent-back-history' => PreventBackHistory::class,
        ]);
        $middleware->append(SecurityHeaders::class);
        $middleware->redirectGuestsTo(fn ($request) => $request->is('admin', 'admin/*', 'debug/*') ? route('admin.login') : route('login'));
        // Account suspension first, then maintenance / feature switches, on every web and API request.
        $middleware->web(append: [EnsureAccountActive::class, EnforcePlatformSettings::class]);
        $middleware->api(append: [EnsureAccountActive::class, EnforcePlatformSettings::class]);
        $middleware->validateCsrfTokens(except: [
            '/api/auth/*',
            '/csp-report',
            '/api/airalo/webhook',
            '/api/my-esims/*',
            '/api/paystack/*',
            '/api/social-numbers/*',
            '/api/social-rentals/*',
            '/api/twilio/*',
            '/api/virtual-numbers/*',
            '/api/wallet/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
