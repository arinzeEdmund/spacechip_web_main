<?php

use App\Http\Controllers\ProfileController;
use App\Models\Payment;
use App\Models\User;
use App\Services\CryptomusService;
use App\Services\GloEsimService;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', function () {
    return view('landing');
});

Route::view('/contact-us', 'pages.contact')->name('contact');
Route::view('/terms', 'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/help-center', 'pages.help')->name('help');
Route::view('/esim-guide', 'pages.esim-guide')->name('esim.guide');

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/admin', function () {
        $usersTotal = User::count();
        $usersVerified = User::whereNotNull('email_verified_at')->count();

        $paymentsTotal = Payment::count();
        $paymentsFulfilled = Payment::where('status', 'fulfilled')->count();
        $paymentsPending = Payment::whereNotIn('status', ['fulfilled'])->count();

        $paymentsByProvider = Payment::query()
            ->selectRaw('provider, COUNT(*) as c')
            ->groupBy('provider')
            ->orderByDesc('c')
            ->pluck('c', 'provider')
            ->toArray();

        $recentPayments = Payment::with('user')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $recentUsers = User::query()
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $health = [
            'app_env' => (string) config('app.env'),
            'app_debug' => (bool) config('app.debug'),
            'cache_driver' => (string) config('cache.default'),
            'queue_connection' => (string) config('queue.default'),
            'mail_mailer' => (string) config('mail.default'),
            'gloesim_configured' => trim((string) config('services.gloesim.dealer_email')) !== '' && trim((string) config('services.gloesim.dealer_password')) !== '',
        ];

        return view('admin.dashboard', [
            'stats' => [
                'users_total' => $usersTotal,
                'users_verified' => $usersVerified,
                'users_unverified' => max(0, $usersTotal - $usersVerified),
                'payments_total' => $paymentsTotal,
                'payments_fulfilled' => $paymentsFulfilled,
                'payments_pending' => $paymentsPending,
                'payments_by_provider' => $paymentsByProvider,
            ],
            'health' => $health,
            'recentPayments' => $recentPayments,
            'recentUsers' => $recentUsers,
        ]);
    })->name('admin.dashboard');

    Route::get('/admin/payments/{payment}', function (Payment $payment) {
        return response()->json([
            'id' => $payment->id,
            'user_id' => $payment->user_id,
            'provider' => $payment->provider,
            'provider_reference' => $payment->provider_reference,
            'status' => $payment->status,
            'asset_type' => $payment->asset_type,
            'asset_id' => $payment->asset_id,
            'bundle_id' => $payment->bundle_id,
            'package_type' => $payment->package_type,
            'currency' => $payment->currency,
            'amount_minor' => $payment->amount_minor,
            'provider_payload' => $payment->provider_payload,
            'fulfillment_payload' => $payment->fulfillment_payload,
            'created_at' => optional($payment->created_at)->toIso8601String(),
            'updated_at' => optional($payment->updated_at)->toIso8601String(),
        ]);
    })->name('admin.payment');
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
    $tab = (string) request('tab', '');
    if ($tab !== '') {
        $tab = in_array($tab, ['countries', 'regions', 'virtual'], true) ? $tab : 'countries';
        $page = max(1, (int) request('page', 1));
        $perPage = (int) request('per_page', 30);
        $perPage = max(10, min(60, $perPage));
        $q = trim((string) request('q', ''));
        $qLower = mb_strtolower($q);

        if ($tab === 'countries') {
            $items = $gloEsim->allCountriesWithPrices('DATA-ONLY');
        } elseif ($tab === 'regions') {
            $items = $gloEsim->allRegionsWithPrices();
        } else {
            $items = $gloEsim->allVirtualNumbers();
        }

        if ($qLower !== '') {
            $items = array_values(array_filter($items, function ($item) use ($qLower) {
                $name = mb_strtolower((string) data_get($item, 'name', ''));
                return str_contains($name, $qLower);
            }));
        }

        $total = count($items);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($items, $offset, $perPage);
        $hasMore = ($offset + count($slice)) < $total;

        return response()->json([
            'tab' => $tab,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'has_more' => $hasMore,
            'items' => $slice,
        ]);
    }

    return response()->json([
        'countries' => $gloEsim->allCountriesWithPrices('DATA-ONLY'),
        'regions' => $gloEsim->allRegionsWithPrices(),
        'virtualNumbers' => $gloEsim->allVirtualNumbers(),
    ]);
});

