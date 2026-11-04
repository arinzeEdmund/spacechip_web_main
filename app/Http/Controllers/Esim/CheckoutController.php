<?php

namespace App\Http\Controllers\Esim;

use App\Http\Controllers\Controller;
use App\Services\AiraloService;
use App\Services\EsimFulfillment;
use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\Auth;

/**
 * eSIM checkout page: resolves the selected bundle and prices it in the customer's payment
 * currency. Top-ups are only offered for the user's own renewable eSIMs.
 */
class CheckoutController extends Controller
{
    public function show(AiraloService $airalo, ExchangeRateService $exchangeRates, EsimFulfillment $esims)
    {
        $type = request('type');
        $id = request('id');
        $bundleId = request('bundle');
        $packageType = request('package_type', 'DATA-ONLY');
        $topupEsimId = trim((string) request('topup_esim_id', ''));

        if (! $type || ! $id || ! $bundleId) {
            abort(404);
        }

        $asset = $airalo->getAssetDetails((string) $type, (string) $id, (string) $packageType);
        if (! $asset) {
            abort(404);
        }

        $bundle = collect($asset['bundles'] ?? [])->first(fn ($b) => (string) ($b['id'] ?? '') === (string) $bundleId);
        if (! $bundle) {
            abort(404);
        }

        $usdPrice = $bundle['price'] ?? null;
        if (! is_numeric($usdPrice) || (float) $usdPrice <= 0) {
            abort(422);
        }

        // Show both the USD price and, when Paystack settles in naira, the NGN amount at today's rate.
        $paystackCurrency = strtoupper(trim((string) config('services.paystack.currency', 'NGN'))) ?: 'NGN';
        $rateSource = null;
        $usdToNgnRate = 0.0;
        $amountNgn = 0.0;
        $paystackAmountMinorNgn = 0;
        if ($paystackCurrency === 'NGN') {
            $quote = $exchangeRates->usdToNgn();
            $usdToNgnRate = (float) ($quote['rate'] ?? 0);
            $rateSource = (string) ($quote['source'] ?? 'unavailable');
            if ($usdToNgnRate <= 0) {
                abort(422, 'Unable to fetch USD to NGN exchange rate. Set ESIM_USD_TO_NGN_RATE as a fallback or try again shortly.');
            }
            $amountNgn = (float) $usdPrice * $usdToNgnRate;
            $paystackAmountMinorNgn = (int) round($amountNgn * 100);
        }
        $paystackAmountMinorUsd = (int) round((float) $usdPrice * 100);

        if (($paystackCurrency === 'NGN' && $paystackAmountMinorNgn <= 0) || $paystackAmountMinorUsd <= 0) {
            abort(422);
        }

        if ($topupEsimId !== '' && ! $esims->canTopUp((int) Auth::id(), $topupEsimId)) {
            abort(403);
        }

        return view('checkout', [
            'asset' => $asset,
            'bundle' => $bundle,
            'type' => $type,
            'id' => $id,
            'topupEsimId' => $topupEsimId !== '' ? $topupEsimId : null,
            'paystackCurrency' => $paystackCurrency,
            'paystackAmountMinorNgn' => $paystackAmountMinorNgn,
            'paystackAmountMinorUsd' => $paystackAmountMinorUsd,
            'usdToNgnRate' => $usdToNgnRate,
            'usdPrice' => (float) $usdPrice,
            'paystackAmountNgnRaw' => $amountNgn,
            'paystackAmountNgnAdjusted' => $amountNgn,
            'usdToNgnRateSource' => $rateSource,
        ]);
    }
}
