<?php

use App\Http\Controllers\Admin\DiagnosticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin diagnostics
|--------------------------------------------------------------------------
|
| Raw Airalo responses for troubleshooting the catalogue. Previously public;
| now admin-only. The admin console itself lives in routes/admin.php.
|
*/

Route::controller(DiagnosticsController::class)->middleware(['admin.guard', 'auth:admin', 'admin.2fa', 'admin'])->group(function () {
    Route::get('/debug/airalo-catalog', 'debugCatalog')->name('admin.debug.airalo-catalog');
    Route::get('/debug/airalo-asset', 'debugAsset')->name('admin.debug.airalo-asset');
});