Route::get('/api/assets/{type}/{id}/bundles', function (string $type, string $id, GloEsimService $gloEsim) {
    $packageType = request('package_type', 'DATA-ONLY');

    if (! in_array($packageType, ['DATA-ONLY', 'DATA-VOICE-SMS'], true)) {
        return response()->json(['message' => 'Invalid package_type.'], 422);
    }

    $cacheKey = 'gloesim.asset_bundles.v3.'.$type.'.'.$id.'.'.$packageType;

    $bundles = Cache::remember($cacheKey, now()->addMinutes(20), function () use ($gloEsim, $type, $id, $packageType) {
        $asset = $gloEsim->getAssetDetails($type, $id, $packageType);
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
});

Route::get('/assets/{type}/{id}', function (string $type, string $id, GloEsimService $gloEsim) {
    $asset = $gloEsim->getAssetDetails($type, $id, 'DATA-ONLY');
    if (! $asset) {
        abort(404);
    }

    return view('details', [
        'asset' => $asset,
        'type' => $type,
        'id' => $id,
        'bundlesDataOnly' => data_get($asset, 'bundles', []),
        'bundlesDataCalls' => [],
    ]);
})->name('asset.details');

Route::middleware(['auth'])->get('/checkout', function (GloEsimService $gloEsim) {
    $type = request('type');
    $id = request('id');
    $bundleId = request('bundle');
    $packageType = request('package_type', 'DATA-ONLY');

    if (! $type || ! $id || ! $bundleId) {
        abort(404);
    }

    $asset = $gloEsim->getAssetDetails((string) $type, (string) $id, (string) $packageType);
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

    $rateSource = 'static';
    $usdToNgnRate = 0.0;

    $paystackCurrency = (string) config('services.paystack.currency', 'NGN');
    $paystackAmountMinorNgn = 100 * 100;
    $paystackAmountMinorUsd = (int) round(((float) $usdPrice) * 100);
    $paystackAmountNgnRaw = 0.0;
    $paystackAmountNgnAdjusted = 0.0;

    if ($paystackAmountMinorNgn <= 0 || $paystackAmountMinorUsd <= 0) {
        abort(422);
    }

    return view('checkout', [
        'asset' => $asset,
        'bundle' => $bundle,
        'type' => $type,
        'id' => $id,
        'paystackCurrency' => $paystackCurrency,
        'paystackAmountMinorNgn' => $paystackAmountMinorNgn,
        'paystackAmountMinorUsd' => $paystackAmountMinorUsd,
        'usdToNgnRate' => (float) $usdToNgnRate,
        'usdPrice' => (float) $usdPrice,
        'paystackAmountNgnRaw' => (float) $paystackAmountNgnRaw,
        'paystackAmountNgnAdjusted' => (float) $paystackAmountNgnAdjusted,
        'usdToNgnRateSource' => $rateSource,
    ]);
})->name('checkout');

Route::middleware(['auth'])->post('/api/paystack/initialize', function (GloEsimService $gloEsim) {
    $type = request('type');
    $id = request('id');
    $bundleId = request('bundle');
    $packageType = request('package_type', 'DATA-ONLY');
    $requestedCurrency = strtoupper((string) request('currency', 'NGN'));
    if (! in_array($requestedCurrency, ['NGN', 'USD'], true)) {
        return response()->json(['message' => 'Unsupported currency.'], 422);
    }

    if (! $type || ! $id || ! $bundleId) {
        return response()->json(['message' => 'Missing checkout parameters.'], 422);
    }

    $asset = $gloEsim->getAssetDetails((string) $type, (string) $id, (string) $packageType);
    if (! $asset) {
        return response()->json(['message' => 'Asset not found.'], 404);
    }

    $bundle = collect($asset['bundles'] ?? [])->first(fn ($b) => (string) ($b['id'] ?? '') === (string) $bundleId);
    if (! $bundle) {
        return response()->json(['message' => 'Bundle not found.'], 404);
    }

    $price = $bundle['price'] ?? null;
    if (! is_numeric($price) || (float) $price <= 0) {
        return response()->json(['message' => 'Invalid amount.'], 422);
    }

    $usdToNgnRate = null;
    $rateSource = null;
    $amountMinor = 0;
    $amountNgnRaw = null;
    $amountNgnAdjusted = null;

    if ($requestedCurrency === 'USD') {
        $amountMinor = (int) round(((float) $price) * 100);
    } else {
        $rateSource = 'static';
        $usdToNgnRate = 0.0;
        $amountNgnRaw = 0.0;
        $amountNgnAdjusted = 0.0;
        $amountMinor = 100 * 100;
    }

    if ($amountMinor <= 0) {
        return response()->json(['message' => 'Invalid amount.'], 422);
    }
    $email = (string) (Auth::user()?->email ?? '');
    if (trim($email) === '') {
        return response()->json(['message' => 'Missing user email.'], 422);
    }
    $currency = $requestedCurrency;
    $reference = (string) Str::uuid();

    Payment::updateOrCreate(
        [
            'provider' => 'paystack',
            'provider_reference' => $reference,
        ],
        [
            'user_id' => Auth::id(),
            'status' => 'initialized',
            'asset_type' => (string) $type,
            'asset_id' => (string) $id,
            'bundle_id' => (string) $bundleId,
            'package_type' => (string) $packageType,
            'currency' => (string) $currency,
            'amount_minor' => (int) $amountMinor,
            'provider_payload' => [
                'asset' => [
                    'name' => (string) ($asset['name'] ?? ''),
                    'code' => (string) ($asset['code'] ?? ''),
                    'flag' => (string) ($asset['flag'] ?? ''),
                    'flag_url' => (string) ($asset['flag_url'] ?? ''),
                ],
                'bundle' => [
                    'name' => (string) ($bundle['name'] ?? ''),
                    'data' => (string) ($bundle['data'] ?? ''),
                    'validity' => (string) ($bundle['validity'] ?? ''),
                    'price' => is_numeric($price) ? (float) $price : null,
                    'price_formatted' => (string) ($bundle['price_formatted'] ?? ''),
                ],
            ],
        ]
    );

    $secretKey = (string) (config('services.paystack.secret_key') ?: env('PAYSTACK_SECRET_KEY', ''));
    $secretKey = trim($secretKey, " \t\n\r\0\x0B\"'");
    if (trim($secretKey) === '') {
        return response()->json(['message' => 'Paystack secret key is not configured.'], 500);
    }

    $response = Http::withToken($secretKey)
        ->acceptJson()
        ->timeout(30)
        ->post('https://api.paystack.co/transaction/initialize', [
            'email' => $email,
            'amount' => $amountMinor,
            'currency' => $currency,
            'reference' => $reference,
            'metadata' => [
                'type' => (string) $type,
                'id' => (string) $id,
                'bundle' => (string) $bundleId,
                'package_type' => (string) $packageType,
            ],
        ]);

    if (! $response->successful()) {
        return response()->json([
            'message' => 'Failed to initialize Paystack transaction.',
            'details' => $response->json(),
        ], 502);
    }

    $json = $response->json();
    $accessCode = data_get($json, 'data.access_code');

    if (! is_string($accessCode) || $accessCode === '') {
        return response()->json(['message' => 'Paystack did not return an access code.'], 502);
    }

    return response()->json([
        'reference' => $reference,
        'access_code' => $accessCode,
        'amount' => $amountMinor,
        'currency' => $currency,
        'email' => $email,
        'conversion' => [
            'usd_price' => (float) $price,
            'usd_to_ngn_rate' => (float) $usdToNgnRate,
            'rate_source' => $rateSource,
            'ngn_amount_raw' => $amountNgnRaw === null ? null : (float) $amountNgnRaw,
            'ngn_amount_adjusted' => $amountNgnAdjusted === null ? null : (float) $amountNgnAdjusted,
            'ngn_amount_minor' => $amountMinor,
            'currency' => $currency,
        ],
    ]);
})->name('paystack.initialize');

Route::middleware(['auth'])->post('/api/paystack/verify', function (GloEsimService $gloEsim, QrCodeService $qr) {
    $reference = (string) request('reference', '');
    if (trim($reference) === '') {
        return response()->json(['message' => 'Missing reference.'], 422);
    }

    $fulfilledCacheKey = 'paystack.fulfillment.v1.'.sha1($reference);
    $existing = Cache::get($fulfilledCacheKey);
    if (is_array($existing) && ($existing['ok'] ?? false) === true) {
        return response()->json($existing);
    }

    $secretKey = (string) (config('services.paystack.secret_key') ?: env('PAYSTACK_SECRET_KEY', ''));
    $secretKey = trim($secretKey, " \t\n\r\0\x0B\"'");
    if (trim($secretKey) === '') {
        return response()->json(['message' => 'Paystack secret key is not configured.'], 500);
    }

    try {
        $response = Http::withToken($secretKey)
            ->acceptJson()
            ->timeout(30)
            ->get('https://api.paystack.co/transaction/verify/'.urlencode($reference));
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Failed to verify Paystack transaction (network error).',
            'details' => $e->getMessage(),
        ], 502);
    }

    if (! $response->successful()) {
        return response()->json([
            'message' => 'Failed to verify Paystack transaction.',
            'status' => $response->status(),
            'details' => $response->json(),
        ], 502);
    }

    $json = $response->json();
    $status = data_get($json, 'data.status');

    $ok = $status === 'success';

    if (! $ok) {
        return response()->json([
            'ok' => false,
            'status' => $status,
            'reference' => data_get($json, 'data.reference'),
            'data' => data_get($json, 'data'),
        ]);
    }

    $meta = data_get($json, 'data.metadata', []);
    if (! is_array($meta)) {
        $meta = [];
    }

    $type = (string) (data_get($meta, 'type') ?? request('type', ''));
    $id = (string) (data_get($meta, 'id') ?? request('id', ''));
    $bundleId = (string) (data_get($meta, 'bundle') ?? request('bundle', ''));
    $packageType = (string) (data_get($meta, 'package_type') ?? request('package_type', 'DATA-ONLY'));

    if ($type === '' || $id === '' || $bundleId === '') {
        return response()->json([
            'ok' => true,
            'status' => $status,
            'reference' => data_get($json, 'data.reference'),
            'data' => data_get($json, 'data'),
            'fulfillment' => [
                'ok' => false,
                'message' => 'Missing fulfillment metadata.',
            ],
        ], 422);
    }

    $user = Auth::user();
    $email = (string) ($user?->email ?? '');
    if (trim($email) === '') {
        return response()->json(['message' => 'Missing user email.'], 422);
    }

    $asset = $gloEsim->getAssetDetails($type, $id, $packageType);
    $bundle = $asset ? collect($asset['bundles'] ?? [])->first(fn ($b) => (string) ($b['id'] ?? '') === $bundleId) : null;

    $fulfillment = $gloEsim->fulfillEsim($bundleId, $reference, $email, $packageType, [
        'name' => (string) ($user?->name ?? ''),
    ]);
    if (($fulfillment['ok'] ?? false) !== true) {
        $err = (string) ($fulfillment['error'] ?? '');
        $isPending = (bool) ($fulfillment['pending'] ?? false);

        if ($isPending) {
            $payload = [
                'ok' => true,
                'status' => $status,
                'reference' => data_get($json, 'data.reference'),
                'data' => data_get($json, 'data'),
                'fulfillment' => [
                    'ok' => false,
                    'pending' => true,
                    'attempted_at' => now()->toIso8601String(),
                    'type' => $type,
                    'id' => $id,
                    'bundle' => $bundleId,
                    'package_type' => $packageType,
                    'esim_id' => $fulfillment['esim_id'] ?? null,
                    'order_id' => $fulfillment['order_id'] ?? null,
                    'message' => 'Payment confirmed. Preparing your eSIM…',
                ],
            ];

            Cache::put($fulfilledCacheKey, $payload, now()->addHours(6));

            Payment::updateOrCreate(
                [
                    'provider' => 'paystack',
                    'provider_reference' => $reference,
                ],
                [
                    'user_id' => Auth::id(),
                    'status' => 'paid_pending_fulfillment',
                    'asset_type' => (string) $type,
                    'asset_id' => (string) $id,
                    'bundle_id' => (string) $bundleId,
                    'package_type' => (string) $packageType,
                    'currency' => (string) (data_get($json, 'data.currency') ?? 'NGN'),
                    'amount_minor' => (int) (data_get($json, 'data.amount') ?? 0),
                    'fulfillment_payload' => array_filter([
                        'ok' => false,
                        'pending' => true,
                        'attempted_at' => now()->toIso8601String(),
                        'error' => (string) ($fulfillment['error'] ?? ''),
                        'esim_id' => $fulfillment['esim_id'] ?? null,
                        'order_id' => $fulfillment['order_id'] ?? null,
                        'activation_code' => (string) ($fulfillment['activation_code'] ?? ''),
                        'iccid' => (string) ($fulfillment['iccid'] ?? ''),
                        'qr_code_url' => (string) ($fulfillment['qr_code_url'] ?? ''),
                        'smdp_address' => (string) ($fulfillment['smdp_address'] ?? ''),
                    ], fn ($v) => $v !== null),
                ]
            );

            return response()->json($payload);
        }

        return response()->json([
            'ok' => true,
            'status' => $status,
            'reference' => data_get($json, 'data.reference'),
            'data' => data_get($json, 'data'),
            'fulfillment' => [
                'ok' => false,
                'message' => $err !== '' ? 'Payment confirmed, but eSIM fulfillment failed: '.$err : 'Payment confirmed, but eSIM fulfillment failed. Please try again shortly or contact support.',
            ],
        ]);
    }

    $esimId = trim((string) ($fulfillment['esim_id'] ?? ''));
    $iccid = trim((string) ($fulfillment['iccid'] ?? ''));
    if ($esimId === '' && $iccid !== '') {
        $rawList = $gloEsim->dealerMyEsimsRaw();
        $list = is_array($rawList) ? (data_get($rawList, 'data.items') ?? data_get($rawList, 'data') ?? null) : null;
        if (is_array($list)) {
            foreach ($list as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rowIccid = trim((string) (data_get($row, 'iccid') ?? ''));
                if ($rowIccid !== '' && $rowIccid === $iccid) {
                    $esimId = trim((string) (data_get($row, 'id') ?? data_get($row, 'esim_id') ?? ''));
                    break;
                }
            }
        }
    }

    $details = $esimId !== '' ? $gloEsim->getEsimDetails($esimId) : null;
    $detailsOk = is_array($details) && (($details['ok'] ?? false) === true);
    $gloItem = $detailsOk && is_array($details['gloesim_item'] ?? null) ? $details['gloesim_item'] : null;

    $resolvedEsimId = trim((string) (($detailsOk ? ($details['esim_id'] ?? '') : '') ?: $esimId));
    $iccid = trim((string) (($detailsOk ? ($details['iccid'] ?? '') : '') ?: $iccid));
    $activationCode = trim((string) (($detailsOk ? ($details['activation_code'] ?? '') : '') ?: (string) ($fulfillment['activation_code'] ?? '')));
    $lpa = trim((string) (($detailsOk ? ($details['lpa'] ?? '') : '') ?: (string) ($fulfillment['lpa'] ?? '')));
    $qrCodeUrl = trim((string) (($detailsOk ? ($details['qr_code_url'] ?? '') : '') ?: (string) ($fulfillment['qr_code_url'] ?? '')));
    $smdpAddress = trim((string) (($detailsOk ? ($details['smdp_address'] ?? '') : '') ?: (string) ($fulfillment['smdp_address'] ?? '')));
    $esimStatus = trim((string) (($detailsOk ? ($details['esim_status'] ?? '') : '') ?: (string) ($fulfillment['esim_status'] ?? '')));
    $number = $detailsOk ? ($details['number'] ?? null) : ($fulfillment['number'] ?? null);
    $pukCode = trim((string) (($detailsOk ? ($details['puk_code'] ?? '') : '') ?: (string) ($fulfillment['puk_code'] ?? '')));
    $installIos = trim((string) (($detailsOk ? ($details['direct_installation_link_ios'] ?? '') : '') ?: (string) ($fulfillment['direct_installation_link_ios'] ?? '')));
    $installAndroid = trim((string) (($detailsOk ? ($details['direct_installation_link_android'] ?? '') : '') ?: (string) ($fulfillment['direct_installation_link_android'] ?? '')));

    $qrPayload = $qr->esimQrPayload($lpa !== '' ? $lpa : $activationCode, $smdpAddress);
    $synced = $iccid !== '' && $qrPayload !== '';
    $qrCodeDataUrl = $qrPayload !== '' ? $qr->svgDataUrl($qrPayload) : '';
    $qrSvg = $qrPayload !== '' ? $qr->svgString($qrPayload) : '';

    if (! $synced) {
        $payload = [
            'ok' => true,
            'status' => $status,
            'reference' => data_get($json, 'data.reference'),
            'data' => data_get($json, 'data'),
            'fulfillment' => [
                'ok' => false,
                'pending' => true,
                'synced' => false,
                'attempted_at' => now()->toIso8601String(),
                'type' => $type,
                'id' => $id,
                'bundle' => $bundleId,
                'package_type' => $packageType,
                'esim_id' => $resolvedEsimId !== '' ? $resolvedEsimId : null,
                'iccid' => $iccid !== '' ? $iccid : null,
                'message' => 'Payment confirmed. Preparing your eSIM…',
            ],
        ];

        Cache::put($fulfilledCacheKey, $payload, now()->addHours(6));

        Payment::updateOrCreate(
            [
                'provider' => 'paystack',
                'provider_reference' => $reference,
            ],
            [
                'user_id' => Auth::id(),
                'status' => 'paid_pending_fulfillment',
                'asset_type' => (string) $type,
                'asset_id' => (string) $id,
                'bundle_id' => (string) $bundleId,
                'package_type' => (string) $packageType,
                'currency' => (string) (data_get($json, 'data.currency') ?? 'NGN'),
                'amount_minor' => (int) (data_get($json, 'data.amount') ?? 0),
                'fulfillment_payload' => array_filter([
                    'ok' => false,
                    'pending' => true,
                    'synced' => false,
                    'attempted_at' => now()->toIso8601String(),
                    'esim_id' => $resolvedEsimId !== '' ? $resolvedEsimId : null,
                    'iccid' => $iccid !== '' ? $iccid : null,
                    'number' => $number,
                    'esim_status' => $esimStatus,
                    'smdp_address' => $smdpAddress,
                    'activation_code' => $activationCode,
                    'lpa' => $lpa,
                    'puk_code' => $pukCode,
                    'qr_code_url' => $qrCodeUrl,
                    'direct_installation_link_ios' => $installIos,
                    'direct_installation_link_android' => $installAndroid,
                    'gloesim' => $gloItem,
                ], fn ($v) => $v !== null && $v !== ''),
            ]
        );

        return response()->json($payload);
    }

    $subject = 'Your Spacechip eSIM is ready';
    $bundleName = $bundle ? (string) ($bundle['name'] ?? '') : '';
    $bundleData = $bundle ? (string) ($bundle['data'] ?? '') : '';
    $bundleValidity = $bundle ? (string) ($bundle['validity'] ?? '') : '';

    $html = '<div style="font-family: Instrument Sans, Arial, sans-serif; background:#f7f7f8; padding:24px;">'
        .'<div style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:18px; border:1px solid rgba(20,84,84,.12); overflow:hidden;">'
        .'<div style="padding:18px 20px; background: linear-gradient(90deg,#f27457,#145454); color:#fff;">'
        .'<div style="font-weight:900; letter-spacing:.02em;">Spacechip</div>'
        .'<div style="opacity:.9; margin-top:6px;">Your eSIM delivery details</div>'
        .'</div>'
        .'<div style="padding:18px 20px; color:#0b1a1a;">'
        .($bundleName !== '' ? '<div style="font-weight:800; font-size:16px; margin-bottom:10px;">'.$bundleName.'</div>' : '')
        .'<div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px;">'
        .($bundleData !== '' ? '<div style="padding:8px 10px; border-radius:12px; background:rgba(20,84,84,.06); border:1px solid rgba(20,84,84,.10); font-weight:700; font-size:12px;">Data: '.$bundleData.'</div>' : '')
        .($bundleValidity !== '' ? '<div style="padding:8px 10px; border-radius:12px; background:rgba(242,116,87,.06); border:1px solid rgba(242,116,87,.10); font-weight:700; font-size:12px;">Validity: '.$bundleValidity.'</div>' : '')
        .'</div>'
        .'<div style="border-top:1px solid rgba(15,31,31,.08); padding-top:14px;">'
        .'<div style="font-weight:800; margin-bottom:10px;">eSIM Details</div>'
        .($iccid !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">ICCID:</span> '.$iccid.'</div>' : '')
        .($activationCode !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">Activation Code:</span> '.$activationCode.'</div>' : '')
        .($smdpAddress !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">SM-DP+ Address:</span> '.$smdpAddress.'</div>' : '')
        .($lpa !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">LPA:</span> '.$lpa.'</div>' : '')
        .($qrCodeUrl !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">QR Code:</span> <a href="'.$qrCodeUrl.'" style="color:#145454; font-weight:800;">Open QR Code</a></div>' : '')
        .($qrCodeDataUrl !== '' ? '<div style="margin-top:12px;"><div style="color:rgba(15,31,31,.62); font-weight:800; margin-bottom:8px;">QR Code</div><img alt="eSIM QR code" src="'.$qrCodeDataUrl.'" style="width:220px; max-width:100%; border-radius:14px; border:1px solid rgba(15,31,31,.10); background:#fff; padding:10px;"></div>' : '')
        .'</div>'
        .'<div style="margin-top:14px; color:rgba(15,31,31,.62); font-size:13px; line-height:1.5;">'
        .'Open your phone settings → Cellular/Mobile Data → Add eSIM, then scan the QR code (or enter the activation code manually).'
        .'</div>'
        .'</div>'
        .'</div>'
        .'</div>';

    try {
        \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($email, $subject, $html, $qrSvg) {
            $message->to($email)->subject($subject)->html($html);
            if (is_string($qrSvg) && $qrSvg !== '') {
                $message->attachData($qrSvg, 'esim-qr.svg', ['mime' => 'image/svg+xml']);
            }
        });
    } catch (\Throwable) {
        // No-op: fulfillment should not be blocked by mail issues
    }

    $payload = [
        'ok' => true,
        'status' => $status,
        'reference' => data_get($json, 'data.reference'),
        'data' => data_get($json, 'data'),
        'fulfillment' => [
            'ok' => true,
            'synced' => true,
            'type' => $type,
            'id' => $id,
            'bundle' => $bundleId,
            'package_type' => $packageType,
            'esim_id' => $resolvedEsimId,
            'order_id' => (string) ($fulfillment['order_id'] ?? ''),
            'iccid' => $iccid,
            'activation_code' => $activationCode,
            'lpa' => $lpa,
            'puk_code' => $pukCode,
            'number' => is_scalar($number) ? (string) $number : '',
            'esim_status' => $esimStatus,
            'direct_installation_link_ios' => $installIos,
            'direct_installation_link_android' => $installAndroid,
            'qr_code_url' => $qrCodeUrl,
            'qr_payload' => $qrPayload,
            'qr_code_data_url' => $qrCodeDataUrl,
            'smdp_address' => $smdpAddress,
            'gloesim' => $gloItem,
        ],
    ];

    Cache::put($fulfilledCacheKey, $payload, now()->addDays(2));

    Payment::updateOrCreate(
        [
            'provider' => 'paystack',
            'provider_reference' => $reference,
        ],
        [
            'user_id' => Auth::id(),
            'status' => 'fulfilled',
            'asset_type' => (string) $type,
            'asset_id' => (string) $id,
            'bundle_id' => (string) $bundleId,
            'package_type' => (string) $packageType,
            'currency' => (string) (data_get($json, 'data.currency') ?? 'NGN'),
            'amount_minor' => (int) (data_get($json, 'data.amount') ?? 0),
            'fulfillment_payload' => array_merge(
                ['ok' => true, 'attempted_at' => now()->toIso8601String()],
                $payload['fulfillment']
            ),
        ]
    );

    return response()->json($payload);
})->name('paystack.verify');

Route::middleware(['auth'])->get('/api/paystack/status', function (GloEsimService $gloEsim, QrCodeService $qr) {
    $reference = (string) request('reference', '');
    if (trim($reference) === '') {
        return response()->json(['message' => 'Missing reference.'], 422);
    }

    $fulfilledCacheKey = 'paystack.fulfillment.v1.'.sha1($reference);
    $existing = Cache::get($fulfilledCacheKey);
    if (! is_array($existing)) {
        return response()->json(['message' => 'Payment not found.'], 404);
    }

    if (($existing['ok'] ?? false) === true && (data_get($existing, 'fulfillment.ok') === true) && (data_get($existing, 'fulfillment.synced') === true)) {
        return response()->json($existing);
    }

    $fulfillmentMeta = (array) data_get($existing, 'fulfillment', []);
    $attemptedAt = (string) ($fulfillmentMeta['attempted_at'] ?? '');
    $shouldAttempt = true;
    if ($attemptedAt !== '') {
        $ts = strtotime($attemptedAt);
        if ($ts !== false) {
            $shouldAttempt = $ts <= now()->subSeconds(5)->getTimestamp();
        }
    }

    if (! $shouldAttempt) {
        return response()->json($existing);
    }

    $user = Auth::user();
    $email = (string) ($user?->email ?? '');
    if (trim($email) === '') {
        return response()->json(['message' => 'Missing user email.'], 422);
    }

    $packageType = (string) ($fulfillmentMeta['package_type'] ?? 'DATA-ONLY');
    $bundleId = (string) ($fulfillmentMeta['bundle'] ?? '');
    $pendingEsimId = trim((string) ($fulfillmentMeta['esim_id'] ?? ''));
    $pendingIccid = trim((string) ($fulfillmentMeta['iccid'] ?? ''));
    $type = (string) ($fulfillmentMeta['type'] ?? '');
    $id = (string) ($fulfillmentMeta['id'] ?? '');

    $resolvedEsimId = $pendingEsimId;
    if ($resolvedEsimId === '' && $pendingIccid !== '') {
        $rawList = $gloEsim->dealerMyEsimsRaw();
        $list = is_array($rawList) ? (data_get($rawList, 'data.items') ?? data_get($rawList, 'data') ?? null) : null;
        if (is_array($list)) {
            foreach ($list as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rowIccid = trim((string) (data_get($row, 'iccid') ?? ''));
                if ($rowIccid !== '' && $rowIccid === $pendingIccid) {
                    $resolvedEsimId = trim((string) (data_get($row, 'id') ?? data_get($row, 'esim_id') ?? ''));
                    break;
                }
            }
        }
    }

    $fulfillment = $resolvedEsimId !== ''
        ? $gloEsim->getEsimDetails($resolvedEsimId)
        : ($bundleId !== '' ? $gloEsim->fulfillEsim($bundleId, $reference, $email, $packageType, ['name' => (string) ($user?->name ?? '')]) : ['ok' => false, 'error' => 'Missing bundle id.']);

    $iccid = trim((string) ($fulfillment['iccid'] ?? $pendingIccid));
    $activationCode = trim((string) ($fulfillment['activation_code'] ?? ''));
    $lpa = trim((string) ($fulfillment['lpa'] ?? ''));
    $qrCodeUrl = trim((string) ($fulfillment['qr_code_url'] ?? ''));
    $smdpAddress = trim((string) ($fulfillment['smdp_address'] ?? ''));
    $qrPayload = $qr->esimQrPayload($lpa !== '' ? $lpa : $activationCode, $smdpAddress);
    $synced = $iccid !== '' && $qrPayload !== '' && (($fulfillment['ok'] ?? false) === true);

    if (! $synced) {
        $existing['fulfillment'] = array_filter(array_merge($fulfillmentMeta, [
            'ok' => false,
            'pending' => true,
            'synced' => false,
            'attempted_at' => now()->toIso8601String(),
            'error' => (string) ($fulfillment['error'] ?? ''),
            'esim_id' => (string) ($fulfillment['esim_id'] ?? $resolvedEsimId),
            'iccid' => $iccid !== '' ? $iccid : null,
            'message' => 'Payment confirmed. Preparing your eSIM…',
        ]), fn ($v) => $v !== null);

        Cache::put($fulfilledCacheKey, $existing, now()->addHours(6));

        return response()->json($existing);
    }

    $qrCodeDataUrl = $qrPayload !== '' ? $qr->svgDataUrl($qrPayload) : '';
    $qrSvg = $qrPayload !== '' ? $qr->svgString($qrPayload) : '';

    $subject = 'Your Spacechip eSIM is ready';
    $html = '<div style="font-family: Instrument Sans, Arial, sans-serif; background:#f7f7f8; padding:24px;">'
        .'<div style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:18px; border:1px solid rgba(20,84,84,.12); overflow:hidden;">'
        .'<div style="padding:18px 20px; background: linear-gradient(90deg,#f27457,#145454); color:#fff;">'
        .'<div style="font-weight:900; letter-spacing:.02em;">Spacechip</div>'
        .'<div style="opacity:.9; margin-top:6px;">Your eSIM delivery details</div>'
        .'</div>'
        .'<div style="padding:18px 20px; color:#0b1a1a;">'
        .'<div style="border-top:1px solid rgba(15,31,31,.08); padding-top:14px;">'
        .'<div style="font-weight:800; margin-bottom:10px;">eSIM Details</div>'
        .($iccid !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">ICCID:</span> '.$iccid.'</div>' : '')
        .($activationCode !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">Activation Code:</span> '.$activationCode.'</div>' : '')
        .($smdpAddress !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">SM-DP+ Address:</span> '.$smdpAddress.'</div>' : '')
        .($lpa !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">LPA:</span> '.$lpa.'</div>' : '')
        .($qrCodeUrl !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">QR Code:</span> <a href="'.$qrCodeUrl.'" style="color:#145454; font-weight:800;">Open QR Code</a></div>' : '')
        .($qrCodeDataUrl !== '' ? '<div style="margin-top:12px;"><div style="color:rgba(15,31,31,.62); font-weight:800; margin-bottom:8px;">QR Code</div><img alt="eSIM QR code" src="'.$qrCodeDataUrl.'" style="width:220px; max-width:100%; border-radius:14px; border:1px solid rgba(15,31,31,.10); background:#fff; padding:10px;"></div>' : '')
        .'</div>'
        .'<div style="margin-top:14px; color:rgba(15,31,31,.62); font-size:13px; line-height:1.5;">'
        .'Open your phone settings → Cellular/Mobile Data → Add eSIM, then scan the QR code (or enter the activation code manually).'
        .'</div>'
        .'</div>'
        .'</div>'
        .'</div>';

    try {
        \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($email, $subject, $html, $qrSvg) {
            $message->to($email)->subject($subject)->html($html);
            if (is_string($qrSvg) && $qrSvg !== '') {
                $message->attachData($qrSvg, 'esim-qr.svg', ['mime' => 'image/svg+xml']);
            }
        });
    } catch (\Throwable) {
    }

    $existing['ok'] = true;
    $existing['fulfillment'] = [
        'ok' => true,
        'synced' => true,
        'type' => $type,
        'id' => $id,
        'bundle' => $bundleId,
        'package_type' => $packageType,
        'esim_id' => (string) ($fulfillment['esim_id'] ?? $resolvedEsimId),
        'iccid' => $iccid,
        'activation_code' => $activationCode,
        'lpa' => $lpa,
        'puk_code' => (string) ($fulfillment['puk_code'] ?? ''),
        'number' => is_scalar($fulfillment['number'] ?? null) ? (string) ($fulfillment['number'] ?? '') : '',
        'esim_status' => (string) ($fulfillment['esim_status'] ?? ''),
        'direct_installation_link_ios' => (string) ($fulfillment['direct_installation_link_ios'] ?? ''),
        'direct_installation_link_android' => (string) ($fulfillment['direct_installation_link_android'] ?? ''),
        'qr_code_url' => $qrCodeUrl,
        'qr_payload' => $qrPayload,
        'qr_code_data_url' => $qrCodeDataUrl,
        'smdp_address' => $smdpAddress,
        'gloesim' => is_array($fulfillment['gloesim_item'] ?? null) ? $fulfillment['gloesim_item'] : null,
    ];

    Cache::put($fulfilledCacheKey, $existing, now()->addDays(2));
    Payment::updateOrCreate(
        [
            'provider' => 'paystack',
            'provider_reference' => $reference,
        ],
        [
            'user_id' => Auth::id(),
            'status' => 'fulfilled',
            'currency' => (string) (data_get($existing, 'data.currency') ?? data_get($existing, 'data.currency') ?? 'NGN'),
            'amount_minor' => (int) (data_get($existing, 'data.amount') ?? 0),
            'asset_type' => (string) ($existing['fulfillment']['type'] ?? ''),
            'asset_id' => (string) ($existing['fulfillment']['id'] ?? ''),
            'bundle_id' => (string) ($existing['fulfillment']['bundle'] ?? ''),
            'package_type' => (string) ($existing['fulfillment']['package_type'] ?? 'DATA-ONLY'),
            'fulfillment_payload' => array_merge(
                ['ok' => true, 'attempted_at' => now()->toIso8601String()],
                $existing['fulfillment']
            ),
        ]
    );


    return response()->json($existing);
})->name('paystack.status');

Route::middleware(['auth'])->post('/api/cryptomus/invoice', function (Request $request, CryptomusService $cryptomus, GloEsimService $gloEsim) {
    $type = (string) $request->input('type', '');
    $id = (string) $request->input('id', '');
    $bundleId = (string) $request->input('bundle', '');
    $packageType = (string) $request->input('package_type', 'DATA-ONLY');
    $kind = (string) $request->input('kind', 'crypto');
    $kind = in_array($kind, ['crypto', 'card'], true) ? $kind : 'crypto';

    if ($type === '' || $id === '' || $bundleId === '') {
        return response()->json(['message' => 'Missing checkout parameters.'], 422);
    }

    $asset = $gloEsim->getAssetDetails($type, $id, $packageType);
    if (! $asset) {
        return response()->json(['message' => 'Asset not found.'], 404);
    }

    $bundle = collect($asset['bundles'] ?? [])->first(fn ($b) => (string) ($b['id'] ?? '') === $bundleId);
    if (! $bundle) {
        return response()->json(['message' => 'Bundle not found.'], 404);
    }

    $usdPrice = $bundle['price'] ?? null;
    if (! is_numeric($usdPrice) || (float) $usdPrice <= 0) {
        return response()->json(['message' => 'Invalid amount.'], 422);
    }

    $amount = number_format((float) $usdPrice, 2, '.', '');
    $currency = 'USD';
    $orderId = 'sc_crypto_'.Auth::id().'_'.str_replace('-', '_', (string) Str::uuid());

    $returnUrl = url('/crypto/return?order_id='.$orderId);
    $successUrl = $returnUrl;
    $callbackUrl = url('/api/cryptomus/webhook');

    $payload = [
        'amount' => $amount,
        'currency' => $currency,
        'order_id' => $orderId,
        'url_return' => $returnUrl,
        'url_success' => $successUrl,
        'url_callback' => $callbackUrl,
        'lifetime' => 3600,
    ];

    $res = $cryptomus->createInvoice($payload);
    if (! is_array($res) || (int) data_get($res, 'state', 1) !== 0) {
        return response()->json([
            'message' => (string) (data_get($res, 'message') ?? 'Failed to create Cryptomus invoice.'),
        ], 502);
    }

    $result = data_get($res, 'result', []);
    $payUrl = (string) data_get($result, 'url', '');
    $uuid = (string) data_get($result, 'uuid', '');
    if ($payUrl === '') {
        return response()->json(['message' => 'Cryptomus did not return a payment URL.'], 502);
    }

    Payment::create([
        'user_id' => Auth::id(),
        'provider' => 'cryptomus',
        'provider_reference' => $orderId,
        'status' => 'created',
        'asset_type' => $type,
        'asset_id' => $id,
        'bundle_id' => $bundleId,
        'package_type' => $packageType,
        'currency' => $currency,
        'amount_minor' => (int) round(((float) $usdPrice) * 100),
        'provider_payload' => [
            'asset' => [
                'name' => (string) ($asset['name'] ?? ''),
                'code' => (string) ($asset['code'] ?? ''),
                'flag' => (string) ($asset['flag'] ?? ''),
                'flag_url' => (string) ($asset['flag_url'] ?? ''),
            ],
            'bundle' => [
                'name' => (string) ($bundle['name'] ?? ''),
                'data' => (string) ($bundle['data'] ?? ''),
                'validity' => (string) ($bundle['validity'] ?? ''),
                'price' => is_numeric($usdPrice) ? (float) $usdPrice : null,
                'price_formatted' => (string) ($bundle['price_formatted'] ?? ''),
            ],
            'invoice_uuid' => $uuid,
            'pay_url' => $payUrl,
            'kind' => $kind,
            'raw' => $res,
        ],
    ]);

    return response()->json([
        'order_id' => $orderId,
        'uuid' => $uuid,
        'url' => $payUrl,
    ]);
})->name('cryptomus.invoice');

Route::middleware(['auth'])->get('/api/my-esims', function (Request $request, QrCodeService $qr, GloEsimService $gloEsim) {
    $filter = (string) $request->query('filter', 'valid');
    $filter = in_array($filter, ['valid', 'expired'], true) ? $filter : 'valid';

    $page = max(1, (int) $request->query('page', 1));
    $perPage = (int) $request->query('per_page', 10);
    $perPage = max(5, min(30, $perPage));

    $parseDays = function (?string $value): ?int {
        if (! is_string($value)) {
            return null;
        }
        if (preg_match('/(\d+)/', $value, $m)) {
            $days = (int) $m[1];
            return $days > 0 ? $days : null;
        }
        return null;
    };

    $rows = Payment::query()
        ->where('user_id', Auth::id())
        ->whereIn('provider', ['paystack', 'cryptomus'])
        ->whereNotNull('fulfillment_payload')
        ->orderByDesc('created_at')
        ->get();

    $dealerIndex = [];
    $dealerPagesFetched = 0;
    $dealerLastPage = null;
    $getDealerRowByIccid = function (string $iccid) use (&$dealerIndex, &$dealerPagesFetched, &$dealerLastPage, $gloEsim): ?array {
        $iccid = trim($iccid);
        if ($iccid === '') {
            return null;
        }

        if (isset($dealerIndex[$iccid]) && is_array($dealerIndex[$iccid])) {
            return $dealerIndex[$iccid];
        }

        $maxPages = 10;
        $perPage = 200;

        while ($dealerPagesFetched < $maxPages) {
            if (is_int($dealerLastPage) && $dealerPagesFetched >= $dealerLastPage) {
                break;
            }

            $nextPage = $dealerPagesFetched + 1;

            try {
                $raw = $gloEsim->dealerMyEsimsRaw([
                    'page' => $nextPage,
                    'per_page' => $perPage,
                    'perPage' => $perPage,
                ]);
            } catch (\Throwable) {
                $raw = null;
            }

            $dealerPagesFetched = $nextPage;

            $metaLastPage = data_get($raw, 'data.meta.lastPage');
            if (is_numeric($metaLastPage)) {
                $dealerLastPage = (int) $metaLastPage;
            }

            $list = is_array($raw) ? (data_get($raw, 'data.items') ?? data_get($raw, 'data') ?? null) : null;
            if (! is_array($list) || $list === []) {
                break;
            }

            foreach ($list as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rowIccid = trim((string) (data_get($row, 'iccid') ?? ''));
                if ($rowIccid === '') {
                    continue;
                }
                if (! isset($dealerIndex[$rowIccid])) {
                    $dealerIndex[$rowIccid] = $row;
                }
            }

            if (isset($dealerIndex[$iccid]) && is_array($dealerIndex[$iccid])) {
                return $dealerIndex[$iccid];
            }
        }

        return null;
    };

    $items = [];
    $seenIccids = [];
    foreach ($rows as $p) {
        $fulfillment = is_array($p->fulfillment_payload) ? $p->fulfillment_payload : [];
        $syncKey = 'myesims.autosync.v3.'.sha1((string) $p->id);
        if (Cache::add($syncKey, '1', now()->addSeconds(5))) {
            $glo = is_array($fulfillment['gloesim'] ?? null) ? $fulfillment['gloesim'] : [];
            $iccid = trim((string) ($fulfillment['iccid'] ?? (data_get($glo, 'iccid') ?? '')));
            $esimId = trim((string) ($fulfillment['esim_id'] ?? ''));

            if ($iccid !== '') {
                $dealerRow = $getDealerRowByIccid($iccid);
                if (is_array($dealerRow)) {
                    $dealerEsimId = trim((string) (data_get($dealerRow, 'id') ?? data_get($dealerRow, 'esim_id') ?? ''));
                    $hasBetterId = $dealerEsimId !== '' && $dealerEsimId !== $esimId;
                    $missingLpa = trim((string) ($fulfillment['lpa'] ?? (data_get($glo, 'qr_code_text') ?? ''))) === '';
                    $missingSmdp = trim((string) ($fulfillment['smdp_address'] ?? (data_get($glo, 'smdp_address') ?? ''))) === '';
                    $missingGlo = ! is_array($fulfillment['gloesim'] ?? null);

                    if ($missingGlo || $missingLpa || $missingSmdp || $hasBetterId) {
                        $fulfillment = array_merge($fulfillment, [
                            'gloesim' => $dealerRow,
                            'esim_id' => (string) (data_get($dealerRow, 'id') ?? data_get($dealerRow, 'esim_id') ?? $esimId),
                            'iccid' => (string) (data_get($dealerRow, 'iccid') ?? $iccid),
                            'number' => data_get($dealerRow, 'number'),
                            'esim_status' => (string) (data_get($dealerRow, 'status') ?? ''),
                            'smdp_address' => (string) (data_get($dealerRow, 'smdp_address') ?? ''),
                            'lpa' => (string) (data_get($dealerRow, 'qr_code_text') ?? ''),
                            'direct_installation_link_ios' => (string) (data_get($dealerRow, 'universal_link') ?? ''),
                            'direct_installation_link_android' => (string) (data_get($dealerRow, 'android_universal_link') ?? ''),
                            'attempted_at' => now()->toIso8601String(),
                        ]);

                        $p->fulfillment_payload = array_filter($fulfillment, fn ($v) => $v !== null);
                        $p->save();
                    }
                }
            }

            $esimId = trim((string) ($fulfillment['esim_id'] ?? ''));
            $glo = is_array($fulfillment['gloesim'] ?? null) ? $fulfillment['gloesim'] : [];
            $iccid = trim((string) ($fulfillment['iccid'] ?? (data_get($glo, 'iccid') ?? '')));
            $lpaValue = trim((string) ($fulfillment['lpa'] ?? (data_get($glo, 'qr_code_text') ?? '')));
            $activationValue = trim((string) ($fulfillment['activation_code'] ?? ''));
            $smdpValue = trim((string) ($fulfillment['smdp_address'] ?? (data_get($glo, 'smdp_address') ?? '')));
            $qrPayload = $qr->esimQrPayload($lpaValue !== '' ? $lpaValue : $activationValue, $smdpValue);
            $computedSynced = $iccid !== '' && trim($qrPayload) !== '';

            if (! $computedSynced && $esimId !== '') {
                $details = $gloEsim->getEsimDetails($esimId);
                if (is_array($details) && (($details['ok'] ?? false) === true)) {
                    $gloItem = is_array($details['gloesim_item'] ?? null) ? $details['gloesim_item'] : null;
                    $lpaValue = trim((string) ($details['lpa'] ?? ''));
                    $activationValue = trim((string) ($details['activation_code'] ?? ''));
                    $smdpValue = trim((string) ($details['smdp_address'] ?? ''));
                    $qrPayload = $qr->esimQrPayload($lpaValue !== '' ? $lpaValue : $activationValue, $smdpValue);
                    $computedSynced = trim((string) ($details['iccid'] ?? '')) !== '' && trim($qrPayload) !== '';

                    $fulfillment = array_filter(array_merge($fulfillment, [
                        'gloesim' => $gloItem ?? $fulfillment['gloesim'] ?? null,
                        'attempted_at' => now()->toIso8601String(),
                        'esim_id' => (string) ($details['esim_id'] ?? $esimId),
                        'iccid' => (string) ($details['iccid'] ?? $iccid),
                        'number' => $details['number'] ?? null,
                        'esim_status' => (string) ($details['esim_status'] ?? ''),
                        'smdp_address' => (string) ($details['smdp_address'] ?? ''),
                        'activation_code' => (string) ($details['activation_code'] ?? ''),
                        'lpa' => (string) ($details['lpa'] ?? ''),
                        'puk_code' => (string) ($details['puk_code'] ?? ''),
                        'qr_code_url' => (string) ($details['qr_code_url'] ?? ''),
                        'direct_installation_link_ios' => (string) ($details['direct_installation_link_ios'] ?? ''),
                        'direct_installation_link_android' => (string) ($details['direct_installation_link_android'] ?? ''),
                    ]), fn ($v) => $v !== null);

                    unset($fulfillment['raw'], $fulfillment['gloesim_detail_raw'], $fulfillment['gloesim_item']);
                    $p->fulfillment_payload = $fulfillment;
                    $p->save();
                }
            }

            if ($computedSynced && (($fulfillment['synced'] ?? false) !== true || ($fulfillment['ok'] ?? false) !== true)) {
                $glo = is_array($fulfillment['gloesim'] ?? null) ? $fulfillment['gloesim'] : [];
                $iccid = trim((string) ($fulfillment['iccid'] ?? (data_get($glo, 'iccid') ?? '')));
                $lpaValue = trim((string) ($fulfillment['lpa'] ?? (data_get($glo, 'qr_code_text') ?? '')));
                $smdpValue = trim((string) ($fulfillment['smdp_address'] ?? (data_get($glo, 'smdp_address') ?? '')));
                $fulfillment = array_merge($fulfillment, [
                    'ok' => true,
                    'pending' => false,
                    'synced' => true,
                    'iccid' => $iccid,
                    'lpa' => $lpaValue,
                    'smdp_address' => $smdpValue,
                ]);
                $p->fulfillment_payload = $fulfillment;
                $p->save();
            }
        }

        $fulfillment = is_array($p->fulfillment_payload) ? $p->fulfillment_payload : $fulfillment;

        $providerPayload = is_array($p->provider_payload) ? $p->provider_payload : [];
        $bundle = is_array(data_get($providerPayload, 'bundle')) ? (array) data_get($providerPayload, 'bundle') : [];
        $validityStr = (string) ($bundle['validity'] ?? '');
        $days = $parseDays($validityStr);

        $purchasedAt = $p->created_at ? $p->created_at->copy() : now();
        $expiresAt = $days ? $purchasedAt->copy()->addDays($days) : null;

        $isExpired = $expiresAt ? $expiresAt->isPast() : false;
        if ($filter === 'expired' && ! $isExpired) {
            continue;
        }
        if ($filter === 'valid' && $isExpired) {
            continue;
        }

        $glo = is_array($fulfillment['gloesim'] ?? null) ? $fulfillment['gloesim'] : [];
        $lpaValue = (string) ($fulfillment['lpa'] ?? (data_get($glo, 'qr_code_text') ?? ''));
        $activationValue = (string) ($fulfillment['activation_code'] ?? '');
        $smdpValue = (string) ($fulfillment['smdp_address'] ?? (data_get($glo, 'smdp_address') ?? ''));
        $qrPayload = $qr->esimQrPayload($lpaValue !== '' ? $lpaValue : $activationValue, $smdpValue);
        $iccidValue = (string) ($fulfillment['iccid'] ?? (data_get($glo, 'iccid') ?? ''));
        $isFulfilled = (bool) ($fulfillment['ok'] ?? false) || (string) $p->status === 'fulfilled';
        if (trim($iccidValue) === '' || ! $isFulfilled) {
            continue;
        }

        $computedSynced = trim($qrPayload) !== '';
        if ($computedSynced && (($fulfillment['synced'] ?? false) !== true || ($fulfillment['ok'] ?? false) !== true)) {
            $p->fulfillment_payload = array_merge($fulfillment, [
                'ok' => true,
                'pending' => false,
                'synced' => true,
                'iccid' => $iccidValue,
                'smdp_address' => $smdpValue,
                'lpa' => $lpaValue,
            ]);
            $p->save();
            $fulfillment = is_array($p->fulfillment_payload) ? $p->fulfillment_payload : $fulfillment;
        }

        $seenIccids[$iccidValue] = true;

        $items[] = [
            'id' => (int) $p->id,
            'provider' => (string) $p->provider,
            'reference' => (string) $p->provider_reference,
            'asset_type' => (string) $p->asset_type,
            'asset_id' => (string) $p->asset_id,
            'bundle_id' => (string) $p->bundle_id,
            'package_type' => (string) $p->package_type,
            'purchased_at' => $purchasedAt->toIso8601String(),
            'expires_at' => $expiresAt ? $expiresAt->toIso8601String() : null,
            'status' => $isExpired ? 'expired' : 'valid',
            'asset' => [
                'name' => (string) data_get($providerPayload, 'asset.name', ''),
                'code' => (string) data_get($providerPayload, 'asset.code', ''),
                'flag' => (string) data_get($providerPayload, 'asset.flag', ''),
                'flag_url' => (string) data_get($providerPayload, 'asset.flag_url', ''),
            ],
            'bundle' => [
                'name' => (string) ($bundle['name'] ?? ''),
                'data' => (string) ($bundle['data'] ?? ''),
                'validity' => $validityStr,
                'price_formatted' => (string) ($bundle['price_formatted'] ?? ''),
            ],
            'esim' => [
                'iccid' => $iccidValue,
                'esim_id' => (string) ($fulfillment['esim_id'] ?? ''),
                'activation_code' => $activationValue,
                'lpa' => $lpaValue,
                'puk_code' => (string) ($fulfillment['puk_code'] ?? ''),
                'number' => (string) ($fulfillment['number'] ?? ''),
                'esim_status' => (string) ($fulfillment['esim_status'] ?? ''),
                'direct_installation_link_ios' => (string) ($fulfillment['direct_installation_link_ios'] ?? ''),
                'direct_installation_link_android' => (string) ($fulfillment['direct_installation_link_android'] ?? ''),
                'qr_code_url' => (string) ($fulfillment['qr_code_url'] ?? ''),
                'qr_payload' => $qrPayload,
                'smdp_address' => $smdpValue,
                'qr_code_data_url' => $qrPayload !== '' ? $qr->svgDataUrl($qrPayload) : '',
            ],
        ];
    }

    $deduped = [];
    $seenKeys = [];
    foreach ($items as $it) {
        $bundleId = trim((string) ($it['bundle_id'] ?? ''));
        $provider = (string) ($it['provider'] ?? '');
        $reference = (string) ($it['reference'] ?? '');
        $paymentId = (int) ($it['id'] ?? 0);

        $key = $bundleId !== ''
            ? 'bundle:'.$bundleId
            : ($reference !== '' ? 'ref:'.$provider.':'.$reference : 'payment:'.$paymentId);

        if (isset($seenKeys[$key])) {
            continue;
        }
        $seenKeys[$key] = true;
        $deduped[] = $it;
    }
    $items = $deduped;

    $total = count($items);
    $offset = ($page - 1) * $perPage;
    $slice = array_slice($items, $offset, $perPage);

    return response()->json([
        'filter' => $filter,
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'has_more' => ($offset + count($slice)) < $total,
        'items' => $slice,
    ]);
})->name('myesims.list');

Route::post('/api/cryptomus/webhook', function (Request $request, GloEsimService $gloEsim, QrCodeService $qr) {
    $data = $request->json()->all();
    if (! is_array($data)) {
        return response()->json(['message' => 'Invalid payload.'], 400);
    }

    $sign = (string) ($data['sign'] ?? '');
    unset($data['sign']);

    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $json = is_string($json) ? $json : '';
    $key = (string) (config('services.cryptomus.payment_key') ?: env('CRYPTOMUS_PAYMENT_KEY', ''));
    $key = trim($key, " \t\n\r\0\x0B\"'");
    $expected = md5(base64_encode($json).$key);

    if ($sign === '' || ! hash_equals($expected, $sign)) {
        return response()->json(['message' => 'Invalid signature.'], 401);
    }

    $orderId = (string) ($data['order_id'] ?? '');
    $status = (string) ($data['status'] ?? '');
    $isFinal = (bool) ($data['is_final'] ?? false);

    if ($orderId === '') {
        return response()->json(['ok' => true]);
    }

    $payment = Payment::where('provider', 'cryptomus')->where('provider_reference', $orderId)->first();
    if (! $payment) {
        return response()->json(['ok' => true]);
    }

    $payment->provider_payload = array_merge($payment->provider_payload ?? [], ['webhook' => $data]);
    $payment->status = $status;
    $payment->save();

    if (! $isFinal || ! in_array($status, ['paid', 'paid_over'], true)) {
        return response()->json(['ok' => true]);
    }

    if (($payment->fulfillment_payload['ok'] ?? false) === true) {
        return response()->json(['ok' => true]);
    }

    $user = $payment->user;
    $email = (string) ($user?->email ?? '');
    if ($email === '') {
        return response()->json(['ok' => true]);
    }

    $fulfillment = $gloEsim->fulfillEsim($payment->bundle_id, 'cm_'.$orderId, $email, (string) $payment->package_type, [
        'name' => (string) ($user?->name ?? ''),
    ]);
    if (($fulfillment['ok'] ?? false) !== true) {
        $payment->fulfillment_payload = array_filter([
            'ok' => false,
            'pending' => (bool) ($fulfillment['pending'] ?? false),
            'attempted_at' => now()->toIso8601String(),
            'error' => (string) ($fulfillment['error'] ?? ''),
            'esim_id' => $fulfillment['esim_id'] ?? null,
            'order_id' => $fulfillment['order_id'] ?? null,
        ], fn ($v) => $v !== null);
        $payment->save();

        return response()->json(['ok' => true]);
    }

    $esimId = trim((string) ($fulfillment['esim_id'] ?? ''));
    $iccid = (string) ($fulfillment['iccid'] ?? '');
    if ($esimId === '' && $iccid !== '') {
        $rawList = $gloEsim->dealerMyEsimsRaw();
        $list = is_array($rawList) ? (data_get($rawList, 'data.items') ?? data_get($rawList, 'data') ?? null) : null;
        if (is_array($list)) {
            foreach ($list as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rowIccid = trim((string) (data_get($row, 'iccid') ?? ''));
                if ($rowIccid !== '' && $rowIccid === $iccid) {
                    $esimId = trim((string) (data_get($row, 'id') ?? data_get($row, 'esim_id') ?? ''));
                    break;
                }
            }
        }
    }

    $details = $esimId !== '' ? $gloEsim->getEsimDetails($esimId) : null;
    $detailsOk = is_array($details) && (($details['ok'] ?? false) === true);
    $gloItem = $detailsOk && is_array($details['gloesim_item'] ?? null) ? $details['gloesim_item'] : null;

    $iccid = (string) (($detailsOk ? ($details['iccid'] ?? '') : ($fulfillment['iccid'] ?? '')) ?? '');
    $activationCode = (string) (($detailsOk ? ($details['activation_code'] ?? '') : ($fulfillment['activation_code'] ?? '')) ?? '');
    $lpa = (string) (($detailsOk ? ($details['lpa'] ?? '') : ($fulfillment['lpa'] ?? '')) ?? '');
    $qrCodeUrl = (string) (($detailsOk ? ($details['qr_code_url'] ?? '') : ($fulfillment['qr_code_url'] ?? '')) ?? '');
    $smdpAddress = (string) (($detailsOk ? ($details['smdp_address'] ?? '') : ($fulfillment['smdp_address'] ?? '')) ?? '');
    $esimStatus = (string) (($detailsOk ? ($details['esim_status'] ?? '') : ($fulfillment['esim_status'] ?? '')) ?? '');
    $number = $detailsOk ? ($details['number'] ?? null) : ($fulfillment['number'] ?? null);
    $pukCode = (string) (($detailsOk ? ($details['puk_code'] ?? '') : ($fulfillment['puk_code'] ?? '')) ?? '');
    $installIos = (string) (($detailsOk ? ($details['direct_installation_link_ios'] ?? '') : ($fulfillment['direct_installation_link_ios'] ?? '')) ?? '');
    $installAndroid = (string) (($detailsOk ? ($details['direct_installation_link_android'] ?? '') : ($fulfillment['direct_installation_link_android'] ?? '')) ?? '');
    $resolvedEsimId = (string) (($detailsOk ? ($details['esim_id'] ?? '') : ($fulfillment['esim_id'] ?? '')) ?? $esimId);

    $qrPayload = $qr->esimQrPayload($lpa !== '' ? $lpa : $activationCode, $smdpAddress);
    $synced = trim($iccid) !== '' && $qrPayload !== '';
    $payment->fulfillment_payload = array_filter([
        'ok' => $synced,
        'pending' => ! $synced,
        'synced' => $synced,
        'attempted_at' => now()->toIso8601String(),
        'order_id' => (string) ($fulfillment['order_id'] ?? ''),
        'esim_id' => $resolvedEsimId,
        'iccid' => $iccid,
        'number' => $number,
        'esim_status' => $esimStatus,
        'smdp_address' => $smdpAddress,
        'activation_code' => $activationCode,
        'lpa' => $lpa,
        'puk_code' => $pukCode,
        'qr_code_url' => $qrCodeUrl,
        'direct_installation_link_ios' => $installIos,
        'direct_installation_link_android' => $installAndroid,
        'gloesim' => $gloItem,
    ], fn ($v) => $v !== null && $v !== '');

    $payment->save();

    if (! $synced) {
        return response()->json(['ok' => true]);
    }

    $qrCodeDataUrl = $qrPayload !== '' ? $qr->svgDataUrl($qrPayload) : '';
    $qrSvg = $qrPayload !== '' ? $qr->svgString($qrPayload) : '';

    $subject = 'Your Spacechip eSIM is ready';
    $html = '<div style="font-family: Instrument Sans, Arial, sans-serif; background:#f7f7f8; padding:24px;">'
        .'<div style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:18px; border:1px solid rgba(20,84,84,.12); overflow:hidden;">'
        .'<div style="padding:18px 20px; background: linear-gradient(90deg,#f27457,#145454); color:#fff;">'
        .'<div style="font-weight:900; letter-spacing:.02em;">Spacechip</div>'
        .'<div style="opacity:.9; margin-top:6px;">Your eSIM delivery details</div>'
        .'</div>'
        .'<div style="padding:18px 20px; color:#0b1a1a;">'
        .'<div style="border-top:1px solid rgba(15,31,31,.08); padding-top:14px;">'
        .'<div style="font-weight:800; margin-bottom:10px;">eSIM Details</div>'
        .($iccid !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">ICCID:</span> '.$iccid.'</div>' : '')
        .($activationCode !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">Activation Code:</span> '.$activationCode.'</div>' : '')
        .($smdpAddress !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">SM-DP+ Address:</span> '.$smdpAddress.'</div>' : '')
        .($lpa !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">LPA:</span> '.$lpa.'</div>' : '')
        .($qrCodeUrl !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">QR Code:</span> <a href="'.$qrCodeUrl.'" style="color:#145454; font-weight:800;">Open QR Code</a></div>' : '')
        .($qrCodeDataUrl !== '' ? '<div style="margin-top:12px;"><div style="color:rgba(15,31,31,.62); font-weight:800; margin-bottom:8px;">QR Code</div><img alt="eSIM QR code" src="'.$qrCodeDataUrl.'" style="width:220px; max-width:100%; border-radius:14px; border:1px solid rgba(15,31,31,.10); background:#fff; padding:10px;"></div>' : '')
        .'</div>'
        .'<div style="margin-top:14px; color:rgba(15,31,31,.62); font-size:13px; line-height:1.5;">'
        .'Open your phone settings → Cellular/Mobile Data → Add eSIM, then scan the QR code (or enter the activation code manually).'
        .'</div>'
        .'</div>'
        .'</div>'
        .'</div>';

    try {
        \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($email, $subject, $html, $qrSvg) {
            $message->to($email)->subject($subject)->html($html);
            if (is_string($qrSvg) && $qrSvg !== '') {
                $message->attachData($qrSvg, 'esim-qr.svg', ['mime' => 'image/svg+xml']);
            }
        });
    } catch (\Throwable) {
    }

    return response()->json(['ok' => true]);
});

Route::middleware(['auth'])->get('/crypto/return', function () {
    return view('cryptomus-return', ['orderId' => (string) request('order_id', '')]);
})->name('cryptomus.return');

Route::middleware(['auth'])->get('/api/cryptomus/status', function (Request $request, CryptomusService $cryptomus, GloEsimService $gloEsim, QrCodeService $qr) {
    $orderId = (string) $request->query('order_id', '');
    if ($orderId === '') {
        return response()->json(['message' => 'Missing order_id.'], 422);
    }

    $payment = Payment::where('provider', 'cryptomus')->where('provider_reference', $orderId)->first();
    if (! $payment || (int) $payment->user_id !== (int) Auth::id()) {
        return response()->json(['message' => 'Payment not found.'], 404);
    }

    $res = $cryptomus->paymentInfo(['order_id' => $orderId]);
    if (! is_array($res) || (int) data_get($res, 'state', 1) !== 0) {
        return response()->json(['message' => 'Unable to fetch payment status.'], 502);
    }

    $result = data_get($res, 'result', []);
    $status = (string) data_get($result, 'status', '');
    $isFinal = (bool) data_get($result, 'is_final', false);

    $payment->provider_payload = array_merge($payment->provider_payload ?? [], ['status' => $result]);
    $payment->status = $status !== '' ? $status : $payment->status;
    $payment->save();

    if ($isFinal && in_array($status, ['paid', 'paid_over'], true) && (($payment->fulfillment_payload['ok'] ?? false) !== true)) {
        $attemptedAt = (string) ($payment->fulfillment_payload['attempted_at'] ?? '');
        $shouldAttempt = true;
        if ($attemptedAt !== '') {
            $ts = strtotime($attemptedAt);
            if ($ts !== false) {
                $shouldAttempt = $ts <= now()->subSeconds(5)->getTimestamp();
            }
        }

        if ($shouldAttempt) {
            $email = (string) (Auth::user()?->email ?? '');
            $pendingEsimId = (string) ($payment->fulfillment_payload['esim_id'] ?? '');
            $isPending = (bool) ($payment->fulfillment_payload['pending'] ?? false);

            $fulfillment = $isPending && $pendingEsimId !== ''
                ? $gloEsim->getEsimDetails($pendingEsimId)
                : $gloEsim->fulfillEsim($payment->bundle_id, 'cm_'.$orderId, $email, (string) $payment->package_type, [
                    'name' => (string) (Auth::user()?->name ?? ''),
                ]);

            if (($fulfillment['ok'] ?? false) === true) {
                $esimId = trim((string) ($fulfillment['esim_id'] ?? ''));
                $iccid = (string) ($fulfillment['iccid'] ?? '');
                if ($esimId === '' && $iccid !== '') {
                    $rawList = $gloEsim->dealerMyEsimsRaw();
                    $list = is_array($rawList) ? (data_get($rawList, 'data.items') ?? data_get($rawList, 'data') ?? null) : null;
                    if (is_array($list)) {
                        foreach ($list as $row) {
                            if (! is_array($row)) {
                                continue;
                            }
                            $rowIccid = trim((string) (data_get($row, 'iccid') ?? ''));
                            if ($rowIccid !== '' && $rowIccid === $iccid) {
                                $esimId = trim((string) (data_get($row, 'id') ?? data_get($row, 'esim_id') ?? ''));
                                break;
                            }
                        }
                    }
                }

                $details = $esimId !== '' ? $gloEsim->getEsimDetails($esimId) : null;
                $detailsOk = is_array($details) && (($details['ok'] ?? false) === true);
                $gloItem = $detailsOk && is_array($details['gloesim_item'] ?? null) ? $details['gloesim_item'] : null;

                $iccid = (string) (($detailsOk ? ($details['iccid'] ?? '') : ($fulfillment['iccid'] ?? '')) ?? '');
                $activationCode = (string) (($detailsOk ? ($details['activation_code'] ?? '') : ($fulfillment['activation_code'] ?? '')) ?? '');
                $lpa = (string) (($detailsOk ? ($details['lpa'] ?? '') : ($fulfillment['lpa'] ?? '')) ?? '');
                $qrCodeUrl = (string) (($detailsOk ? ($details['qr_code_url'] ?? '') : ($fulfillment['qr_code_url'] ?? '')) ?? '');
                $smdpAddress = (string) (($detailsOk ? ($details['smdp_address'] ?? '') : ($fulfillment['smdp_address'] ?? '')) ?? '');
                $esimStatus = (string) (($detailsOk ? ($details['esim_status'] ?? '') : ($fulfillment['esim_status'] ?? '')) ?? '');
                $number = $detailsOk ? ($details['number'] ?? null) : ($fulfillment['number'] ?? null);
                $pukCode = (string) (($detailsOk ? ($details['puk_code'] ?? '') : ($fulfillment['puk_code'] ?? '')) ?? '');
                $installIos = (string) (($detailsOk ? ($details['direct_installation_link_ios'] ?? '') : ($fulfillment['direct_installation_link_ios'] ?? '')) ?? '');
                $installAndroid = (string) (($detailsOk ? ($details['direct_installation_link_android'] ?? '') : ($fulfillment['direct_installation_link_android'] ?? '')) ?? '');
                $resolvedEsimId = (string) (($detailsOk ? ($details['esim_id'] ?? '') : ($fulfillment['esim_id'] ?? '')) ?? $esimId);

                $qrPayload = $qr->esimQrPayload($lpa !== '' ? $lpa : $activationCode, $smdpAddress);
                $synced = trim($iccid) !== '' && $qrPayload !== '';
                $payment->fulfillment_payload = array_filter([
                    'ok' => $synced,
                    'pending' => ! $synced,
                    'synced' => $synced,
                    'attempted_at' => now()->toIso8601String(),
                    'order_id' => (string) ($fulfillment['order_id'] ?? ($payment->fulfillment_payload['order_id'] ?? '')),
                    'esim_id' => $resolvedEsimId,
                    'iccid' => $iccid,
                    'number' => $number,
                    'esim_status' => $esimStatus,
                    'smdp_address' => $smdpAddress,
                    'activation_code' => $activationCode,
                    'lpa' => $lpa,
                    'puk_code' => $pukCode,
                    'qr_code_url' => $qrCodeUrl,
                    'direct_installation_link_ios' => $installIos,
                    'direct_installation_link_android' => $installAndroid,
                    'gloesim' => $gloItem,
                ], fn ($v) => $v !== null && $v !== '');
                $payment->save();

                if (! $synced) {
                    return response()->json([
                        'order_id' => $orderId,
                        'status' => $status,
                        'is_final' => $isFinal,
                        'fulfilled' => false,
                        'synced' => false,
                        'fulfillment_error' => '',
                    ]);
                }

                $qrCodeDataUrl = $qrPayload !== '' ? $qr->svgDataUrl($qrPayload) : '';
                $qrSvg = $qrPayload !== '' ? $qr->svgString($qrPayload) : '';

                $subject = 'Your Spacechip eSIM is ready';
                $html = '<div style="font-family: Instrument Sans, Arial, sans-serif; background:#f7f7f8; padding:24px;">'
                    .'<div style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:18px; border:1px solid rgba(20,84,84,.12); overflow:hidden;">'
                    .'<div style="padding:18px 20px; background: linear-gradient(90deg,#f27457,#145454); color:#fff;">'
                    .'<div style="font-weight:900; letter-spacing:.02em;">Spacechip</div>'
                    .'<div style="opacity:.9; margin-top:6px;">Your eSIM delivery details</div>'
                    .'</div>'
                    .'<div style="padding:18px 20px; color:#0b1a1a;">'
                    .'<div style="border-top:1px solid rgba(15,31,31,.08); padding-top:14px;">'
                    .'<div style="font-weight:800; margin-bottom:10px;">eSIM Details</div>'
                    .($iccid !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">ICCID:</span> '.$iccid.'</div>' : '')
                    .($activationCode !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">Activation Code:</span> '.$activationCode.'</div>' : '')
                    .($smdpAddress !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">SM-DP+ Address:</span> '.$smdpAddress.'</div>' : '')
                    .($lpa !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">LPA:</span> '.$lpa.'</div>' : '')
                    .($qrCodeUrl !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:700;">QR Code:</span> <a href="'.$qrCodeUrl.'" style="color:#145454; font-weight:800;">Open QR Code</a></div>' : '')
                    .($qrCodeDataUrl !== '' ? '<div style="margin-top:12px;"><div style="color:rgba(15,31,31,.62); font-weight:800; margin-bottom:8px;">QR Code</div><img alt="eSIM QR code" src="'.$qrCodeDataUrl.'" style="width:220px; max-width:100%; border-radius:14px; border:1px solid rgba(15,31,31,.10); background:#fff; padding:10px;"></div>' : '')
                    .'</div>'
                    .'<div style="margin-top:14px; color:rgba(15,31,31,.62); font-size:13px; line-height:1.5;">'
                    .'Open your phone settings → Cellular/Mobile Data → Add eSIM, then scan the QR code (or enter the activation code manually).'
                    .'</div>'
                    .'</div>'
                    .'</div>'
                    .'</div>';

                try {
                    \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($email, $subject, $html, $qrSvg) {
                        $message->to($email)->subject($subject)->html($html);
                        if (is_string($qrSvg) && $qrSvg !== '') {
                            $message->attachData($qrSvg, 'esim-qr.svg', ['mime' => 'image/svg+xml']);
                        }
                    });
                } catch (\Throwable) {
                }
            } else {
                $payment->fulfillment_payload = array_filter([
                    'ok' => false,
                    'pending' => (bool) ($fulfillment['pending'] ?? $isPending),
                    'attempted_at' => now()->toIso8601String(),
                    'error' => (string) ($fulfillment['error'] ?? ''),
                    'esim_id' => $fulfillment['esim_id'] ?? ($pendingEsimId !== '' ? $pendingEsimId : null),
                    'order_id' => $fulfillment['order_id'] ?? ($payment->fulfillment_payload['order_id'] ?? null),
                ], fn ($v) => $v !== null);
                $payment->save();
            }
        }
    }

    return response()->json([
        'order_id' => $orderId,
        'status' => $status,
        'is_final' => $isFinal,
        'fulfilled' => (bool) (is_array($payment->fulfillment_payload) ? ($payment->fulfillment_payload['ok'] ?? false) : false),
        'synced' => (bool) (is_array($payment->fulfillment_payload) ? ($payment->fulfillment_payload['synced'] ?? false) : false),
        'fulfillment_error' => (string) (is_array($payment->fulfillment_payload) ? ($payment->fulfillment_payload['error'] ?? '') : ''),
    ]);
})->name('cryptomus.status');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
