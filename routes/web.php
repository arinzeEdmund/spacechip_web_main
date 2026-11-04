<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Esim\CatalogController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Browser-facing pages live here. JSON endpoints consumed by the dashboard
| and the mobile app are split by domain under routes/web/ and loaded below.
| They stay on the "web" middleware group on purpose: the dashboard calls
| them with its session cookie while the mobile app uses Sanctum bearer
| tokens, and `auth:sanctum` accepts both.
|
*/

// Marketing & legal pages
Route::view('/', 'landing')->name('home');
Route::view('/contact-us', 'pages.contact')->name('contact');
Route::view('/terms', 'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/help-center', 'pages.help')->name('help');
Route::view('/esim-guide', 'pages.esim-guide')->name('esim.guide');
Route::view('/delete-account', 'pages.delete-account')->name('delete.account');
Route::post('/csp-report', \App\Http\Controllers\CspReportController::class)->middleware('throttle:30,1')->name('csp.report');
Route::view('/refund-policy', 'pages.legal.refund')->name('refund');
Route::view('/acceptable-use', 'pages.legal.acceptable-use')->name('acceptable.use');
Route::view('/cookie-policy', 'pages.legal.cookies')->name('cookies');
Route::view('/accessibility', 'pages.legal.accessibility')->name('accessibility');
Route::get('/privacy/request', [\App\Http\Controllers\PrivacyRequestController::class, 'create'])->name('privacy.request');
Route::post('/privacy/request', [\App\Http\Controllers\PrivacyRequestController::class, 'store'])->middleware('throttle:5,60')->name('privacy.request.store');

// eSIM catalogue pages
Route::view('/allassets', 'allassets')->name('assets.index');
Route::get('/assets/{type}/{id}', [CatalogController::class, 'show'])->name('asset.details');

// Signed-in area
Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->controller(ProfileController::class)->group(function () {
    Route::get('/profile', 'edit')->name('profile.edit');
    Route::patch('/profile', 'update')->name('profile.update');
    Route::delete('/profile', 'destroy')->name('profile.destroy');
});

require __DIR__.'/web/mobile-auth.php';
require __DIR__.'/web/esims.php';
require __DIR__.'/web/wallet.php';
require __DIR__.'/web/virtual-numbers.php';
require __DIR__.'/web/social-numbers.php';
require __DIR__.'/web/webhooks.php';
require __DIR__.'/web/diagnostics.php';
require __DIR__.'/admin.php';
require __DIR__.'/auth.php';
