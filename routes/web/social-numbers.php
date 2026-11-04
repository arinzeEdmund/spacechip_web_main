<?php

use App\Http\Controllers\Api\SocialNumberController;
use App\Http\Controllers\Api\SocialRentalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Social Numbers (SMSPool)
|--------------------------------------------------------------------------
|
| Two products share one provider:
|   - one-time activations: a number that receives a single OTP (~10 min)
|   - monthly rentals: a dedicated number kept for 30-day periods
|
*/

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::controller(SocialNumberController::class)->prefix('api/social-numbers')->name('social-numbers.')->group(function () {
        Route::get('profile', 'profile')->middleware('throttle:30,1')->name('profile');
        Route::get('services', 'services')->middleware('throttle:30,1')->name('services');
        Route::get('apps', 'apps')->middleware('throttle:30,1')->name('apps');
        Route::get('countries', 'countries')->middleware('throttle:30,1')->name('countries');
        Route::get('operators', 'operators')->middleware('throttle:60,1')->name('operators');
        Route::get('prices', 'prices')->middleware('throttle:30,1')->name('prices');
        Route::post('buy', 'buy')->middleware(['throttle:15,1', 'region.block', 'kyc.gate'])->name('buy');
        Route::get('check/{id}', 'check')->middleware('throttle:60,1')->name('check');
        Route::post('orders/{id}/finish', 'finish')->middleware('throttle:20,1')->name('finish');
        Route::post('orders/{id}/cancel', 'cancel')->middleware('throttle:20,1')->name('cancel');
        Route::post('orders/{id}/ban', 'ban')->middleware('throttle:20,1')->name('ban');
        Route::post('orders/{id}/try-another', 'tryAnother')->middleware('throttle:10,1')->name('try-another');
        Route::get('orders', 'orders')->middleware('throttle:30,1')->name('orders');
    });

    Route::controller(SocialRentalController::class)->prefix('api/social-rentals')->name('social-rentals.')->group(function () {
        Route::get('apps', 'apps')->middleware('throttle:30,1')->name('apps');
        Route::get('countries', 'countries')->middleware('throttle:30,1')->name('countries');
        Route::get('quote', 'quote')->middleware('throttle:30,1')->name('quote');
        Route::post('buy', 'buy')->middleware(['throttle:10,1', 'region.block', 'kyc.gate', 'purchase.waiver'])->name('buy');
        Route::post('{rental}/activate', 'activate')->middleware('throttle:20,1')->name('activate');
        Route::get('{rental}/sms', 'sms')->middleware('throttle:30,1')->name('sms');
        Route::post('{rental}/cancel', 'cancel')->middleware('throttle:20,1')->name('cancel');
        Route::get('/', 'index')->middleware('throttle:30,1')->name('index');
    });
});
