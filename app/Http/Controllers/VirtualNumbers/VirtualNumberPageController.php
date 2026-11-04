<?php

namespace App\Http\Controllers\VirtualNumbers;

use App\Http\Controllers\Controller;
use App\Models\VirtualNumberProduct;
use App\Models\VirtualNumberSubscription;
use App\Services\TwilioService;
use App\Services\VirtualNumberCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Dashboard pages for browsing and buying virtual numbers (data is loaded client-side from
 * /api/virtual-numbers/*), plus redirects from the legacy /virtual-numbers/* URLs.
 */
class VirtualNumberPageController extends Controller
{
    public function __construct(private VirtualNumberCatalog $catalog) {}

    public function legacyIndex(Request $request)
    {
        return redirect()->route('dashboard.virtual.index', $request->query());
    }

    public function legacyCheckout(Request $request)
    {
        return redirect()->route('dashboard.virtual.checkout', $request->query());
    }

    public function legacyMy(Request $request)
    {
        return redirect()->route('dashboard.virtual.my', $request->query());
    }

    public function legacyCountry(string $country, Request $request)
    {
        return redirect()->route('dashboard.virtual.country', ['country' => $country, ...$request->query()]);
    }

    public function index()
    {
        return view('virtual-numbers.index', [
            ...$this->links(),
            'countriesApiBase' => '/api/virtual-numbers/countries?context=dashboard',
        ]);
    }

    public function checkout(Request $request, TwilioService $twilio)
    {
        $productId = (int) $request->query('product_id', 0);
        $phoneNumber = trim((string) $request->query('phone_number', ''));
        $numberType = VirtualNumberCatalog::numberType($request->query('number_type'));

        $product = $productId > 0 && $phoneNumber !== '' ? VirtualNumberProduct::query()->where('active', true)->find($productId) : null;
        if (! $product) {
            abort(404);
        }

        [$currency, $minor, $live] = $this->catalog->monthlyPrice($product, $numberType);

        return view('virtual-numbers.checkout', [
            'product' => $product,
            'phoneNumber' => $phoneNumber,
            'numberType' => $numberType,
            'priceCurrency' => $currency,
            'priceMinor' => $minor,
            'priceFormatted' => $live ? $twilio->formatMoney($minor, $currency) : $currency.' '.number_format($minor / 100, 2),
            ...$this->links(),
        ]);
    }

    public function my()
    {
        return view('virtual-numbers.my', [
            'subs' => VirtualNumberSubscription::with('product')->where('user_id', Auth::id())->orderByDesc('created_at')->get(),
            'indexUrl' => route('dashboard.virtual.index'),
        ]);
    }

    public function country(string $country, TwilioService $twilio)
    {
        $country = strtoupper(trim($country));
        if (! preg_match('/^[A-Z]{2}$/', $country)) {
            abort(404);
        }

        $countryName = $twilio->isConfigured() ? $this->catalog->countryName($country) : $country;
        $product = $this->catalog->productFor($country, $countryName);

        $live = $twilio->isConfigured() ? $this->catalog->cheapestLivePrice($country, 'USD') : null;
        $startingFrom = $live
            ? $twilio->formatMoney($live[1], $live[0])
            : strtoupper((string) ($product->currency ?? 'USD')).' '.number_format((int) $product->monthly_amount_minor / 100, 2);

        return view('virtual-numbers.country', [
            'countryIso' => $country,
            'countryName' => $countryName,
            'product' => $product,
            'startingFrom' => $startingFrom,
            'checkoutBaseUrl' => route('dashboard.virtual.checkout'),
            ...$this->links(),
        ]);
    }

    private function links(): array
    {
        return ['indexUrl' => route('dashboard.virtual.index'), 'myUrl' => route('dashboard.virtual.my')];
    }
}
