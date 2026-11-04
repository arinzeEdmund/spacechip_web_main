<?php

use App\Http\Controllers\Api\MyEsimController;
use App\Http\Controllers\Esim\CatalogController;
use App\Http\Controllers\Esim\CheckoutController;
use App\Http\Controllers\Payments\CryptomusController;
use App\Http\Controllers\Payments\PaystackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| eSIMs: catalogue, checkout, payment and the user's installed eSIMs
|--------------------------------------------------------------------------
|
| Catalogue and fulfilment are provided by Airalo. GloEsim is a legacy
| provider still used to sync eSIMs bought through it. Payment is via
| Paystack (card), Cryptomus (crypto, web only) or the wallet
| (see routes/web/wallet.php).
|
*/

// Public catalogue
Route::controller(CatalogController::class)->prefix('api')->name('catalog.')->group(function () {
    Route::get('landing', 'landing')->name('landing');
    Route::get('allassets', 'index')->name('index');
    Route::get('assets/{type}/{id}/bundles', 'bundles')->name('bundles');
});

// Checkout
Route::get('/checkout', [CheckoutController::class, 'show'])->middleware('auth')->name('checkout');

// Paystack (card)
Route::controller(PaystackController::class)->prefix('api/paystack')->name('paystack.')->group(function () {
    Route::post('initialize', 'initialize')->middleware(['auth:sanctum', 'throttle:10,1', 'region.block', 'kyc.gate', 'purchase.waiver'])->name('initialize');
    Route::post('verify', 'verify')->middleware(['auth:sanctum', 'throttle:20,1'])->name('verify');
    Route::get('status', 'showStatus')->middleware('auth')->name('status.read');
    Route::post('status', 'status')->middleware(['auth:sanctum', 'throttle:30,1'])->name('status');
});

// Cryptomus (crypto, web only; webhook lives in routes/web/webhooks.php)
Route::controller(CryptomusController::class)->middleware('auth')->name('cryptomus.')->group(function () {
    Route::post('/api/cryptomus/invoice', 'invoice')->middleware(['throttle:10,1', 'region.block', 'kyc.gate', 'purchase.waiver'])->name('invoice');
    Route::get('/crypto/return', 'return')->name('return');
    Route::get('/api/cryptomus/status', 'showStatus')->name('status.read');
    Route::post('/api/cryptomus/status', 'status')->middleware('throttle:30,1')->name('status');
});

// The signed-in user's eSIMs
Route::controller(MyEsimController::class)->prefix('api/my-esims')->middleware('auth:sanctum')->name('myesims.')->group(function () {
    Route::get('/', 'index')->name('list');
    Route::post('sync', 'sync')->middleware('throttle:15,1')->name('sync');
});
