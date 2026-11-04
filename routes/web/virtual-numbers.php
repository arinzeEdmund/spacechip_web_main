<?php

use App\Http\Controllers\Api\VirtualNumberController;
use App\Http\Controllers\VirtualNumbers\VirtualNumberPageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Virtual Numbers (Twilio, monthly subscriptions)
|--------------------------------------------------------------------------
|
| Route order matters: the literal "my" and "checkout" segments must be
| registered before the two-letter {country} wildcard, which would
| otherwise match "my".
|
*/

// Dashboard pages (plus legacy /virtual-numbers/* URLs that redirect into the dashboard)
Route::controller(VirtualNumberPageController::class)->middleware(['auth', 'verified'])->group(function () {
    Route::get('/virtual-numbers', 'legacyIndex')->name('virtual.index');
    Route::get('/virtual-numbers/checkout', 'legacyCheckout')->name('virtual.checkout');
    Route::get('/virtual-numbers/my', 'legacyMy')->name('virtual.my');
    Route::get('/virtual-numbers/{country}', 'legacyCountry')->where('country', '[A-Za-z]{2}')->name('virtual.country');

    Route::get('/dashboard/virtual-numbers', 'index')->name('dashboard.virtual.index');
    Route::get('/dashboard/virtual-numbers/checkout', 'checkout')->name('dashboard.virtual.checkout');
    Route::get('/dashboard/virtual-numbers/my', 'my')->name('dashboard.virtual.my');
    Route::get('/dashboard/virtual-numbers/{country}', 'country')->where('country', '[A-Za-z]{2}')->name('dashboard.virtual.country');
});

// JSON API
Route::controller(VirtualNumberController::class)
    ->prefix('api/virtual-numbers')
    ->middleware(['auth:sanctum', 'verified'])
    ->name('virtual.')
    ->group(function () {
        Route::get('countries', 'countries')->middleware('throttle:30,1')->name('countries');
        Route::get('country/{country}', 'country')->middleware('throttle:30,1')->name('country.meta');
        Route::get('my', 'my')->middleware('throttle:30,1')->name('my.api');
        Route::get('available', 'available')->middleware('throttle:30,1')->name('available');

        Route::post('paystack/initialize', 'paystackInitialize')->middleware(['throttle:10,1', 'region.block', 'kyc.gate', 'purchase.waiver'])->name('paystack.init');
        Route::post('paystack/verify', 'paystackVerify')->middleware('throttle:20,1')->name('paystack.verify');

        Route::post('{subscription}/cancel', 'cancel')->middleware('throttle:10,1')->name('cancel');
        Route::get('{subscription}/messages', 'messages')->middleware('throttle:30,1')->name('messages');
        Route::post('{subscription}/messages/send', 'sendMessage')->middleware('throttle:10,1')->name('messages.send');
        Route::get('{subscription}/settings', 'settings')->middleware('throttle:30,1')->name('settings.get');
        Route::post('{subscription}/settings', 'updateSettings')->middleware('throttle:10,1')->name('settings.set');

        Route::get('messages/{message}/media/{index}', 'media')->middleware('throttle:60,1')->name('messages.media');
        Route::get('messages/{message}/recording', 'recording')->middleware('throttle:60,1')->name('messages.recording');
    });
