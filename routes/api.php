<?php

use App\Http\Controllers\Api\MobileDiscoveryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (stateless, prefixed with /api)
|--------------------------------------------------------------------------
|
| Only the mobile discovery endpoints live here. The operational /api/*
| endpoints are registered from routes/web/*.php on the "web" middleware
| group so the dashboard (session cookie) and the mobile app (Sanctum
| bearer token) can share them.
|
*/

Route::controller(MobileDiscoveryController::class)->prefix('mobile')->name('api.mobile.')->group(function () {
    Route::get('health', 'health')->name('health');
    Route::get('endpoints', 'endpoints')->name('endpoints');
});
