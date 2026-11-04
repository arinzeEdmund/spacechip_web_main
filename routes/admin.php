<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NumbersController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PlatformController;
use App\Http\Controllers\Admin\PrivacyRequestController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WalletController;
use Illuminate\Support\Facades\Route;

/*
| Admin sign-in: its own login page and session (guard `admin`), separate from the customer login.
| Password, then a code from the authenticator app OR emailed to the admin (interchangeable).
*/
Route::middleware('admin.guard')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware(['throttle:10,1', 'turnstile'])->name('login.attempt');
    Route::get('/two-factor', [AuthController::class, 'showChallenge'])->name('two-factor');
    Route::post('/two-factor', [AuthController::class, 'verify'])->middleware('throttle:10,1')->name('two-factor.verify');
    Route::post('/two-factor/email', [AuthController::class, 'sendCode'])->middleware('throttle:5,1')->name('two-factor.email');
});

/*
| Admin console. Every route requires an admin who completed the second step; every write is
| audit-logged and rate limited. Sessions end after 30 idle minutes.
*/
Route::middleware(['admin.guard', 'auth:admin', 'admin.2fa', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/security', [SecurityController::class, 'show'])->name('security');
    Route::post('/security/authenticator/start', [SecurityController::class, 'start'])->middleware('throttle:10,1')->name('security.start');
    Route::post('/security/authenticator/confirm', [SecurityController::class, 'confirm'])->middleware('throttle:10,1')->name('security.confirm');
    Route::post('/security/authenticator/disable', [SecurityController::class, 'disable'])->middleware('throttle:5,1')->name('security.disable');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');

    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/esims', [PaymentController::class, 'esims'])->name('esims');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::get('/payments/{payment}/json', [PaymentController::class, 'json'])->name('payment');

    Route::get('/wallet', WalletController::class)->name('wallet');
    Route::get('/numbers/virtual', [NumbersController::class, 'virtual'])->name('numbers.virtual');
    Route::get('/numbers/social', [NumbersController::class, 'social'])->name('numbers.social');

    Route::get('/providers', [PlatformController::class, 'providers'])->name('providers');
    Route::get('/settings', [PlatformController::class, 'settings'])->name('settings');
    Route::get('/logins', [PlatformController::class, 'logins'])->name('logins');
    Route::get('/audit', [PlatformController::class, 'audit'])->name('audit');
    Route::get('/system', [PlatformController::class, 'system'])->name('system');
    Route::get('/reports', [\App\Http\Controllers\Admin\ReportController::class, 'index'])->name('reports');
    Route::get('/reports/sales.csv', [\App\Http\Controllers\Admin\ReportController::class, 'sales'])->middleware('throttle:10,1')->name('reports.sales');
    Route::get('/privacy-requests', [PrivacyRequestController::class, 'index'])->name('privacy-requests');

    Route::middleware('throttle:60,1')->group(function () {
        Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
        Route::post('/users/{user}/unsuspend', [UserController::class, 'unsuspend'])->name('users.unsuspend');
        Route::post('/users/{user}/verify-email', [UserController::class, 'verifyEmail'])->name('users.verify');
        Route::post('/users/{user}/toggle-admin', [UserController::class, 'toggleAdmin'])->name('users.toggle-admin');
        Route::post('/users/{user}/kyc', [UserController::class, 'kyc'])->name('users.kyc');
        Route::post('/users/{user}/revoke-sessions', [UserController::class, 'revokeSessions'])->name('users.revoke-sessions');
        Route::post('/users/{user}/wallet', [UserController::class, 'adjustWallet'])->name('users.wallet');

        Route::post('/payments/{payment}/retry', [PaymentController::class, 'retry'])->name('payments.retry');
        Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund');

        Route::post('/numbers/virtual/{subscription}/cancel', [NumbersController::class, 'cancelVirtual'])->name('numbers.virtual.cancel');
        Route::post('/numbers/social/{order}/cancel', [NumbersController::class, 'cancelSocialOrder'])->name('numbers.social.cancel');
        Route::post('/numbers/rentals/{rental}/cancel', [NumbersController::class, 'cancelRental'])->name('numbers.rentals.cancel');

        Route::post('/providers/refresh', [PlatformController::class, 'refreshProviders'])->name('providers.refresh');
        Route::put('/settings', [PlatformController::class, 'updateSettings'])->name('settings.update');
        Route::post('/privacy-requests/{privacyRequest}/complete', [PrivacyRequestController::class, 'complete'])->name('privacy-requests.complete');
        Route::post('/system/retry-jobs', [PlatformController::class, 'retryFailedJobs'])->name('system.retry-jobs');
        Route::post('/system/clear-cache', [PlatformController::class, 'clearCache'])->name('system.clear-cache');
    });
});
