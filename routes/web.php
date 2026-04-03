<?php

use App\Http\Controllers\ProfileController;
use App\Services\GloEsimService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::get('/api/landing', function (GloEsimService $gloEsim) {
    return response()->json([
        'popularCountries' => $gloEsim->popularCountries(6),
        'popularRegions' => $gloEsim->popularRegions(7),
        'searchableAssets' => $gloEsim->searchableAssets(),
    ]);
});

Route::get('/allassets', function () {
    return view('allassets');
});

Route::get('/api/allassets', function (GloEsimService $gloEsim) {
    return response()->json([
        'countriesDataOnly' => $gloEsim->allCountriesWithPrices('DATA-ONLY'),
        'countriesDataCalls' => $gloEsim->allCountriesWithPrices('DATA-VOICE-SMS'),
        'regions' => $gloEsim->allRegionsWithPrices(),
        'virtualNumbers' => $gloEsim->allVirtualNumbers(),
    ]);
});

Route::get('/assets/{type}/{id}', function (string $type, string $id, GloEsimService $gloEsim) {
    $packageType = request('package_type', 'DATA-ONLY');
    $asset = $gloEsim->getAssetDetails($type, $id, $packageType);
    
    if (!$asset) abort(404);

    return view('details', [
        'asset' => $asset,
        'type' => $type,
        'id' => $id,
        'currentPackageType' => $packageType
    ]);
})->name('asset.details');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
