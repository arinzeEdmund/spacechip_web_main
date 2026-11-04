<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiraloService;

/**
 * Raw Airalo catalogue responses for troubleshooting (admin only).
 */
class DiagnosticsController extends Controller
{
    public function debugCatalog(AiraloService $airalo)
    {
        $type = request('type', 'local');

        return response()->json($airalo->getRawPackages($type));
    }

    public function debugAsset(AiraloService $airalo)
    {
        $type = request('type', 'country');
        $id = request('id', 'france');

        return response()->json([
            'asset_details' => $airalo->getAssetDetails($type, $id, 'DATA-ONLY'),
            'raw_packages_response' => $airalo->getRawPackages($type === 'region' ? 'regional' : 'local'),
        ]);
    }
}
