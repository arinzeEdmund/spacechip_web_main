<?php

namespace App\Http\Controllers\Esim;

use App\Http\Controllers\Controller;
use App\Models\VirtualNumberProduct;
use App\Services\AiraloService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Public eSIM catalogue backed by Airalo: landing highlights, the
 * searchable country/region list, and bundle details.
 */
class CatalogController extends Controller
{
    public function landing(AiraloService $airalo)
    {
        return response()->json([
            'popularCountries' => $airalo->popularCountries(6),
            'popularRegions' => $airalo->popularRegions(7),
            'searchableAssets' => $airalo->searchableAssets(),
        ]);
    }

    /**
     * Without ?tab, returns every list at once (mobile app). With ?tab=countries|regions|virtual,
     * returns one list filtered by ?q and paged by ?page / ?per_page (10–60) for infinite scroll.
     */
    public function index(AiraloService $airalo)
    {
        $tab = (string) request('tab', '');

        if ($tab === '') {
            return response()->json([
                'countries' => $airalo->allCountriesWithPrices('DATA-ONLY'),
                'regions' => $airalo->allRegionsWithPrices(),
                'virtualNumbers' => $this->virtualNumberItems(),
            ]);
        }

        $tab = in_array($tab, ['countries', 'regions', 'virtual'], true) ? $tab : 'countries';
        $page = max(1, (int) request('page', 1));
        $perPage = max(10, min(60, (int) request('per_page', 30)));
        $q = mb_strtolower(trim((string) request('q', '')));

        $items = match ($tab) {
            'countries' => $airalo->allCountriesWithPrices('DATA-ONLY'),
            'regions' => $airalo->allRegionsWithPrices(),
            'virtual' => $this->virtualNumberItems(),
        };

        if ($q !== '') {
            $items = array_values(array_filter(
                $items,
                fn ($item) => str_contains(mb_strtolower((string) data_get($item, 'name', '')), $q),
            ));
        }

        $offset = ($page - 1) * $perPage;
        $slice = array_slice($items, $offset, $perPage);

        return response()->json([
            'tab' => $tab,
            'page' => $page,
            'per_page' => $perPage,
            'total' => count($items),
            'has_more' => $offset + count($slice) < count($items),
            'items' => $slice,
        ]);
    }

    /** Active virtual-number products, shaped like catalogue cards so the list UI can render them. */
    private function virtualNumberItems(): array
    {
        return VirtualNumberProduct::query()
            ->where('active', true)
            ->orderBy('country_iso')
            ->orderBy('label')
            ->get()
            ->map(function (VirtualNumberProduct $p) {
                $iso = strtoupper((string) $p->country_iso);

                return [
                    'id' => (int) $p->id,
                    'product_id' => (int) $p->id,
                    'name' => $iso.' · '.$p->label,
                    'description' => 'Monthly virtual number subscription (SMS & calls).',
                    'price_formatted' => strtoupper((string) ($p->currency ?? 'USD')).' '.number_format(((int) $p->monthly_amount_minor) / 100, 2),
                    'flag' => '☎️',
                    'flag_url' => '',
                    'url' => route('dashboard.virtual.country', ['country' => $iso]),
                ];
            })
            ->all();
    }

    public function bundles(string $type, string $id, AiraloService $airalo)
    {
        $packageType = request('package_type', 'DATA-ONLY');

        if (! in_array($packageType, ['DATA-ONLY', 'DATA-VOICE-SMS'], true)) {
            return response()->json(['message' => 'Invalid package_type.'], 422);
        }

        $cacheKey = 'airalo.asset_bundles.v1.'.$type.'.'.$id.'.'.$packageType;

        $bundles = Cache::remember($cacheKey, now()->addMinutes(20), function () use ($airalo, $type, $id, $packageType) {
            $asset = $airalo->getAssetDetails($type, $id, $packageType);
            if (! $asset) {
                return null;
            }

            return data_get($asset, 'bundles', []);
        });

        if ($bundles === null) {
            return response()->json(['message' => 'Asset not found.'], 404);
        }

        return response()->json([
            'type' => $type,
            'id' => $id,
            'package_type' => $packageType,
            'bundles' => $bundles,
        ]);
    }

    public function show(string $type, string $id, AiraloService $airalo)
    {
        $asset = $airalo->getAssetDetails($type, $id, 'DATA-ONLY');
        if (! $asset) {
            abort(404);
        }

        $topupEsimId = trim((string) request('topup_esim_id', ''));
        if ($topupEsimId !== '' && ! Auth::check()) {
            return redirect()->route('login')->with('url.intended', request()->fullUrl());
        }

        return view('details', [
            'asset' => $asset,
            'type' => $type,
            'id' => $id,
            'bundlesDataOnly' => data_get($asset, 'bundles', []),
            'bundlesDataCalls' => [],
            'topupEsimId' => $topupEsimId !== '' ? $topupEsimId : null,
        ]);
    }
}
