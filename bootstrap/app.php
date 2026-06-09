<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\PreventBackHistory;
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
            'prevent-back-history' => PreventBackHistory::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            '/api/auth/*',
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
