<?php

use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Wallet
|--------------------------------------------------------------------------
|
| A USD-denominated balance (stored in cents) that users top up via
| Paystack and spend on eSIMs, virtual numbers and social numbers. All
| ledger writes go through App\Services\WalletService.
|
*/

Route::controller(WalletController::class)
    ->prefix('api/wallet')
    ->middleware(['auth:sanctum', 'verified'])
    ->name('wallet.')
    ->group(function () {
        Route::get('balance', 'balance')->middleware('throttle:30,1')->name('balance');
        Route::get('transactions', 'transactions')->middleware('throttle:30,1')->name('transactions');

        Route::post('deposit/paystack/initialize', 'depositInitialize')->middleware(['throttle:10,1', 'region.block', 'kyc.gate'])->name('deposit.paystack.init');
        Route::post('deposit/paystack/verify', 'depositVerify')->middleware('throttle:20,1')->name('deposit.paystack.verify');

        Route::post('pay/esim', 'payEsim')->middleware(['throttle:10,1', 'region.block', 'kyc.gate', 'purchase.waiver'])->name('pay.esim');
        Route::post('pay/virtual-number', 'payVirtualNumber')->middleware(['throttle:10,1', 'region.block', 'kyc.gate', 'purchase.waiver'])->name('pay.virtual_number');
    });
