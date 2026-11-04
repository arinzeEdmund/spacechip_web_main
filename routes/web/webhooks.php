<?php

use App\Http\Controllers\Webhooks\AiraloWebhookController;
use App\Http\Controllers\Webhooks\CryptomusWebhookController;
use App\Http\Controllers\Webhooks\PaystackWebhookController;
use App\Http\Controllers\Webhooks\TwilioWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Provider Webhooks
|--------------------------------------------------------------------------
|
| Unauthenticated, provider-to-server callbacks; CSRF is exempted in
| bootstrap/app.php. Twilio (X-Twilio-Signature) and Cryptomus (sign field)
| are signature-verified in their controllers. The Airalo webhook is not
| yet verified: see "Known gaps" in ARCHITECTURE.md.
|
*/

Route::post('/api/airalo/webhook', AiraloWebhookController::class)->middleware('throttle:60,1')->name('airalo.webhook');
Route::post('/api/paystack/webhook', PaystackWebhookController::class)->middleware('throttle:120,1')->name('paystack.webhook');
Route::post('/api/cryptomus/webhook', CryptomusWebhookController::class)->name('cryptomus.webhook');

Route::controller(TwilioWebhookController::class)->prefix('api/twilio')->name('twilio.')->group(function () {
    Route::post('sms', 'sms')->name('sms');
    Route::post('voice', 'voice')->name('voice');
    Route::post('voice/recording', 'voiceRecording')->name('voice.recording');
});
