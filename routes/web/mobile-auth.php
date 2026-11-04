<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile Authentication (Sanctum bearer tokens)
|--------------------------------------------------------------------------
|
| Registration issues no token until the emailed 6-digit OTP is verified.
| The browser equivalents live in routes/auth.php.
|
*/

Route::controller(AuthController::class)->prefix('api/auth')->name('api.auth.')->group(function () {
    Route::post('register', 'register')->middleware(['throttle:signup', 'region.block'])->name('register');
    Route::post('verify-otp', 'verifyOtp')->middleware('throttle:10,1')->name('verify-otp');
    Route::post('resend-otp', 'resendOtp')->middleware('throttle:3,10')->name('resend-otp');
    Route::post('login', 'login')->middleware('throttle:login')->name('login');
    Route::post('forgot-password', 'forgotPassword')->middleware('throttle:password-reset')->name('forgot_password');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', 'logout')->middleware('throttle:30,1')->name('logout');
        Route::get('me', 'me')->middleware('throttle:60,1')->name('me');
        Route::post('resend-verification', 'resendVerification')->middleware('throttle:10,1')->name('resend_verification');
        Route::delete('delete-account', 'deleteAccount')->middleware('throttle:5,1')->name('delete_account');
    });
});
