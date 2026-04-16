<?php

use App\Http\Controllers\ProfileController;
use App\Models\Payment;
use App\Models\User;
use App\Models\VirtualNumberMessage;
use App\Models\VirtualNumberProduct;
use App\Models\VirtualNumberSubscription;
use App\Models\WalletTransaction;
use App\Services\CryptomusService;
use App\Services\GloEsimService;
use App\Services\QrCodeService;
use App\Services\TwilioService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
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

Route::middleware(['throttle:10,1'])->post('/api/auth/register', function (Request $request) {
    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255'],
        'password' => ['required', 'string', 'min:8'],
        'device_name' => ['nullable', 'string', 'max:255'],
    ]);

    $email = mb_strtolower(trim((string) $data['email']));
    if (User::query()->where('email', $email)->exists()) {
        return response()->json(['message' => 'Email already in use.'], 422);
    }

    $user = User::create([
        'name' => (string) $data['name'],
        'email' => $email,
        'password' => (string) $data['password'],
    ]);

    try {
        $user->sendEmailVerificationNotification();
    } catch (Throwable) {
    }

    $token = $user->createToken(trim((string) ($data['device_name'] ?? 'mobile')) ?: 'mobile')->plainTextToken;

    return response()->json([
        'ok' => true,
        'token_type' => 'Bearer',
        'token' => $token,
        'user' => [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
        ],
    ]);
})->name('api.auth.register');

Route::middleware(['throttle:20,1'])->post('/api/auth/login', function (Request $request) {
    $data = $request->validate([
        'email' => ['required', 'string', 'email'],
        'password' => ['required', 'string'],
        'device_name' => ['nullable', 'string', 'max:255'],
    ]);

    $email = mb_strtolower(trim((string) $data['email']));
    $user = User::query()->where('email', $email)->first();
    if (! $user || ! Hash::check((string) $data['password'], (string) $user->password)) {
        return response()->json(['message' => 'Invalid credentials.'], 422);
    }

    $token = $user->createToken(trim((string) ($data['device_name'] ?? 'mobile')) ?: 'mobile')->plainTextToken;

    return response()->json([
        'ok' => true,
        'token_type' => 'Bearer',
        'token' => $token,
        'user' => [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'wallet_balance_minor' => (int) ($user->wallet_balance_minor ?? 0),
        ],
    ]);
})->name('api.auth.login');

Route::middleware(['auth:sanctum', 'throttle:30,1'])->post('/api/auth/logout', function (Request $request) {
    $token = $request->user()?->currentAccessToken();
    if ($token) {
        $token->delete();
    }

    return response()->json(['ok' => true]);
})->name('api.auth.logout');

Route::middleware(['auth:sanctum', 'throttle:60,1'])->get('/api/auth/me', function (Request $request) {
    $user = $request->user();
    if (! $user) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    return response()->json([
        'ok' => true,
        'user' => [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'wallet_balance_minor' => (int) ($user->wallet_balance_minor ?? 0),
        ],
    ]);
})->name('api.auth.me');

Route::middleware(['auth:sanctum', 'throttle:10,1'])->post('/api/auth/resend-verification', function (Request $request) {
    $user = $request->user();
    if (! $user) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
    if ($user->hasVerifiedEmail()) {
        return response()->json(['ok' => true, 'message' => 'Email already verified.']);
    }

    try {
        $user->sendEmailVerificationNotification();
    } catch (Throwable) {
    }

    return response()->json(['ok' => true]);
})->name('api.auth.resend_verification');

Route::middleware(['throttle:5,1'])->post('/api/auth/forgot-password', function (Request $request) {
    $data = $request->validate([
        'email' => ['required', 'string', 'email'],
    ]);
    $status = Password::sendResetLink(['email' => (string) $data['email']]);
    if ($status !== Password::RESET_LINK_SENT) {
        return response()->json(['message' => 'Unable to send reset link.'], 422);
    }

    return response()->json(['ok' => true]);
})->name('api.auth.forgot_password');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/virtual-numbers', function (Request $request) {
        return redirect()->route('dashboard.virtual.index', $request->query());
    })->name('virtual.index');

    Route::get('/virtual-numbers/checkout', function (Request $request) {
        return redirect()->route('dashboard.virtual.checkout', $request->query());
    })->name('virtual.checkout');

    Route::get('/virtual-numbers/my', function (Request $request) {
        return redirect()->route('dashboard.virtual.my', $request->query());
    })->name('virtual.my');

    Route::get('/virtual-numbers/{country}', function (string $country, Request $request) {
        return redirect()->route('dashboard.virtual.country', array_merge(['country' => $country], $request->query()));
    })->where('country', '[A-Za-z]{2}')->name('virtual.country');

    Route::get('/dashboard/virtual-numbers', function () {
        return view('virtual-numbers.index', [
            'indexUrl' => route('dashboard.virtual.index'),
            'myUrl' => route('dashboard.virtual.my'),
            'countriesApiBase' => '/api/virtual-numbers/countries?context=dashboard',
        ]);
    })->name('dashboard.virtual.index');

    Route::get('/dashboard/virtual-numbers/checkout', function (Request $request, TwilioService $twilio) {
        $productId = (int) $request->query('product_id', 0);
        $phoneNumber = trim((string) $request->query('phone_number', ''));
        $numberType = trim((string) $request->query('number_type', ''));
        $numberType = in_array($numberType, ['Local', 'Mobile', 'TollFree'], true) ? $numberType : '';
        if ($productId <= 0 || $phoneNumber === '') {
            abort(404);
        }

        $product = VirtualNumberProduct::query()->where('active', true)->find($productId);
        if (! $product) {
            abort(404);
        }

        $priceCurrency = strtoupper((string) ($product->currency ?? 'USD'));
        $priceMinor = (int) $product->monthly_amount_minor;
        $priceFormatted = $priceCurrency.' '.number_format($priceMinor / 100, 2);
        if ($twilio->isConfigured() && $numberType !== '') {
            $pricing = $twilio->phoneNumberPricing((string) $product->country_iso);
            if (($pricing['ok'] ?? false) === true && is_array($pricing['prices_minor'] ?? null)) {
                $pricesMinor = (array) $pricing['prices_minor'];
                $cur = (string) ($pricing['currency'] ?? $priceCurrency);
                $minor = isset($pricesMinor[$numberType]) && is_numeric($pricesMinor[$numberType]) ? (int) $pricesMinor[$numberType] : null;
                if (is_int($minor) && $minor > 0) {
                    $priceCurrency = strtoupper(trim($cur)) !== '' ? strtoupper(trim($cur)) : $priceCurrency;
                    $priceMinor = $minor;
                    $priceFormatted = $twilio->formatMoney($minor, $priceCurrency);
                }
            }
        }

        return view('virtual-numbers.checkout', [
            'product' => $product,
            'phoneNumber' => $phoneNumber,
            'numberType' => $numberType,
            'priceCurrency' => $priceCurrency,
            'priceMinor' => $priceMinor,
            'priceFormatted' => $priceFormatted,
            'indexUrl' => route('dashboard.virtual.index'),
            'myUrl' => route('dashboard.virtual.my'),
        ]);
    })->name('dashboard.virtual.checkout');

    Route::get('/dashboard/virtual-numbers/my', function () {
        $subs = VirtualNumberSubscription::with('product')
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return view('virtual-numbers.my', [
            'subs' => $subs,
            'indexUrl' => route('dashboard.virtual.index'),
        ]);
    })->name('dashboard.virtual.my');

    Route::get('/dashboard/virtual-numbers/{country}', function (string $country, TwilioService $twilio) {
        $country = strtoupper(trim($country));
        if (! preg_match('/^[A-Z]{2}$/', $country)) {
            abort(404);
        }

        $defaultCurrency = strtoupper(trim((string) env('VIRTUAL_NUMBER_DEFAULT_CURRENCY', 'USD')));
        $defaultCurrency = $defaultCurrency !== '' ? $defaultCurrency : 'USD';
        $defaultAmountMinor = (int) env('VIRTUAL_NUMBER_DEFAULT_MONTHLY_AMOUNT_MINOR', 500);
        $defaultAmountMinor = $defaultAmountMinor > 0 ? $defaultAmountMinor : 500;

        $countryName = $country;
        if ($twilio->isConfigured()) {
            $cacheKey = 'twilio.countries.v2';
            $cached = Cache::get($cacheKey);
            if (! is_array($cached)) {
                $res = $twilio->availableCountries();
                $cached = (($res['ok'] ?? false) === true && is_array($res['items'] ?? null)) ? $res['items'] : [];
                Cache::put($cacheKey, $cached, now()->addDays(3));
            }
            foreach ($cached as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (strtoupper((string) ($row['country_code'] ?? '')) === $country) {
                    $countryName = (string) ($row['country_name'] ?? $country);
                    break;
                }
            }
        }

        $product = VirtualNumberProduct::query()
            ->where('active', true)
            ->where('country_iso', $country)
            ->orderBy('monthly_amount_minor')
            ->first();

        if (! $product) {
            $product = VirtualNumberProduct::create([
                'country_iso' => $country,
                'label' => $countryName.' Local Number',
                'cap_sms' => true,
                'cap_voice' => true,
                'currency' => $defaultCurrency,
                'monthly_amount_minor' => $defaultAmountMinor,
                'twilio_search_filters' => [],
                'active' => true,
            ]);
        }

        $startingFrom = strtoupper((string) ($product->currency ?? 'USD')).' '.number_format(((int) ($product->monthly_amount_minor ?? 0)) / 100, 2);
        if ($twilio->isConfigured()) {
            $pricing = $twilio->phoneNumberPricing($country);
            if (($pricing['ok'] ?? false) === true && is_array($pricing['prices_minor'] ?? null)) {
                $cur = (string) ($pricing['currency'] ?? 'USD');
                $pricesMinor = (array) $pricing['prices_minor'];
                $mins = array_values(array_filter(array_map(fn ($v) => is_numeric($v) ? (int) $v : null, $pricesMinor)));
                if (! empty($mins)) {
                    $startingFrom = $twilio->formatMoney(min($mins), $cur);
                }
            }
        }

        return view('virtual-numbers.country', [
            'countryIso' => $country,
            'countryName' => $countryName,
            'product' => $product,
            'startingFrom' => $startingFrom,
            'indexUrl' => route('dashboard.virtual.index'),
            'myUrl' => route('dashboard.virtual.my'),
            'checkoutBaseUrl' => route('dashboard.virtual.checkout'),
        ]);
    })->where('country', '[A-Za-z]{2}')->name('dashboard.virtual.country');
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->get('/api/virtual-numbers/countries', function (Request $request, TwilioService $twilio) {
    if (! $twilio->isConfigured()) {
        return response()->json(['message' => 'Twilio is not configured.'], 500);
    }

    $page = max(1, (int) $request->query('page', 1));
    $perPage = max(10, min(120, (int) $request->query('per_page', 60)));
    $q = trim((string) $request->query('q', ''));
    $qLower = mb_strtolower($q);

    $cacheKey = 'twilio.countries.v2';
    $items = Cache::get($cacheKey);
    if (! is_array($items)) {
        $res = $twilio->availableCountries();
        if (($res['ok'] ?? false) !== true) {
            return response()->json(['message' => (string) ($res['error'] ?? 'Unable to fetch countries.')], 502);
        }
        $items = is_array($res['items'] ?? null) ? $res['items'] : [];
        Cache::put($cacheKey, $items, now()->addDays(3));
    }

    $defaultCurrency = strtoupper(trim((string) env('VIRTUAL_NUMBER_DEFAULT_CURRENCY', 'USD')));
    $defaultCurrency = $defaultCurrency !== '' ? $defaultCurrency : 'USD';
    $defaultAmountMinor = (int) env('VIRTUAL_NUMBER_DEFAULT_MONTHLY_AMOUNT_MINOR', 500);
    $defaultAmountMinor = $defaultAmountMinor > 0 ? $defaultAmountMinor : 500;
    $defaultPrice = $defaultCurrency.' '.number_format($defaultAmountMinor / 100, 2);

    if ($qLower !== '') {
        $items = array_values(array_filter($items, function ($row) use ($qLower) {
            if (! is_array($row)) {
                return false;
            }
            $name = mb_strtolower((string) ($row['country_name'] ?? ''));
            $code = mb_strtolower((string) ($row['country_code'] ?? ''));

            return str_contains($name, $qLower) || str_contains($code, $qLower);
        }));
    }

    $total = count($items);
    $offset = ($page - 1) * $perPage;
    $slice = array_slice($items, $offset, $perPage);
    $hasMore = ($offset + count($slice)) < $total;

    $codes = array_values(array_filter(array_map(fn ($r) => is_array($r) ? strtoupper((string) ($r['country_code'] ?? '')) : '', $slice)));
    $minPrices = VirtualNumberProduct::query()
        ->selectRaw('country_iso, MIN(monthly_amount_minor) as amt, MIN(currency) as cur')
        ->where('active', true)
        ->whereIn('country_iso', $codes)
        ->groupBy('country_iso')
        ->get()
        ->keyBy('country_iso');

    $context = (string) $request->query('context', '');
    $context = in_array($context, ['dashboard'], true) ? $context : 'public';

    $payload = [];
    foreach ($slice as $row) {
        if (! is_array($row)) {
            continue;
        }
        $code = strtoupper((string) ($row['country_code'] ?? ''));
        $name = (string) ($row['country_name'] ?? $code);
        $emoji = '';
        if (preg_match('/^[A-Z]{2}$/', $code)) {
            $emoji = mb_chr(ord($code[0]) + 127397).mb_chr(ord($code[1]) + 127397);
        }

        $price = $defaultPrice;
        $min = $minPrices->get($code);
        if ($min) {
            $cur = strtoupper((string) ($min->cur ?? $defaultCurrency));
            $amt = (int) ($min->amt ?? $defaultAmountMinor);
            if ($cur !== '' && $amt > 0) {
                $price = $cur.' '.number_format($amt / 100, 2);
            }
        }

        $payload[] = [
            'country_code' => $code,
            'country_name' => $name,
            'flag' => $emoji !== '' ? $emoji : '☎️',
            'starting_price_formatted' => $price,
            'url' => $context === 'dashboard'
                ? route('dashboard.virtual.country', ['country' => $code])
                : route('virtual.country', ['country' => $code]),
        ];
    }

    return response()->json([
        'ok' => true,
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'has_more' => $hasMore,
        'items' => $payload,
    ]);
})->name('virtual.countries');

Route::middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->get('/api/virtual-numbers/country/{country}', function (string $country, TwilioService $twilio) {
    if (! $twilio->isConfigured()) {
        return response()->json(['message' => 'Twilio is not configured.'], 500);
    }

    $country = strtoupper(trim($country));
    if (! preg_match('/^[A-Z]{2}$/', $country)) {
        return response()->json(['message' => 'Invalid country code.'], 422);
    }

    $defaultCurrency = strtoupper(trim((string) env('VIRTUAL_NUMBER_DEFAULT_CURRENCY', 'USD')));
    $defaultCurrency = $defaultCurrency !== '' ? $defaultCurrency : 'USD';
    $defaultAmountMinor = (int) env('VIRTUAL_NUMBER_DEFAULT_MONTHLY_AMOUNT_MINOR', 500);
    $defaultAmountMinor = $defaultAmountMinor > 0 ? $defaultAmountMinor : 500;

    $cacheKey = 'twilio.countries.v2';
    $items = Cache::get($cacheKey);
    if (! is_array($items)) {
        $res = $twilio->availableCountries();
        if (($res['ok'] ?? false) !== true) {
            return response()->json(['message' => (string) ($res['error'] ?? 'Unable to fetch countries.')], 502);
        }
        $items = is_array($res['items'] ?? null) ? $res['items'] : [];
        Cache::put($cacheKey, $items, now()->addDays(3));
    }

    $countryName = $country;
    foreach ($items as $row) {
        if (! is_array($row)) {
            continue;
        }
        if (strtoupper((string) ($row['country_code'] ?? '')) === $country) {
            $countryName = (string) ($row['country_name'] ?? $country);
            break;
        }
    }

    $product = VirtualNumberProduct::query()
        ->where('active', true)
        ->where('country_iso', $country)
        ->orderBy('monthly_amount_minor')
        ->first();

    if (! $product) {
        $product = VirtualNumberProduct::create([
            'country_iso' => $country,
            'label' => $countryName.' Local Number',
            'cap_sms' => true,
            'cap_voice' => true,
            'currency' => $defaultCurrency,
            'monthly_amount_minor' => $defaultAmountMinor,
            'twilio_search_filters' => [],
            'active' => true,
        ]);
    }

    $startingFromMinor = $defaultAmountMinor;
    $startingFromCurrency = $defaultCurrency;

    $pricing = $twilio->phoneNumberPricing($country);
    if (($pricing['ok'] ?? false) === true && is_array($pricing['prices_minor'] ?? null)) {
        $cur = strtoupper((string) ($pricing['currency'] ?? $defaultCurrency));
        $pricesMinor = (array) $pricing['prices_minor'];
        $mins = array_values(array_filter(array_map(fn ($v) => is_numeric($v) ? (int) $v : null, $pricesMinor)));
        if (! empty($mins)) {
            $startingFromMinor = min($mins);
            $startingFromCurrency = $cur !== '' ? $cur : $defaultCurrency;
        }
    }

    $startingFrom = $twilio->formatMoney($startingFromMinor, $startingFromCurrency);

    return response()->json([
        'ok' => true,
        'country_code' => $country,
        'country_name' => $countryName,
        'product_id' => (int) $product->id,
        'starting_from' => $startingFrom,
    ]);
})->name('virtual.country.meta');

Route::middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->get('/api/virtual-numbers/my', function () {
    $subs = VirtualNumberSubscription::with('product')
        ->where('user_id', Auth::id())
        ->orderByDesc('created_at')
        ->limit(200)
        ->get();

    $items = $subs->map(function (VirtualNumberSubscription $s) {
        $label = $s->product?->label ?: 'Virtual Number';

        return [
            'id' => (int) $s->id,
            'status' => (string) $s->status,
            'phone_number' => (string) ($s->phone_number ?? ''),
            'country_iso' => (string) ($s->country_iso ?? $s->product?->country_iso ?? ''),
            'plan_label' => (string) $label,
            'currency' => (string) ($s->currency ?? 'USD'),
            'monthly_amount_minor' => (int) ($s->monthly_amount_minor ?? 0),
            'current_period_end' => $s->current_period_end ? $s->current_period_end->toIso8601String() : null,
        ];
    })->values()->toArray();

    return response()->json(['ok' => true, 'items' => $items]);
})->name('virtual.my.api');

Route::middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->get('/api/virtual-numbers/available', function (Request $request, TwilioService $twilio) {
    if (! $twilio->isConfigured()) {
        return response()->json(['message' => 'Twilio is not configured.'], 500);
    }

    $productId = (int) $request->query('product_id', 0);
    $limit = (int) $request->query('limit', 20);
    $page = (int) $request->query('page', 0);
    if ($productId <= 0) {
        return response()->json(['message' => 'Missing product_id.'], 422);
    }

    $product = VirtualNumberProduct::query()->where('active', true)->find($productId);
    if (! $product) {
        return response()->json(['message' => 'Product not found.'], 404);
    }

    $cacheKey = 'twilio.available.any.v1.'.sha1($product->id.'|'.$product->country_iso.'|'.((int) $product->cap_sms).'|'.((int) $product->cap_voice).'|'.$limit.'|'.$page);
    $cached = Cache::get($cacheKey);
    if (is_array($cached)) {
        return response()->json($cached);
    }

    $res = $twilio->availableAnyNumbers(
        (string) $product->country_iso,
        false,
        false,
        $limit,
        array_merge(is_array($product->twilio_search_filters) ? $product->twilio_search_filters : [], $page > 0 ? ['Page' => $page] : [])
    );
    if (($res['ok'] ?? false) !== true) {
        return response()->json(['message' => (string) ($res['error'] ?? 'Unable to fetch numbers.')], 502);
    }

    $items = $res['items'] ?? [];
    $pricing = $twilio->phoneNumberPricing((string) $product->country_iso);
    $currency = (($pricing['ok'] ?? false) === true) ? (string) ($pricing['currency'] ?? 'USD') : 'USD';
    $pricesMinor = (($pricing['ok'] ?? false) === true && is_array($pricing['prices_minor'] ?? null)) ? (array) $pricing['prices_minor'] : [];

    $items = array_map(function ($it) use ($twilio, $pricesMinor, $currency) {
        if (! is_array($it)) {
            return $it;
        }
        $type = (string) ($it['number_type'] ?? '');
        $minor = isset($pricesMinor[$type]) && is_numeric($pricesMinor[$type]) ? (int) $pricesMinor[$type] : null;
        $it['monthly_fee_minor'] = $minor;
        $it['monthly_fee_currency'] = $currency;
        $it['monthly_fee_formatted'] = is_int($minor) && $minor > 0 ? $twilio->formatMoney($minor, $currency) : null;

        return $it;
    }, $items);

    $payload = [
        'ok' => true,
        'items' => $items,
        'next_page_uri' => null,
        'has_more' => (bool) ($res['has_more'] ?? false),
        'types_tried' => $res['types_tried'] ?? [],
        'modes_tried' => $res['modes_tried'] ?? [],
    ];
    Cache::put($cacheKey, $payload, now()->addSeconds(30));

    return response()->json($payload);
})->name('virtual.available');

Route::middleware(['auth:sanctum', 'verified', 'throttle:10,1'])->post('/api/virtual-numbers/paystack/initialize', function (Request $request, TwilioService $twilio) {
    $productId = (int) $request->input('product_id', 0);
    $phoneNumber = trim((string) $request->input('phone_number', ''));
    $numberType = trim((string) $request->input('number_type', ''));
    $numberType = in_array($numberType, ['Local', 'Mobile', 'TollFree'], true) ? $numberType : '';
    if ($productId <= 0 || $phoneNumber === '') {
        return response()->json(['message' => 'Missing checkout parameters.'], 422);
    }

    $product = VirtualNumberProduct::query()->where('active', true)->find($productId);
    if (! $product) {
        return response()->json(['message' => 'Product not found.'], 404);
    }

    $paystackCurrency = strtoupper((string) config('services.paystack.currency', 'NGN'));
    $paystackCurrency = $paystackCurrency !== '' ? $paystackCurrency : 'NGN';

    $twilioCurrency = strtoupper((string) ($product->currency ?? 'USD'));
    $twilioAmountMinor = (int) $product->monthly_amount_minor;
    if ($twilio->isConfigured() && $numberType !== '') {
        $pricing = $twilio->phoneNumberPricing((string) $product->country_iso);
        if (($pricing['ok'] ?? false) === true && is_array($pricing['prices_minor'] ?? null)) {
            $pricesMinor = (array) $pricing['prices_minor'];
            $cur = (string) ($pricing['currency'] ?? $twilioCurrency);
            $minor = isset($pricesMinor[$numberType]) && is_numeric($pricesMinor[$numberType]) ? (int) $pricesMinor[$numberType] : null;
            if (is_int($minor) && $minor > 0) {
                $twilioCurrency = strtoupper(trim($cur)) !== '' ? strtoupper(trim($cur)) : $twilioCurrency;
                $twilioAmountMinor = $minor;
            }
        }
    }
    if ($twilioAmountMinor <= 0) {
        return response()->json(['message' => 'Invalid amount.'], 422);
    }

    $usdToNgnRate = (float) env('VIRTUAL_NUMBER_USD_TO_NGN_RATE', 0);
    $currency = $paystackCurrency;
    $amountMinor = $twilioAmountMinor;
    if ($currency !== $twilioCurrency && $twilioCurrency === 'USD' && $currency === 'NGN') {
        if ($usdToNgnRate <= 0) {
            return response()->json(['message' => 'USD payments are not supported on your Paystack account. Set VIRTUAL_NUMBER_USD_TO_NGN_RATE to charge in NGN equivalent.'], 422);
        }
        $amountMinor = (int) round((($twilioAmountMinor / 100) * $usdToNgnRate) * 100);
    }

    $ownerEmail = trim((string) env('PAYSTACK_OWNER_EMAIL', ''));
    $email = $ownerEmail !== '' ? $ownerEmail : (string) (Auth::user()?->email ?? '');
    $email = trim($email);
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return response()->json(['message' => 'Please enter a valid email address.'], 422);
    }

    $reference = (string) Str::uuid();

    Payment::updateOrCreate(
        ['provider' => 'paystack', 'provider_reference' => $reference],
        [
            'user_id' => Auth::id(),
            'status' => 'initialized',
            'asset_type' => 'virtual_number',
            'asset_id' => (string) $product->id,
            'bundle_id' => (string) $product->id,
            'package_type' => 'MONTHLY',
            'currency' => $currency,
            'amount_minor' => $amountMinor,
            'provider_payload' => [
                'virtual_number' => [
                    'product_id' => $product->id,
                    'phone_number' => $phoneNumber,
                    'number_type' => $numberType !== '' ? $numberType : null,
                    'country_iso' => (string) $product->country_iso,
                    'cap_sms' => (bool) $product->cap_sms,
                    'cap_voice' => (bool) $product->cap_voice,
                    'twilio_price_currency' => $twilioCurrency,
                    'twilio_price_minor' => $twilioAmountMinor,
                    'paystack_charge_currency' => $currency,
                    'paystack_charge_minor' => $amountMinor,
                    'usd_to_ngn_rate' => ($currency === 'NGN' && $twilioCurrency === 'USD') ? $usdToNgnRate : null,
                    'paystack_email' => $email,
                ],
            ],
        ]
    );

    $secretKey = (string) (config('services.paystack.secret_key') ?: env('PAYSTACK_SECRET_KEY', ''));
    $secretKey = trim($secretKey, " \t\n\r\0\x0B\"'");
    if ($secretKey === '') {
        return response()->json(['message' => 'Paystack secret key is not configured.'], 500);
    }

    try {
        $response = Http::withToken($secretKey)
            ->acceptJson()
            ->timeout(30)
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $email,
                'amount' => $amountMinor,
                'currency' => $currency,
                'reference' => $reference,
                'metadata' => [
                    'purpose' => 'virtual_number_subscription',
                    'product_id' => (int) $product->id,
                    'phone_number' => $phoneNumber,
                    'number_type' => $numberType !== '' ? $numberType : null,
                ],
            ]);
    } catch (Throwable) {
        return response()->json(['message' => 'Failed to initialize Paystack transaction.'], 502);
    }

    if (! $response->successful()) {
        $body = $response->json();
        $msg = is_array($body) ? (string) (data_get($body, 'message') ?? '') : '';
        $msg = trim($msg);
        if ($msg === '') {
            $msg = 'Failed to initialize Paystack transaction.';
        }

        return response()->json([
            'message' => $msg,
            'paystack' => [
                'status' => $response->status(),
                'currency' => $currency,
                'amount_minor' => $amountMinor,
            ],
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
    ]);
})->name('virtual.paystack.init');

Route::middleware(['auth:sanctum', 'verified', 'throttle:20,1'])->post('/api/virtual-numbers/paystack/verify', function (Request $request, TwilioService $twilio) {
    $reference = trim((string) $request->input('reference', ''));
    if ($reference === '') {
        return response()->json(['message' => 'Missing reference.'], 422);
    }

    $payment = Payment::where('provider', 'paystack')->where('provider_reference', $reference)->first();
    if (! $payment || (int) $payment->user_id !== (int) Auth::id() || (string) $payment->asset_type !== 'virtual_number') {
        return response()->json(['message' => 'Payment not found.'], 404);
    }

    if ((string) $payment->status === 'fulfilled' && is_array($payment->fulfillment_payload)) {
        return response()->json(['ok' => true, 'subscription' => $payment->fulfillment_payload]);
    }

    $secretKey = (string) (config('services.paystack.secret_key') ?: env('PAYSTACK_SECRET_KEY', ''));
    $secretKey = trim($secretKey, " \t\n\r\0\x0B\"'");
    if ($secretKey === '') {
        return response()->json(['message' => 'Paystack secret key is not configured.'], 500);
    }

    try {
        $response = Http::withToken($secretKey)
            ->acceptJson()
            ->timeout(30)
            ->get('https://api.paystack.co/transaction/verify/'.urlencode($reference));
    } catch (Throwable) {
        return response()->json(['message' => 'Failed to verify Paystack transaction.'], 502);
    }

    if (! $response->successful()) {
        return response()->json(['message' => 'Failed to verify Paystack transaction.'], 502);
    }

    $json = $response->json();
    $status = (string) data_get($json, 'data.status', '');
    if ($status !== 'success') {
        return response()->json(['message' => 'Payment not successful.'], 422);
    }

    $customerEmail = trim(mb_strtolower((string) data_get($json, 'data.customer.email', '')));
    $userEmail = trim(mb_strtolower((string) (Auth::user()?->email ?? '')));
    $vn = is_array($payment->provider_payload) ? (data_get($payment->provider_payload, 'virtual_number') ?? null) : null;
    $expectedEmail = is_array($vn) ? trim(mb_strtolower((string) ($vn['paystack_email'] ?? ''))) : '';
    if ($expectedEmail === '') {
        $expectedEmail = $userEmail;
    }
    if ($customerEmail === '' || $customerEmail !== $expectedEmail) {
        return response()->json(['message' => 'Payment not found.'], 404);
    }

    $txCurrency = strtoupper((string) data_get($json, 'data.currency', ''));
    $txAmount = (int) data_get($json, 'data.amount', 0);
    if ($txCurrency === '' || $txAmount <= 0 || $txCurrency !== strtoupper((string) $payment->currency) || $txAmount !== (int) $payment->amount_minor) {
        return response()->json(['message' => 'Payment not found.'], 404);
    }

    $phoneNumber = is_array($vn) ? trim((string) ($vn['phone_number'] ?? '')) : '';
    $productId = is_array($vn) ? (int) ($vn['product_id'] ?? 0) : 0;
    if ($phoneNumber === '' || $productId <= 0) {
        return response()->json(['message' => 'Missing subscription details.'], 422);
    }

    $product = VirtualNumberProduct::query()->where('active', true)->find($productId);
    if (! $product) {
        return response()->json(['message' => 'Product not found.'], 404);
    }

    $payAuth = data_get($json, 'data.authorization', []);
    $authCode = (string) data_get($payAuth, 'authorization_code', '');
    $customerCode = (string) data_get($json, 'data.customer.customer_code', '');
    if (trim($authCode) === '' || trim($customerCode) === '') {
        return response()->json(['message' => 'Subscription billing authorization missing.'], 502);
    }

    $smsUrl = (string) (config('services.twilio.sms_webhook_url') ?: url('/api/twilio/sms'));
    $voiceUrl = (string) (config('services.twilio.voice_webhook_url') ?: url('/api/twilio/voice'));
    $purchase = $twilio->purchaseNumber($phoneNumber, $product->cap_sms ? $smsUrl : null, $product->cap_voice ? $voiceUrl : null);
    if (($purchase['ok'] ?? false) !== true) {
        $payment->status = 'paid_failed_provision';
        $payment->fulfillment_payload = [
            'ok' => false,
            'error' => (string) ($purchase['error'] ?? 'Twilio provisioning failed.'),
        ];
        $payment->save();

        return response()->json(['message' => 'Number provisioning failed.'], 502);
    }

    $now = now();
    $sub = VirtualNumberSubscription::create([
        'user_id' => Auth::id(),
        'virtual_number_product_id' => $product->id,
        'status' => 'active',
        'phone_number' => (string) ($purchase['phone_number'] ?? $phoneNumber),
        'country_iso' => (string) $product->country_iso,
        'cap_sms' => (bool) $product->cap_sms,
        'cap_voice' => (bool) $product->cap_voice,
        'provider' => 'twilio',
        'twilio_phone_number_sid' => (string) ($purchase['sid'] ?? ''),
        'currency' => (string) $payment->currency,
        'monthly_amount_minor' => (int) $payment->amount_minor,
        'current_period_start' => $now,
        'current_period_end' => $now->copy()->addMonth(),
        'paystack_customer_code' => $customerCode,
        'paystack_authorization_code' => $authCode,
        'paystack_email' => $expectedEmail !== '' ? $expectedEmail : null,
        'last_charge_reference' => $reference,
        'renewal_failed_count' => 0,
    ]);

    $payload = [
        'ok' => true,
        'subscription_id' => $sub->id,
        'phone_number' => $sub->phone_number,
        'status' => $sub->status,
        'current_period_end' => optional($sub->current_period_end)->toIso8601String(),
    ];

    $payment->status = 'fulfilled';
    $payment->fulfillment_payload = $payload;
    $payment->save();

    $toEmail = trim((string) (Auth::user()?->email ?? ''));
    if ($toEmail !== '' && filter_var($toEmail, FILTER_VALIDATE_EMAIL) !== false) {
        $subject = 'Your Spacechip virtual number is active';
        $phoneText = htmlspecialchars((string) $sub->phone_number, ENT_QUOTES, 'UTF-8');
        $planText = htmlspecialchars((string) ($product->label ?? 'Virtual Number'), ENT_QUOTES, 'UTF-8');
        $renewText = optional($sub->current_period_end)->toDayDateTimeString() ?: '';
        $renewText = htmlspecialchars((string) $renewText, ENT_QUOTES, 'UTF-8');
        $html = '<div style="font-family: Instrument Sans, Arial, sans-serif; background:#f7f7f8; padding:24px;">'
            .'<div style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:18px; border:1px solid rgba(20,84,84,.12); overflow:hidden;">'
            .'<div style="padding:18px 20px; background: linear-gradient(90deg,#f27457,#145454); color:#fff;">'
            .'<div style="font-weight:900; letter-spacing:.02em;">Spacechip</div>'
            .'<div style="opacity:.9; margin-top:6px;">Virtual number activated</div>'
            .'</div>'
            .'<div style="padding:18px 20px; color:#0b1a1a;">'
            .'<div style="font-weight:900; font-size:16px; margin-bottom:10px;">Your number is ready</div>'
            .'<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:800;">Number:</span> '.$phoneText.'</div>'
            .'<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:800;">Plan:</span> '.$planText.'</div>'
            .($renewText !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:800;">Next renewal:</span> '.$renewText.'</div>' : '')
            .'<div style="margin-top:14px; color:rgba(15,31,31,.62); font-size:13px; line-height:1.5;">'
            .'You can receive SMS/MMS and voicemail in your dashboard: Virtual Numbers → My Numbers → Inbox.'
            .'</div>'
            .'</div>'
            .'</div>'
            .'</div>';

        try {
            Mail::send([], [], function ($message) use ($toEmail, $subject, $html) {
                $message->to($toEmail)->subject($subject)->html($html);
            });
        } catch (Throwable) {
        }
    }

    return response()->json(['ok' => true, 'subscription' => $payload]);
})->name('virtual.paystack.verify');

Route::middleware(['auth:sanctum', 'verified', 'throttle:10,1'])->post('/api/virtual-numbers/{subscription}/cancel', function (VirtualNumberSubscription $subscription, TwilioService $twilio) {
    if ((int) $subscription->user_id !== (int) Auth::id()) {
        abort(403);
    }

    if (in_array((string) $subscription->status, ['canceled'], true)) {
        return response()->json(['ok' => true]);
    }

    $sid = (string) ($subscription->twilio_phone_number_sid ?? '');
    if ($sid !== '') {
        $twilio->releaseNumber($sid);
    }

    $subscription->status = 'canceled';
    $subscription->canceled_at = now();
    $subscription->save();

    return response()->json(['ok' => true]);
})->name('virtual.cancel');

Route::middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->get('/api/wallet/balance', function (WalletService $wallet) {
    $userId = (int) Auth::id();
    $bal = $wallet->balanceMinor($userId);

    return response()->json([
        'ok' => true,
        'balance_minor' => $bal,
        'balance_formatted' => $wallet->formatUsd($bal),
    ]);
})->name('wallet.balance');

Route::middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->get('/api/wallet/transactions', function () {
    $items = WalletTransaction::query()
        ->where('user_id', Auth::id())
        ->orderByDesc('created_at')
        ->limit(200)
        ->get(['id', 'direction', 'action', 'amount_minor', 'balance_after_minor', 'currency', 'payment_id', 'meta', 'created_at'])
        ->map(function (WalletTransaction $t) {
            return [
                'id' => (int) $t->id,
                'direction' => (string) $t->direction,
                'action' => (string) $t->action,
                'amount_minor' => (int) $t->amount_minor,
                'balance_after_minor' => (int) $t->balance_after_minor,
                'currency' => (string) ($t->currency ?? 'USD'),
                'payment_id' => $t->payment_id ? (int) $t->payment_id : null,
                'meta' => is_array($t->meta) ? $t->meta : null,
                'created_at' => $t->created_at ? $t->created_at->toIso8601String() : null,
            ];
        })
        ->values()
        ->toArray();

    return response()->json(['ok' => true, 'items' => $items]);
})->name('wallet.transactions');

Route::middleware(['auth:sanctum', 'verified', 'throttle:10,1'])->post('/api/wallet/deposit/paystack/initialize', function (Request $request, WalletService $wallet) {
    $minUsd = (float) env('WALLET_MIN_DEPOSIT_USD', 1);
    $maxUsd = (float) env('WALLET_MAX_DEPOSIT_USD', 5000);
    $usd = (float) $request->input('amount_usd', 0);
    if ($usd <= 0) {
        return response()->json(['message' => 'Invalid amount.'], 422);
    }
    if ($usd < $minUsd) {
        return response()->json(['message' => 'Minimum deposit is $'.number_format($minUsd, 2).'.'], 422);
    }
    if ($usd > $maxUsd) {
        return response()->json(['message' => 'Maximum deposit is $'.number_format($maxUsd, 2).'.'], 422);
    }

    $usdMinor = (int) round($usd * 100);
    if ($usdMinor <= 0) {
        return response()->json(['message' => 'Invalid amount.'], 422);
    }

    $paystackCurrency = strtoupper((string) config('services.paystack.currency', 'NGN'));
    $paystackCurrency = $paystackCurrency !== '' ? $paystackCurrency : 'NGN';

    $rate = (float) env('WALLET_USD_TO_NGN_RATE', 0);
    if ($rate <= 0) {
        $rate = (float) env('VIRTUAL_NUMBER_USD_TO_NGN_RATE', 0);
    }

    $amountMinor = $usdMinor;
    if ($paystackCurrency === 'NGN') {
        if ($rate <= 0) {
            return response()->json(['message' => 'Set WALLET_USD_TO_NGN_RATE to enable deposits via Paystack NGN.'], 422);
        }
        $amountMinor = (int) round((($usdMinor / 100) * $rate) * 100);
    }

    if ($amountMinor <= 0) {
        return response()->json(['message' => 'Invalid amount.'], 422);
    }

    $ownerEmail = trim((string) env('PAYSTACK_OWNER_EMAIL', ''));
    $email = $ownerEmail !== '' ? $ownerEmail : trim((string) (Auth::user()?->email ?? ''));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return response()->json(['message' => 'Please enter a valid email address.'], 422);
    }

    $reference = (string) Str::uuid();

    $payment = Payment::create([
        'user_id' => Auth::id(),
        'provider' => 'paystack',
        'provider_reference' => $reference,
        'status' => 'initialized',
        'asset_type' => 'wallet_deposit',
        'asset_id' => (string) Auth::id(),
        'bundle_id' => 'wallet',
        'package_type' => 'deposit',
        'currency' => $paystackCurrency,
        'amount_minor' => $amountMinor,
        'provider_payload' => [
            'wallet' => [
                'usd_amount_minor' => $usdMinor,
                'usd_amount_formatted' => $wallet->formatUsd($usdMinor),
                'usd_to_ngn_rate' => $paystackCurrency === 'NGN' ? $rate : null,
                'paystack_email' => $email,
            ],
        ],
    ]);

    $secretKey = (string) (config('services.paystack.secret_key') ?: env('PAYSTACK_SECRET_KEY', ''));
    $secretKey = trim($secretKey, " \t\n\r\0\x0B\"'");
    if ($secretKey === '') {
        return response()->json(['message' => 'Paystack secret key is not configured.'], 500);
    }

    try {
        $response = Http::withToken($secretKey)
            ->acceptJson()
            ->timeout(30)
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $email,
                'amount' => $amountMinor,
                'currency' => $paystackCurrency,
                'reference' => $reference,
                'metadata' => [
                    'purpose' => 'wallet_deposit',
                    'usd_amount_minor' => $usdMinor,
                ],
            ]);
    } catch (Throwable) {
        $payment->status = 'init_failed';
        $payment->save();

        return response()->json(['message' => 'Failed to initialize Paystack transaction.'], 502);
    }

    if (! $response->successful()) {
        $payment->status = 'init_failed';
        $payment->provider_payload = array_merge($payment->provider_payload ?? [], ['paystack' => $response->json()]);
        $payment->save();
        $body = $response->json();
        $msg = is_array($body) ? trim((string) (data_get($body, 'message') ?? '')) : '';

        return response()->json(['message' => $msg !== '' ? $msg : 'Failed to initialize Paystack transaction.'], 502);
    }

    $json = $response->json();
    $accessCode = data_get($json, 'data.access_code');
    if (! is_string($accessCode) || $accessCode === '') {
        return response()->json(['message' => 'Paystack did not return an access code.'], 502);
    }

    return response()->json([
        'ok' => true,
        'reference' => $reference,
        'access_code' => $accessCode,
        'amount' => $amountMinor,
        'currency' => $paystackCurrency,
        'email' => $email,
        'wallet_credit_usd_minor' => $usdMinor,
        'wallet_credit_usd_formatted' => $wallet->formatUsd($usdMinor),
    ]);
})->name('wallet.deposit.paystack.init');

Route::middleware(['auth:sanctum', 'verified', 'throttle:20,1'])->post('/api/wallet/deposit/paystack/verify', function (Request $request, WalletService $wallet) {
    $reference = trim((string) $request->input('reference', ''));
    if ($reference === '') {
        return response()->json(['message' => 'Missing reference.'], 422);
    }

    $payment = Payment::where('provider', 'paystack')->where('provider_reference', $reference)->first();
    if (! $payment || (int) $payment->user_id !== (int) Auth::id() || (string) $payment->asset_type !== 'wallet_deposit') {
        return response()->json(['message' => 'Payment not found.'], 404);
    }

    if ((string) $payment->status === 'fulfilled' && is_array($payment->fulfillment_payload) && (($payment->fulfillment_payload['ok'] ?? false) === true)) {
        return response()->json(['ok' => true, 'wallet' => $payment->fulfillment_payload]);
    }

    $secretKey = (string) (config('services.paystack.secret_key') ?: env('PAYSTACK_SECRET_KEY', ''));
    $secretKey = trim($secretKey, " \t\n\r\0\x0B\"'");
    if ($secretKey === '') {
        return response()->json(['message' => 'Paystack secret key is not configured.'], 500);
    }

    try {
        $response = Http::withToken($secretKey)
            ->acceptJson()
            ->timeout(30)
            ->get('https://api.paystack.co/transaction/verify/'.urlencode($reference));
    } catch (Throwable) {
        return response()->json(['message' => 'Failed to verify Paystack transaction.'], 502);
    }

    if (! $response->successful()) {
        return response()->json(['message' => 'Failed to verify Paystack transaction.'], 502);
    }

    $json = $response->json();
    $status = (string) data_get($json, 'data.status', '');
    if ($status !== 'success') {
        return response()->json(['message' => 'Payment not successful.'], 422);
    }

    $txCurrency = strtoupper((string) data_get($json, 'data.currency', ''));
    $txAmount = (int) data_get($json, 'data.amount', 0);
    if ($txCurrency === '' || $txAmount <= 0 || $txCurrency !== strtoupper((string) $payment->currency) || $txAmount !== (int) $payment->amount_minor) {
        return response()->json(['message' => 'Payment not found.'], 404);
    }

    $walletPayload = is_array($payment->provider_payload) ? (data_get($payment->provider_payload, 'wallet') ?? null) : null;
    $usdMinor = is_array($walletPayload) ? (int) ($walletPayload['usd_amount_minor'] ?? 0) : 0;
    $expectedEmail = is_array($walletPayload) ? trim(mb_strtolower((string) ($walletPayload['paystack_email'] ?? ''))) : '';
    $customerEmail = trim(mb_strtolower((string) data_get($json, 'data.customer.email', '')));
    if ($usdMinor <= 0 || $expectedEmail === '' || $customerEmail === '' || $customerEmail !== $expectedEmail) {
        return response()->json(['message' => 'Payment not found.'], 404);
    }

    $tx = $wallet->credit((int) Auth::id(), $usdMinor, 'deposit', [
        'reference' => $reference,
        'paystack_currency' => $txCurrency,
        'paystack_amount_minor' => $txAmount,
    ], (int) $payment->id);

    $balance = $wallet->balanceMinor((int) Auth::id());
    $payload = [
        'ok' => true,
        'wallet_transaction_id' => (int) $tx->id,
        'credited_usd_minor' => $usdMinor,
        'credited_usd_formatted' => $wallet->formatUsd($usdMinor),
        'balance_usd_minor' => $balance,
        'balance_usd_formatted' => $wallet->formatUsd($balance),
    ];

    $payment->status = 'fulfilled';
    $payment->fulfillment_payload = $payload;
    $payment->save();

    $toEmail = trim((string) (Auth::user()?->email ?? ''));
    if ($toEmail !== '' && filter_var($toEmail, FILTER_VALIDATE_EMAIL) !== false) {
        $subject = 'Wallet funded successfully';
        $credited = $wallet->formatUsd($usdMinor);
        $balText = $wallet->formatUsd($balance);
        $html = '<div style="font-family: Instrument Sans, Arial, sans-serif; background:#f7f7f8; padding:24px;">'
            .'<div style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:18px; border:1px solid rgba(20,84,84,.12); overflow:hidden;">'
            .'<div style="padding:18px 20px; background: linear-gradient(90deg,#f27457,#145454); color:#fff;">'
            .'<div style="font-weight:900; letter-spacing:.02em;">Spacechip</div>'
            .'<div style="opacity:.9; margin-top:6px;">Wallet deposit confirmed</div>'
            .'</div>'
            .'<div style="padding:18px 20px; color:#0b1a1a;">'
            .'<div style="font-weight:900; font-size:16px; margin-bottom:10px;">Deposit successful</div>'
            .'<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:800;">Amount:</span> '.$credited.'</div>'
            .'<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:800;">New balance:</span> '.$balText.'</div>'
            .'<div style="margin-top:14px; color:rgba(15,31,31,.62); font-size:13px; line-height:1.5;">'
            .'You can now pay for eSIMs and virtual numbers using your wallet balance.'
            .'</div>'
            .'</div>'
            .'</div>'
            .'</div>';

        try {
            Mail::send([], [], function ($message) use ($toEmail, $subject, $html) {
                $message->to($toEmail)->subject($subject)->html($html);
            });
        } catch (Throwable) {
        }
    }

    return response()->json(['ok' => true, 'wallet' => $payload]);
})->name('wallet.deposit.paystack.verify');

Route::middleware(['auth:sanctum', 'verified', 'throttle:10,1'])->post('/api/wallet/pay/esim', function (Request $request, GloEsimService $gloEsim, WalletService $wallet) {
    $type = (string) $request->input('type', '');
    $id = (string) $request->input('id', '');
    $bundleId = (string) $request->input('bundle', '');
    $packageType = (string) $request->input('package_type', 'DATA-ONLY');
    $topupEsimId = trim((string) $request->input('topup_esim_id', ''));
    if ($type === '' || $id === '' || $bundleId === '') {
        return response()->json(['message' => 'Missing checkout parameters.'], 422);
    }

    $asset = $gloEsim->getAssetDetails($type, $id, $packageType);
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

    $amountMinor = (int) round(((float) $price) * 100);
    if ($amountMinor <= 0) {
        return response()->json(['message' => 'Invalid amount.'], 422);
    }

    if ($topupEsimId !== '') {
        $userPayments = Payment::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('fulfillment_payload')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $allowed = false;
        foreach ($userPayments as $p) {
            $fp = is_array($p->fulfillment_payload) ? $p->fulfillment_payload : [];
            $esimId = trim((string) ($fp['esim_id'] ?? ''));
            if ($esimId !== $topupEsimId) {
                continue;
            }
            $glo = is_array($fp['gloesim'] ?? null) ? $fp['gloesim'] : [];
            $raw = $fp['can_renew'] ?? data_get($glo, 'can_renew');
            $canRenew = false;
            if (is_bool($raw)) {
                $canRenew = $raw;
            } elseif (is_numeric($raw)) {
                $canRenew = ((int) $raw) === 1;
            } elseif (is_string($raw) && trim($raw) !== '') {
                $canRenew = in_array(mb_strtolower(trim($raw)), ['1', 'true', 'yes'], true);
            }
            if ($canRenew) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed) {
            return response()->json(['message' => 'This eSIM cannot be renewed.'], 422);
        }
    }

    $reference = 'w_'.(string) Str::uuid();
    $payment = Payment::create([
        'user_id' => Auth::id(),
        'provider' => 'wallet',
        'provider_reference' => $reference,
        'status' => 'paid',
        'asset_type' => $type,
        'asset_id' => $id,
        'bundle_id' => $bundleId,
        'package_type' => $packageType,
        'currency' => 'USD',
        'amount_minor' => $amountMinor,
        'provider_payload' => [
            'wallet' => [
                'action' => $topupEsimId !== '' ? 'topup_esim' : 'buy_esim',
                'topup_esim_id' => $topupEsimId !== '' ? $topupEsimId : null,
            ],
            'asset' => [
                'name' => (string) ($asset['name'] ?? ''),
                'code' => (string) ($asset['code'] ?? ''),
            ],
            'bundle' => [
                'name' => (string) ($bundle['name'] ?? ''),
                'price' => (float) $price,
            ],
        ],
    ]);

    try {
        $wallet->debit((int) Auth::id(), $amountMinor, $topupEsimId !== '' ? 'topup_esim' : 'buy_esim', [
            'type' => $type,
            'id' => $id,
            'bundle' => $bundleId,
            'package_type' => $packageType,
            'topup_esim_id' => $topupEsimId !== '' ? $topupEsimId : null,
            'reference' => $reference,
        ], (int) $payment->id, (int) env('WALLET_MIN_BALANCE_MINOR', 0));
    } catch (Throwable $e) {
        $payment->status = 'failed_insufficient_wallet';
        $payment->fulfillment_payload = ['ok' => false, 'error' => $e->getMessage()];
        $payment->save();

        return response()->json(['message' => $e->getMessage()], 422);
    }

    $user = Auth::user();
    $email = (string) ($user?->email ?? '');
    $fulfillment = $topupEsimId !== ''
        ? $gloEsim->topupEsim($topupEsimId, $bundleId, $reference, $email, $packageType, ['name' => (string) ($user?->name ?? '')])
        : $gloEsim->fulfillEsim($bundleId, $reference, $email, $packageType, ['name' => (string) ($user?->name ?? '')]);

    if (($fulfillment['ok'] ?? false) !== true) {
        $isPending = (bool) ($fulfillment['pending'] ?? false);
        if ($isPending) {
            $payment->status = 'paid_pending_fulfillment';
            $payment->fulfillment_payload = array_filter([
                'ok' => false,
                'pending' => true,
                'attempted_at' => now()->toIso8601String(),
                'error' => (string) ($fulfillment['error'] ?? ''),
                'topup_esim_id' => $topupEsimId !== '' ? $topupEsimId : null,
                'esim_id' => $fulfillment['esim_id'] ?? null,
                'order_id' => $fulfillment['order_id'] ?? null,
            ], fn ($v) => $v !== null);
            $payment->save();

            return response()->json([
                'ok' => true,
                'reference' => $reference,
                'fulfillment' => [
                    'ok' => false,
                    'pending' => true,
                    'message' => 'Payment confirmed. Preparing your eSIM…',
                ],
            ]);
        }

        try {
            $wallet->credit((int) Auth::id(), $amountMinor, 'refund', [
                'reason' => (string) ($fulfillment['error'] ?? 'Fulfillment failed'),
                'reference' => $reference,
            ], (int) $payment->id);
        } catch (Throwable) {
        }

        $payment->status = 'failed_refunded';
        $payment->fulfillment_payload = ['ok' => false, 'error' => (string) ($fulfillment['error'] ?? 'Fulfillment failed')];
        $payment->save();

        return response()->json(['message' => 'Payment confirmed, but fulfillment failed. Wallet was refunded.'], 502);
    }

    $payload = array_filter([
        'ok' => true,
        'attempted_at' => now()->toIso8601String(),
        'topup_esim_id' => $topupEsimId !== '' ? $topupEsimId : null,
        'esim_id' => $fulfillment['esim_id'] ?? null,
        'order_id' => $fulfillment['order_id'] ?? null,
        'activation_code' => (string) ($fulfillment['activation_code'] ?? ''),
        'iccid' => (string) ($fulfillment['iccid'] ?? ''),
        'qr_code_url' => (string) ($fulfillment['qr_code_url'] ?? ''),
        'smdp_address' => (string) ($fulfillment['smdp_address'] ?? ''),
        'gloesim' => is_array($fulfillment['gloesim_item'] ?? null) ? $fulfillment['gloesim_item'] : null,
    ], fn ($v) => $v !== null);

    $payment->status = 'fulfilled';
    $payment->fulfillment_payload = $payload;
    $payment->save();

    return response()->json(['ok' => true, 'fulfillment' => $payload]);
})->name('wallet.pay.esim');

Route::middleware(['auth:sanctum', 'verified', 'throttle:10,1'])->post('/api/wallet/pay/virtual-number', function (Request $request, TwilioService $twilio, WalletService $wallet) {
    $productId = (int) $request->input('product_id', 0);
    $phoneNumber = trim((string) $request->input('phone_number', ''));
    $numberType = trim((string) $request->input('number_type', ''));
    $numberType = in_array($numberType, ['Local', 'Mobile', 'TollFree'], true) ? $numberType : '';
    if ($productId <= 0 || $phoneNumber === '') {
        return response()->json(['message' => 'Missing checkout parameters.'], 422);
    }

    $product = VirtualNumberProduct::query()->where('active', true)->find($productId);
    if (! $product) {
        return response()->json(['message' => 'Product not found.'], 404);
    }

    $amountMinor = (int) $product->monthly_amount_minor;
    if ($twilio->isConfigured() && $numberType !== '') {
        $pricing = $twilio->phoneNumberPricing((string) $product->country_iso);
        if (($pricing['ok'] ?? false) === true && is_array($pricing['prices_minor'] ?? null)) {
            $pricesMinor = (array) $pricing['prices_minor'];
            $minor = isset($pricesMinor[$numberType]) && is_numeric($pricesMinor[$numberType]) ? (int) $pricesMinor[$numberType] : null;
            if (is_int($minor) && $minor > 0) {
                $amountMinor = $minor;
            }
        }
    }

    if ($amountMinor <= 0) {
        return response()->json(['message' => 'Invalid amount.'], 422);
    }

    $reference = 'wvn_'.(string) Str::uuid();
    $payment = Payment::create([
        'user_id' => Auth::id(),
        'provider' => 'wallet',
        'provider_reference' => $reference,
        'status' => 'paid',
        'asset_type' => 'virtual_number',
        'asset_id' => (string) $product->id,
        'bundle_id' => (string) $product->id,
        'package_type' => 'MONTHLY',
        'currency' => 'USD',
        'amount_minor' => $amountMinor,
        'provider_payload' => [
            'virtual_number' => [
                'product_id' => $product->id,
                'phone_number' => $phoneNumber,
                'number_type' => $numberType !== '' ? $numberType : null,
                'country_iso' => (string) $product->country_iso,
            ],
        ],
    ]);

    try {
        $wallet->debit((int) Auth::id(), $amountMinor, 'buy_virtual_number', [
            'product_id' => $product->id,
            'phone_number' => $phoneNumber,
            'number_type' => $numberType !== '' ? $numberType : null,
            'reference' => $reference,
        ], (int) $payment->id, (int) env('WALLET_MIN_BALANCE_MINOR', 0));
    } catch (Throwable $e) {
        $payment->status = 'failed_insufficient_wallet';
        $payment->fulfillment_payload = ['ok' => false, 'error' => $e->getMessage()];
        $payment->save();

        return response()->json(['message' => $e->getMessage()], 422);
    }

    $smsUrl = (string) (config('services.twilio.sms_webhook_url') ?: url('/api/twilio/sms'));
    $voiceUrl = (string) (config('services.twilio.voice_webhook_url') ?: url('/api/twilio/voice'));
    $purchase = $twilio->purchaseNumber($phoneNumber, $smsUrl, $voiceUrl);
    if (($purchase['ok'] ?? false) !== true) {
        try {
            $wallet->credit((int) Auth::id(), $amountMinor, 'refund', [
                'reason' => (string) ($purchase['error'] ?? 'Twilio provisioning failed'),
                'reference' => $reference,
            ], (int) $payment->id);
        } catch (Throwable) {
        }
        $payment->status = 'paid_failed_provision_refunded';
        $payment->fulfillment_payload = ['ok' => false, 'error' => (string) ($purchase['error'] ?? 'Twilio provisioning failed')];
        $payment->save();

        return response()->json(['message' => 'Number provisioning failed. Wallet was refunded.'], 502);
    }

    $now = now();
    $sub = VirtualNumberSubscription::create([
        'user_id' => Auth::id(),
        'virtual_number_product_id' => $product->id,
        'status' => 'active',
        'phone_number' => (string) ($purchase['phone_number'] ?? $phoneNumber),
        'country_iso' => (string) $product->country_iso,
        'cap_sms' => true,
        'cap_voice' => true,
        'provider' => 'twilio',
        'billing_method' => 'wallet',
        'twilio_phone_number_sid' => (string) ($purchase['sid'] ?? ''),
        'currency' => 'USD',
        'monthly_amount_minor' => $amountMinor,
        'current_period_start' => $now,
        'current_period_end' => $now->copy()->addMonth(),
        'renewal_failed_count' => 0,
    ]);

    $payload = [
        'ok' => true,
        'subscription_id' => $sub->id,
        'phone_number' => $sub->phone_number,
        'status' => $sub->status,
        'current_period_end' => optional($sub->current_period_end)->toIso8601String(),
    ];

    $payment->status = 'fulfilled';
    $payment->fulfillment_payload = $payload;
    $payment->save();

    $toEmail = trim((string) (Auth::user()?->email ?? ''));
    if ($toEmail !== '' && filter_var($toEmail, FILTER_VALIDATE_EMAIL) !== false) {
        $subject = 'Your Spacechip virtual number is active';
        $phoneText = htmlspecialchars((string) $sub->phone_number, ENT_QUOTES, 'UTF-8');
        $planText = htmlspecialchars((string) ($product->label ?? 'Virtual Number'), ENT_QUOTES, 'UTF-8');
        $renewText = optional($sub->current_period_end)->toDayDateTimeString() ?: '';
        $renewText = htmlspecialchars((string) $renewText, ENT_QUOTES, 'UTF-8');
        $html = '<div style="font-family: Instrument Sans, Arial, sans-serif; background:#f7f7f8; padding:24px;">'
            .'<div style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:18px; border:1px solid rgba(20,84,84,.12); overflow:hidden;">'
            .'<div style="padding:18px 20px; background: linear-gradient(90deg,#f27457,#145454); color:#fff;">'
            .'<div style="font-weight:900; letter-spacing:.02em;">Spacechip</div>'
            .'<div style="opacity:.9; margin-top:6px;">Virtual number activated</div>'
            .'</div>'
            .'<div style="padding:18px 20px; color:#0b1a1a;">'
            .'<div style="font-weight:900; font-size:16px; margin-bottom:10px;">Your number is ready</div>'
            .'<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:800;">Number:</span> '.$phoneText.'</div>'
            .'<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:800;">Plan:</span> '.$planText.'</div>'
            .($renewText !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:800;">Next renewal:</span> '.$renewText.'</div>' : '')
            .'<div style="margin-top:14px; color:rgba(15,31,31,.62); font-size:13px; line-height:1.5;">'
            .'You can receive SMS/MMS and voicemail in your dashboard: Virtual Numbers → My Numbers → Inbox.'
            .'</div>'
            .'</div>'
            .'</div>'
            .'</div>';

        try {
            Mail::send([], [], function ($message) use ($toEmail, $subject, $html) {
                $message->to($toEmail)->subject($subject)->html($html);
            });
        } catch (Throwable) {
        }
    }

    return response()->json(['ok' => true, 'subscription' => $payload]);
})->name('wallet.pay.virtual_number');

Route::middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->get('/api/virtual-numbers/{subscription}/messages', function (VirtualNumberSubscription $subscription, Request $request) {
    if ((int) $subscription->user_id !== (int) Auth::id()) {
        abort(403);
    }

    $perPage = (int) $request->query('per_page', 50);
    $perPage = max(1, min(200, $perPage));

    $items = VirtualNumberMessage::query()
        ->where('virtual_number_subscription_id', $subscription->id)
        ->orderByDesc('id')
        ->limit($perPage)
        ->get()
        ->map(function (VirtualNumberMessage $m) {
            $media = is_array($m->media) ? $m->media : [];

            return [
                'id' => (int) $m->id,
                'direction' => (string) $m->direction,
                'message_type' => (string) ($m->message_type ?? 'sms'),
                'from' => (string) ($m->from ?? ''),
                'to' => (string) ($m->to ?? ''),
                'body' => (string) ($m->body ?? ''),
                'media_count' => is_array($media) ? count($media) : 0,
                'has_recording' => (string) ($m->message_type ?? '') === 'voicemail' && trim((string) ($m->recording_url ?? '')) !== '',
                'created_at' => optional($m->created_at)->toIso8601String(),
            ];
        })
        ->values()
        ->toArray();

    return response()->json(['ok' => true, 'items' => $items]);
})->name('virtual.messages');

Route::middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->get('/api/virtual-numbers/{subscription}/settings', function (VirtualNumberSubscription $subscription) {
    if ((int) $subscription->user_id !== (int) Auth::id()) {
        abort(403);
    }

    return response()->json([
        'ok' => true,
        'settings' => [
            'forward_to_email' => (string) ($subscription->forward_to_email ?? ''),
            'forward_to_phone' => (string) ($subscription->forward_to_phone ?? ''),
        ],
    ]);
})->name('virtual.settings.get');

Route::middleware(['auth:sanctum', 'verified', 'throttle:10,1'])->post('/api/virtual-numbers/{subscription}/settings', function (VirtualNumberSubscription $subscription, Request $request) {
    if ((int) $subscription->user_id !== (int) Auth::id()) {
        abort(403);
    }

    $email = trim((string) $request->input('forward_to_email', ''));
    $phone = trim((string) $request->input('forward_to_phone', ''));

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return response()->json(['message' => 'Invalid forwarding email.'], 422);
    }

    if ($phone !== '' && ! preg_match('/^\+[1-9]\d{6,14}$/', $phone)) {
        return response()->json(['message' => 'Invalid forwarding phone. Use E.164 format like +14155552671.'], 422);
    }

    $subscription->forward_to_email = $email !== '' ? $email : null;
    $subscription->forward_to_phone = $phone !== '' ? $phone : null;
    $subscription->save();

    return response()->json([
        'ok' => true,
        'settings' => [
            'forward_to_email' => (string) ($subscription->forward_to_email ?? ''),
            'forward_to_phone' => (string) ($subscription->forward_to_phone ?? ''),
        ],
    ]);
})->name('virtual.settings.set');

Route::middleware(['auth:sanctum', 'verified', 'throttle:10,1'])->post('/api/virtual-numbers/{subscription}/messages/send', function (VirtualNumberSubscription $subscription, Request $request, TwilioService $twilio) {
    if ((int) $subscription->user_id !== (int) Auth::id()) {
        abort(403);
    }

    $to = trim((string) $request->input('to', ''));
    $body = trim((string) $request->input('body', ''));
    $mediaUrls = $request->input('media_urls', []);
    if (! is_array($mediaUrls)) {
        $mediaUrls = [];
    }
    $mediaUrls = array_values(array_filter(array_map(fn ($u) => trim((string) $u), $mediaUrls)));

    if ($to === '' || ($body === '' && empty($mediaUrls))) {
        return response()->json(['message' => 'Missing to/body.'], 422);
    }

    if (! $twilio->isConfigured()) {
        return response()->json(['message' => 'Twilio is not configured.'], 500);
    }

    $from = (string) ($subscription->phone_number ?? '');
    $res = $twilio->sendMessage($from, $to, $body, $mediaUrls);
    if (($res['ok'] ?? false) !== true) {
        return response()->json(['message' => (string) ($res['error'] ?? 'Failed to send message.')], 502);
    }

    $sid = (string) ($res['sid'] ?? '');
    $messageType = empty($mediaUrls) ? 'sms' : 'mms';

    $msg = VirtualNumberMessage::create([
        'virtual_number_subscription_id' => $subscription->id,
        'direction' => 'outbound',
        'message_type' => $messageType,
        'from' => $from,
        'to' => $to,
        'body' => $body,
        'twilio_message_sid' => $sid !== '' ? $sid : null,
        'media' => empty($mediaUrls) ? null : array_map(fn ($u) => ['url' => $u], $mediaUrls),
        'raw' => $res['raw'] ?? null,
    ]);

    return response()->json([
        'ok' => true,
        'message' => [
            'id' => (int) $msg->id,
            'direction' => (string) $msg->direction,
            'message_type' => (string) ($msg->message_type ?? 'sms'),
            'from' => (string) ($msg->from ?? ''),
            'to' => (string) ($msg->to ?? ''),
            'body' => (string) ($msg->body ?? ''),
            'created_at' => optional($msg->created_at)->toIso8601String(),
        ],
    ]);
})->name('virtual.messages.send');

Route::middleware(['auth:sanctum', 'verified', 'throttle:60,1'])->get('/api/virtual-numbers/messages/{message}/media/{index}', function (VirtualNumberMessage $message, int $index, TwilioService $twilio) {
    $sub = $message->subscription;
    if (! $sub || (int) $sub->user_id !== (int) Auth::id()) {
        abort(403);
    }

    $media = is_array($message->media) ? $message->media : [];
    if (! isset($media[$index]) || ! is_array($media[$index])) {
        abort(404);
    }
    $url = trim((string) ($media[$index]['url'] ?? ''));
    if ($url === '') {
        abort(404);
    }

    try {
        $res = $twilio->fetchProtectedUrl($url);
    } catch (Throwable) {
        return response('Failed to fetch media.', 502);
    }

    if (! $res->successful()) {
        return response('Failed to fetch media.', 502);
    }

    $contentType = (string) ($res->header('Content-Type') ?? 'application/octet-stream');

    return response($res->body(), 200)->header('Content-Type', $contentType);
})->name('virtual.messages.media');

Route::middleware(['auth:sanctum', 'verified', 'throttle:60,1'])->get('/api/virtual-numbers/messages/{message}/recording', function (VirtualNumberMessage $message, TwilioService $twilio) {
    $sub = $message->subscription;
    if (! $sub || (int) $sub->user_id !== (int) Auth::id()) {
        abort(403);
    }
    $url = trim((string) ($message->recording_url ?? ''));
    if ($url === '') {
        abort(404);
    }

    $fetchUrl = str_ends_with($url, '.mp3') ? $url : ($url.'.mp3');
    try {
        $res = $twilio->fetchProtectedUrl($fetchUrl);
    } catch (Throwable) {
        return response('Failed to fetch recording.', 502);
    }

    if (! $res->successful()) {
        return response('Failed to fetch recording.', 502);
    }

    return response($res->body(), 200)->header('Content-Type', 'audio/mpeg');
})->name('virtual.messages.recording');

Route::post('/api/twilio/sms', function (Request $request, TwilioService $twilio) {
    $sig = (string) $request->header('X-Twilio-Signature', '');
    $fullUrl = $request->fullUrl();
    $params = $request->post();
    if (! $twilio->validateSignature($fullUrl, is_array($params) ? $params : [], $sig)) {
        abort(403);
    }

    $to = (string) $request->input('To', '');
    $from = (string) $request->input('From', '');
    $body = (string) $request->input('Body', '');
    $sid = (string) $request->input('MessageSid', '');
    $numMedia = (int) $request->input('NumMedia', 0);

    $media = [];
    if ($numMedia > 0) {
        for ($i = 0; $i < $numMedia; $i++) {
            $u = trim((string) $request->input('MediaUrl'.$i, ''));
            $ct = trim((string) $request->input('MediaContentType'.$i, ''));
            if ($u !== '') {
                $media[] = array_filter(['url' => $u, 'content_type' => $ct], fn ($v) => $v !== '');
            }
        }
    }

    $sub = VirtualNumberSubscription::query()->where('phone_number', $to)->first();
    if ($sub) {
        VirtualNumberMessage::create([
            'virtual_number_subscription_id' => $sub->id,
            'direction' => 'inbound',
            'message_type' => $numMedia > 0 ? 'mms' : 'sms',
            'from' => $from,
            'to' => $to,
            'body' => $body,
            'twilio_message_sid' => $sid !== '' ? $sid : null,
            'media' => empty($media) ? null : $media,
            'raw' => $request->all(),
        ]);

        $forwardEmail = trim((string) ($sub->forward_to_email ?? ''));
        if ($forwardEmail !== '' && filter_var($forwardEmail, FILTER_VALIDATE_EMAIL) !== false) {
            $subject = 'New message to your Spacechip number';
            $html = '<div style="font-family: Instrument Sans, Arial, sans-serif; background:#f7f7f8; padding:24px;">'
                .'<div style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:18px; border:1px solid rgba(20,84,84,.12); overflow:hidden;">'
                .'<div style="padding:18px 20px; background: linear-gradient(90deg,#f27457,#145454); color:#fff;">'
                .'<div style="font-weight:900; letter-spacing:.02em;">Spacechip</div>'
                .'<div style="opacity:.9; margin-top:6px;">Incoming '.($numMedia > 0 ? 'MMS' : 'SMS').'</div>'
                .'</div>'
                .'<div style="padding:18px 20px; color:#0b1a1a;">'
                .'<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:800;">To:</span> '.htmlspecialchars($to, ENT_QUOTES, 'UTF-8').'</div>'
                .'<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:800;">From:</span> '.htmlspecialchars($from, ENT_QUOTES, 'UTF-8').'</div>'
                .($body !== '' ? '<div style="margin-top:12px; padding:12px 14px; border-radius:14px; background:rgba(20,84,84,.06); border:1px solid rgba(20,84,84,.10); white-space:pre-wrap;">'.htmlspecialchars($body, ENT_QUOTES, 'UTF-8').'</div>' : '')
                .($numMedia > 0 ? '<div style="margin-top:10px; color:rgba(15,31,31,.62); font-size:13px; font-weight:700;">MMS media: '.$numMedia.' attachment(s). View in your Spacechip dashboard inbox.</div>' : '')
                .'<div style="margin-top:14px; color:rgba(15,31,31,.62); font-size:13px; line-height:1.5;">'
                .'Open Dashboard → Virtual Numbers → My Numbers → Inbox to reply.'
                .'</div>'
                .'</div>'
                .'</div>'
                .'</div>';

            try {
                Mail::send([], [], function ($message) use ($forwardEmail, $subject, $html) {
                    $message->to($forwardEmail)->subject($subject)->html($html);
                });
            } catch (Throwable) {
            }
        }

        $forwardPhone = trim((string) ($sub->forward_to_phone ?? ''));
        if ($forwardPhone !== '' && preg_match('/^\+[1-9]\d{6,14}$/', $forwardPhone)) {
            $prefix = 'Spacechip SMS from '.$from.': ';
            $text = $prefix.$body;
            if ($body === '') {
                $text = 'Spacechip '.($numMedia > 0 ? 'MMS' : 'SMS').' received from '.$from.'. View in dashboard inbox.';
            }
            if (mb_strlen($text) > 1500) {
                $text = mb_substr($text, 0, 1500);
            }
            try {
                $twilio->sendMessage($to, $forwardPhone, $text, []);
            } catch (Throwable) {
            }
        }
    }

    return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)->header('Content-Type', 'text/xml');
})->name('twilio.sms');

Route::post('/api/twilio/voice', function (Request $request, TwilioService $twilio) {
    $sig = (string) $request->header('X-Twilio-Signature', '');
    $fullUrl = $request->fullUrl();
    $params = $request->post();
    if (! $twilio->validateSignature($fullUrl, is_array($params) ? $params : [], $sig)) {
        abort(403);
    }

    $to = (string) $request->input('To', '');
    $sub = VirtualNumberSubscription::query()->where('phone_number', $to)->first();
    if (! $sub) {
        return response('<?xml version="1.0" encoding="UTF-8"?><Response><Reject/></Response>', 200)->header('Content-Type', 'text/xml');
    }

    $recordingCallback = url('/api/twilio/voice/recording');
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        .'<Response>'
        .'<Say>Please leave a message after the tone.</Say>'
        .'<Record maxLength="120" playBeep="true" recordingStatusCallback="'.e($recordingCallback).'" recordingStatusCallbackMethod="POST" />'
        .'<Say>Goodbye.</Say>'
        .'</Response>';

    return response($xml, 200)->header('Content-Type', 'text/xml');
})->name('twilio.voice');

Route::post('/api/twilio/voice/recording', function (Request $request, TwilioService $twilio) {
    $sig = (string) $request->header('X-Twilio-Signature', '');
    $fullUrl = $request->fullUrl();
    $params = $request->post();
    if (! $twilio->validateSignature($fullUrl, is_array($params) ? $params : [], $sig)) {
        abort(403);
    }

    $to = (string) $request->input('To', '');
    $from = (string) $request->input('From', '');
    $callSid = (string) $request->input('CallSid', '');
    $recSid = (string) $request->input('RecordingSid', '');
    $recUrl = (string) $request->input('RecordingUrl', '');
    $duration = (int) $request->input('RecordingDuration', 0);

    $sub = VirtualNumberSubscription::query()->where('phone_number', $to)->first();
    if ($sub && $recSid !== '') {
        VirtualNumberMessage::updateOrCreate(
            ['twilio_recording_sid' => $recSid],
            [
                'virtual_number_subscription_id' => $sub->id,
                'direction' => 'inbound',
                'message_type' => 'voicemail',
                'from' => $from,
                'to' => $to,
                'twilio_call_sid' => $callSid !== '' ? $callSid : null,
                'twilio_recording_sid' => $recSid,
                'recording_url' => $recUrl !== '' ? $recUrl : null,
                'recording_duration' => $duration > 0 ? $duration : null,
                'raw' => $request->all(),
            ]
        );

        $forwardEmail = trim((string) ($sub->forward_to_email ?? ''));
        if ($forwardEmail !== '' && filter_var($forwardEmail, FILTER_VALIDATE_EMAIL) !== false) {
            $subject = 'New voicemail on your Spacechip number';
            $durText = $duration > 0 ? (string) $duration.'s' : '';
            $html = '<div style="font-family: Instrument Sans, Arial, sans-serif; background:#f7f7f8; padding:24px;">'
                .'<div style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:18px; border:1px solid rgba(20,84,84,.12); overflow:hidden;">'
                .'<div style="padding:18px 20px; background: linear-gradient(90deg,#f27457,#145454); color:#fff;">'
                .'<div style="font-weight:900; letter-spacing:.02em;">Spacechip</div>'
                .'<div style="opacity:.9; margin-top:6px;">Voicemail received</div>'
                .'</div>'
                .'<div style="padding:18px 20px; color:#0b1a1a;">'
                .'<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:800;">To:</span> '.htmlspecialchars($to, ENT_QUOTES, 'UTF-8').'</div>'
                .'<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:800;">From:</span> '.htmlspecialchars($from, ENT_QUOTES, 'UTF-8').'</div>'
                .($durText !== '' ? '<div style="margin-bottom:8px;"><span style="color:rgba(15,31,31,.62); font-weight:800;">Duration:</span> '.htmlspecialchars($durText, ENT_QUOTES, 'UTF-8').'</div>' : '')
                .'<div style="margin-top:14px; color:rgba(15,31,31,.62); font-size:13px; line-height:1.5;">'
                .'Open Dashboard → Virtual Numbers → My Numbers → Inbox to play the voicemail.'
                .'</div>'
                .'</div>'
                .'</div>'
                .'</div>';

            try {
                Mail::send([], [], function ($message) use ($forwardEmail, $subject, $html) {
                    $message->to($forwardEmail)->subject($subject)->html($html);
                });
            } catch (Throwable) {
            }
        }

        $forwardPhone = trim((string) ($sub->forward_to_phone ?? ''));
        if ($forwardPhone !== '' && preg_match('/^\+[1-9]\d{6,14}$/', $forwardPhone)) {
            $text = 'Spacechip voicemail from '.$from.' to '.$to.($duration > 0 ? ' ('.$duration.'s)' : '').'. Check dashboard inbox.';
            try {
                $twilio->sendMessage($to, $forwardPhone, $text, []);
            } catch (Throwable) {
            }
        }
    }

    return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)->header('Content-Type', 'text/xml');
})->name('twilio.voice.recording');

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

        $virtualTotal = VirtualNumberSubscription::count();
        $virtualActive = VirtualNumberSubscription::where('status', 'active')->count();
        $virtualPastDue = VirtualNumberSubscription::where('status', 'past_due')->count();
        $recentVirtualSubs = VirtualNumberSubscription::with(['user', 'product'])
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
            'twilio_configured' => trim((string) config('services.twilio.account_sid')) !== '' && trim((string) config('services.twilio.auth_token')) !== '',
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
                'virtual_total' => $virtualTotal,
                'virtual_active' => $virtualActive,
                'virtual_past_due' => $virtualPastDue,
            ],
            'health' => $health,
            'recentPayments' => $recentPayments,
            'recentUsers' => $recentUsers,
            'recentVirtualSubs' => $recentVirtualSubs,
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
            $items = VirtualNumberProduct::query()
                ->where('active', true)
                ->orderBy('country_iso')
                ->orderBy('label')
                ->get()
                ->map(function (VirtualNumberProduct $p) {
                    $price = strtoupper((string) ($p->currency ?? 'USD')).' '.number_format(((int) $p->monthly_amount_minor) / 100, 2);

                    return [
                        'id' => (int) $p->id,
                        'product_id' => (int) $p->id,
                        'name' => strtoupper((string) $p->country_iso).' · '.$p->label,
                        'description' => 'Monthly virtual number subscription (SMS & calls).',
                        'price_formatted' => $price,
                        'flag' => '☎️',
                        'flag_url' => '',
                        'url' => route('dashboard.virtual.country', ['country' => strtoupper((string) $p->country_iso)]),
                    ];
                })
                ->toArray();
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
        'virtualNumbers' => VirtualNumberProduct::query()
            ->where('active', true)
            ->orderBy('country_iso')
            ->orderBy('label')
            ->get()
            ->map(function (VirtualNumberProduct $p) {
                $price = strtoupper((string) ($p->currency ?? 'USD')).' '.number_format(((int) $p->monthly_amount_minor) / 100, 2);

                return [
                    'id' => (int) $p->id,
                    'product_id' => (int) $p->id,
                    'name' => strtoupper((string) $p->country_iso).' · '.$p->label,
                    'description' => 'Monthly virtual number subscription (SMS & calls).',
                    'price_formatted' => $price,
                    'flag' => '☎️',
                    'flag_url' => '',
                    'url' => route('dashboard.virtual.country', ['country' => strtoupper((string) $p->country_iso)]),
                ];
            })
            ->toArray(),
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
})->name('asset.details');

Route::middleware(['auth'])->get('/checkout', function (GloEsimService $gloEsim) {
    $type = request('type');
    $id = request('id');
    $bundleId = request('bundle');
    $packageType = request('package_type', 'DATA-ONLY');
    $topupEsimId = trim((string) request('topup_esim_id', ''));

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

    if ($topupEsimId !== '') {
        $userPayments = Payment::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('fulfillment_payload')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $allowed = false;
        foreach ($userPayments as $p) {
            $fp = is_array($p->fulfillment_payload) ? $p->fulfillment_payload : [];
            $esimId = trim((string) ($fp['esim_id'] ?? ''));
            if ($esimId !== $topupEsimId) {
                continue;
            }
            $glo = is_array($fp['gloesim'] ?? null) ? $fp['gloesim'] : [];
            $raw = $fp['can_renew'] ?? data_get($glo, 'can_renew');
            $canRenew = false;
            if (is_bool($raw)) {
                $canRenew = $raw;
            } elseif (is_numeric($raw)) {
                $canRenew = ((int) $raw) === 1;
            } elseif (is_string($raw) && trim($raw) !== '') {
                $canRenew = in_array(mb_strtolower(trim($raw)), ['1', 'true', 'yes'], true);
            }
            if ($canRenew) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed) {
            abort(403);
        }
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
        'usdToNgnRate' => (float) $usdToNgnRate,
        'usdPrice' => (float) $usdPrice,
        'paystackAmountNgnRaw' => (float) $paystackAmountNgnRaw,
        'paystackAmountNgnAdjusted' => (float) $paystackAmountNgnAdjusted,
        'usdToNgnRateSource' => $rateSource,
    ]);
})->name('checkout');

Route::middleware(['auth:sanctum', 'throttle:10,1'])->post('/api/paystack/initialize', function (GloEsimService $gloEsim) {
    $type = request('type');
    $id = request('id');
    $bundleId = request('bundle');
    $packageType = request('package_type', 'DATA-ONLY');
    $topupEsimId = trim((string) request('topup_esim_id', ''));
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

    if ($topupEsimId !== '') {
        $userPayments = Payment::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('fulfillment_payload')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $allowed = false;
        foreach ($userPayments as $p) {
            $fp = is_array($p->fulfillment_payload) ? $p->fulfillment_payload : [];
            $esimId = trim((string) ($fp['esim_id'] ?? ''));
            if ($esimId !== $topupEsimId) {
                continue;
            }
            $glo = is_array($fp['gloesim'] ?? null) ? $fp['gloesim'] : [];
            $raw = $fp['can_renew'] ?? data_get($glo, 'can_renew');
            $canRenew = false;
            if (is_bool($raw)) {
                $canRenew = $raw;
            } elseif (is_numeric($raw)) {
                $canRenew = ((int) $raw) === 1;
            } elseif (is_string($raw) && trim($raw) !== '') {
                $canRenew = in_array(mb_strtolower(trim($raw)), ['1', 'true', 'yes'], true);
            }
            if ($canRenew) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed) {
            return response()->json(['message' => 'This eSIM cannot be renewed.'], 422);
        }
    }

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
                'topup' => $topupEsimId !== '' ? ['esim_id' => $topupEsimId] : null,
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
                'topup_esim_id' => $topupEsimId !== '' ? $topupEsimId : null,
            ],
        ]);

    if (! $response->successful()) {
        return response()->json(['message' => 'Failed to initialize Paystack transaction.'], 502);
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

Route::middleware(['auth:sanctum', 'throttle:20,1'])->post('/api/paystack/verify', function (GloEsimService $gloEsim, QrCodeService $qr) {
    $reference = (string) request('reference', '');
    if (trim($reference) === '') {
        return response()->json(['message' => 'Missing reference.'], 422);
    }

    $payment = Payment::where('provider', 'paystack')->where('provider_reference', $reference)->first();
    if (! $payment || (int) $payment->user_id !== (int) Auth::id()) {
        return response()->json(['message' => 'Payment not found.'], 404);
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
    } catch (Throwable $e) {
        return response()->json(['message' => 'Failed to verify Paystack transaction.'], 502);
    }

    if (! $response->successful()) {
        return response()->json(['message' => 'Failed to verify Paystack transaction.'], 502);
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

    $customerEmail = trim(mb_strtolower((string) data_get($json, 'data.customer.email', '')));
    $userEmail = trim(mb_strtolower((string) (Auth::user()?->email ?? '')));
    if ($customerEmail === '' || $userEmail === '' || $customerEmail !== $userEmail) {
        return response()->json(['message' => 'Payment not found.'], 404);
    }

    $txCurrency = (string) data_get($json, 'data.currency', '');
    $txAmount = (int) data_get($json, 'data.amount', 0);
    if ($txCurrency === '' || $txAmount <= 0 || strtoupper($txCurrency) !== strtoupper((string) $payment->currency) || $txAmount !== (int) $payment->amount_minor) {
        return response()->json(['message' => 'Payment not found.'], 404);
    }

    $meta = data_get($json, 'data.metadata', []);
    $meta = is_array($meta) ? $meta : [];
    $metaType = (string) (data_get($meta, 'type') ?? '');
    $metaId = (string) (data_get($meta, 'id') ?? '');
    $metaBundle = (string) (data_get($meta, 'bundle') ?? '');
    $metaPackageType = (string) (data_get($meta, 'package_type') ?? '');

    if ($metaType !== '' && $metaType !== (string) $payment->asset_type) {
        return response()->json(['message' => 'Payment not found.'], 404);
    }
    if ($metaId !== '' && $metaId !== (string) $payment->asset_id) {
        return response()->json(['message' => 'Payment not found.'], 404);
    }
    if ($metaBundle !== '' && $metaBundle !== (string) $payment->bundle_id) {
        return response()->json(['message' => 'Payment not found.'], 404);
    }
    if ($metaPackageType !== '' && $metaPackageType !== (string) $payment->package_type) {
        return response()->json(['message' => 'Payment not found.'], 404);
    }

    $type = (string) $payment->asset_type;
    $id = (string) $payment->asset_id;
    $bundleId = (string) $payment->bundle_id;
    $packageType = (string) $payment->package_type;

    $user = Auth::user();
    $email = (string) ($user?->email ?? '');
    if (trim($email) === '') {
        return response()->json(['message' => 'Missing user email.'], 422);
    }

    $asset = $gloEsim->getAssetDetails($type, $id, $packageType);
    $bundle = $asset ? collect($asset['bundles'] ?? [])->first(fn ($b) => (string) ($b['id'] ?? '') === $bundleId) : null;

    $topupEsimId = trim((string) (data_get($payment->provider_payload, 'topup.esim_id') ?? data_get($meta, 'topup_esim_id') ?? ''));
    if ($topupEsimId !== '') {
        $userPayments = Payment::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('fulfillment_payload')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();
        $ownsTarget = false;
        foreach ($userPayments as $p) {
            $fp = is_array($p->fulfillment_payload) ? $p->fulfillment_payload : [];
            if (trim((string) ($fp['esim_id'] ?? '')) === $topupEsimId) {
                $ownsTarget = true;
                break;
            }
        }
        if (! $ownsTarget) {
            return response()->json(['message' => 'Payment not found.'], 404);
        }
    }

    $fulfillment = $topupEsimId !== ''
        ? $gloEsim->topupEsim($topupEsimId, $bundleId, $reference, $email, $packageType, ['name' => (string) ($user?->name ?? '')])
        : $gloEsim->fulfillEsim($bundleId, $reference, $email, $packageType, ['name' => (string) ($user?->name ?? '')]);
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
                    'topup_esim_id' => $topupEsimId !== '' ? $topupEsimId : null,
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
                        'topup_esim_id' => $topupEsimId !== '' ? $topupEsimId : null,
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
        Mail::send([], [], function ($message) use ($email, $subject, $html, $qrSvg) {
            $message->to($email)->subject($subject)->html($html);
            if (is_string($qrSvg) && $qrSvg !== '') {
                $message->attachData($qrSvg, 'esim-qr.svg', ['mime' => 'image/svg+xml']);
            }
        });
    } catch (Throwable) {
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
            'topup_esim_id' => $topupEsimId !== '' ? $topupEsimId : null,
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

Route::middleware(['auth'])->get('/api/paystack/status', function () {
    $reference = (string) request('reference', '');
    if (trim($reference) === '') {
        return response()->json(['message' => 'Missing reference.'], 422);
    }

    $payment = Payment::where('provider', 'paystack')->where('provider_reference', $reference)->first();
    if (! $payment || (int) $payment->user_id !== (int) Auth::id()) {
        return response()->json(['message' => 'Payment not found.'], 404);
    }

    $fulfilledCacheKey = 'paystack.fulfillment.v1.'.sha1($reference);
    $existing = Cache::get($fulfilledCacheKey);
    if (is_array($existing)) {
        return response()->json($existing);
    }

    return response()->json([
        'ok' => true,
        'status' => (string) ($payment->status ?? ''),
        'reference' => $reference,
        'data' => null,
        'fulfillment' => is_array($payment->fulfillment_payload) ? $payment->fulfillment_payload : null,
    ]);
})->name('paystack.status.read');

Route::middleware(['auth:sanctum', 'throttle:30,1'])->post('/api/paystack/status', function (GloEsimService $gloEsim, QrCodeService $qr) {
    $reference = (string) request('reference', '');
    if (trim($reference) === '') {
        return response()->json(['message' => 'Missing reference.'], 422);
    }

    $payment = Payment::where('provider', 'paystack')->where('provider_reference', $reference)->first();
    if (! $payment || (int) $payment->user_id !== (int) Auth::id()) {
        return response()->json(['message' => 'Payment not found.'], 404);
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
    $topupEsimId = trim((string) ($fulfillmentMeta['topup_esim_id'] ?? ''));
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
        : ($bundleId !== '' ? ($topupEsimId !== '' ? $gloEsim->topupEsim($topupEsimId, $bundleId, $reference, $email, $packageType, ['name' => (string) ($user?->name ?? '')]) : $gloEsim->fulfillEsim($bundleId, $reference, $email, $packageType, ['name' => (string) ($user?->name ?? '')])) : ['ok' => false, 'error' => 'Missing bundle id.']);

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
        Mail::send([], [], function ($message) use ($email, $subject, $html, $qrSvg) {
            $message->to($email)->subject($subject)->html($html);
            if (is_string($qrSvg) && $qrSvg !== '') {
                $message->attachData($qrSvg, 'esim-qr.svg', ['mime' => 'image/svg+xml']);
            }
        });
    } catch (Throwable) {
    }

    $existing['ok'] = true;
    $existing['fulfillment'] = [
        'ok' => true,
        'synced' => true,
        'type' => $type,
        'id' => $id,
        'bundle' => $bundleId,
        'package_type' => $packageType,
        'topup_esim_id' => $topupEsimId !== '' ? $topupEsimId : null,
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

Route::middleware(['auth', 'throttle:10,1'])->post('/api/cryptomus/invoice', function (Request $request, CryptomusService $cryptomus, GloEsimService $gloEsim) {
    $type = (string) $request->input('type', '');
    $id = (string) $request->input('id', '');
    $bundleId = (string) $request->input('bundle', '');
    $packageType = (string) $request->input('package_type', 'DATA-ONLY');
    $topupEsimId = trim((string) $request->input('topup_esim_id', ''));
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

    if ($topupEsimId !== '') {
        $userPayments = Payment::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('fulfillment_payload')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $allowed = false;
        foreach ($userPayments as $p) {
            $fp = is_array($p->fulfillment_payload) ? $p->fulfillment_payload : [];
            $esimId = trim((string) ($fp['esim_id'] ?? ''));
            if ($esimId !== $topupEsimId) {
                continue;
            }
            $glo = is_array($fp['gloesim'] ?? null) ? $fp['gloesim'] : [];
            $raw = $fp['can_renew'] ?? data_get($glo, 'can_renew');
            $canRenew = false;
            if (is_bool($raw)) {
                $canRenew = $raw;
            } elseif (is_numeric($raw)) {
                $canRenew = ((int) $raw) === 1;
            } elseif (is_string($raw) && trim($raw) !== '') {
                $canRenew = in_array(mb_strtolower(trim($raw)), ['1', 'true', 'yes'], true);
            }
            if ($canRenew) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed) {
            return response()->json(['message' => 'This eSIM cannot be renewed.'], 422);
        }
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
            'topup' => $topupEsimId !== '' ? ['esim_id' => $topupEsimId] : null,
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

Route::middleware(['auth:sanctum'])->get('/api/my-esims', function (Request $request, QrCodeService $qr, GloEsimService $gloEsim) {
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

    $items = [];
    $seenIccids = [];
    foreach ($rows as $p) {
        $fulfillment = is_array($p->fulfillment_payload) ? $p->fulfillment_payload : [];

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
        $canRenewRaw = $fulfillment['can_renew'] ?? data_get($glo, 'can_renew');
        $canRenew = null;
        if (is_bool($canRenewRaw)) {
            $canRenew = $canRenewRaw;
        } elseif (is_numeric($canRenewRaw)) {
            $canRenew = ((int) $canRenewRaw) === 1;
        } elseif (is_string($canRenewRaw) && $canRenewRaw !== '') {
            $canRenew = in_array(mb_strtolower(trim($canRenewRaw)), ['1', 'true', 'yes'], true);
        }
        $isFulfilled = (bool) ($fulfillment['ok'] ?? false) || (string) $p->status === 'fulfilled';
        if (trim($iccidValue) === '' || ! $isFulfilled) {
            continue;
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
                'can_renew' => $canRenew,
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

Route::middleware(['auth:sanctum', 'throttle:15,1'])->post('/api/my-esims/sync', function (Request $request, GloEsimService $gloEsim, QrCodeService $qr) {
    $userId = (int) Auth::id();

    $rows = Payment::query()
        ->where('user_id', $userId)
        ->whereIn('provider', ['paystack', 'cryptomus'])
        ->whereNotNull('fulfillment_payload')
        ->orderByDesc('created_at')
        ->limit(60)
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
            } catch (Throwable) {
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

    $updated = 0;
    foreach ($rows as $p) {
        $lockKey = 'myesims.sync.write.v1.'.sha1((string) $p->id);
        if (! Cache::add($lockKey, '1', now()->addSeconds(5))) {
            continue;
        }

        $fulfillment = is_array($p->fulfillment_payload) ? $p->fulfillment_payload : [];
        $glo = is_array($fulfillment['gloesim'] ?? null) ? $fulfillment['gloesim'] : [];
        $iccid = trim((string) ($fulfillment['iccid'] ?? (data_get($glo, 'iccid') ?? '')));
        $esimId = trim((string) ($fulfillment['esim_id'] ?? ''));

        if ($iccid !== '') {
            $dealerRow = $getDealerRowByIccid($iccid);
            if (is_array($dealerRow)) {
                $dealerEsimId = trim((string) (data_get($dealerRow, 'id') ?? data_get($dealerRow, 'esim_id') ?? ''));
                if ($dealerEsimId !== '') {
                    $esimId = $dealerEsimId;
                }
                $fulfillment = array_merge($fulfillment, [
                    'gloesim' => $dealerRow,
                    'esim_id' => $esimId,
                    'iccid' => (string) (data_get($dealerRow, 'iccid') ?? $iccid),
                    'number' => data_get($dealerRow, 'number'),
                    'can_renew' => data_get($dealerRow, 'can_renew'),
                    'esim_status' => (string) (data_get($dealerRow, 'status') ?? ''),
                    'smdp_address' => (string) (data_get($dealerRow, 'smdp_address') ?? ''),
                    'lpa' => (string) (data_get($dealerRow, 'qr_code_text') ?? ''),
                    'direct_installation_link_ios' => (string) (data_get($dealerRow, 'universal_link') ?? ''),
                    'direct_installation_link_android' => (string) (data_get($dealerRow, 'android_universal_link') ?? ''),
                    'attempted_at' => now()->toIso8601String(),
                ]);
            }
        }

        $glo = is_array($fulfillment['gloesim'] ?? null) ? $fulfillment['gloesim'] : [];
        $iccid = trim((string) ($fulfillment['iccid'] ?? (data_get($glo, 'iccid') ?? $iccid)));
        $lpaValue = trim((string) ($fulfillment['lpa'] ?? (data_get($glo, 'qr_code_text') ?? '')));
        $activationValue = trim((string) ($fulfillment['activation_code'] ?? ''));
        $smdpValue = trim((string) ($fulfillment['smdp_address'] ?? (data_get($glo, 'smdp_address') ?? '')));
        $qrPayload = $qr->esimQrPayload($lpaValue !== '' ? $lpaValue : $activationValue, $smdpValue);
        $computedSynced = $iccid !== '' && trim($qrPayload) !== '';

        if (! $computedSynced && $esimId !== '') {
            $details = $gloEsim->getEsimDetails($esimId);
            if (is_array($details) && (($details['ok'] ?? false) === true)) {
                $gloItem = is_array($details['gloesim_item'] ?? null) ? $details['gloesim_item'] : null;
                $iccid = trim((string) ($details['iccid'] ?? $iccid));
                $lpaValue = trim((string) ($details['lpa'] ?? $lpaValue));
                $activationValue = trim((string) ($details['activation_code'] ?? $activationValue));
                $smdpValue = trim((string) ($details['smdp_address'] ?? $smdpValue));
                $qrPayload = $qr->esimQrPayload($lpaValue !== '' ? $lpaValue : $activationValue, $smdpValue);
                $computedSynced = $iccid !== '' && trim($qrPayload) !== '';

                $fulfillment = array_filter(array_merge($fulfillment, [
                    'gloesim' => $gloItem ?? $fulfillment['gloesim'] ?? null,
                    'attempted_at' => now()->toIso8601String(),
                    'esim_id' => (string) ($details['esim_id'] ?? $esimId),
                    'iccid' => (string) ($details['iccid'] ?? $iccid),
                    'number' => $details['number'] ?? null,
                    'can_renew' => $details['can_renew'] ?? data_get($gloItem, 'can_renew'),
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
            }
        }

        $fulfillment['ok'] = $computedSynced;
        $fulfillment['pending'] = ! $computedSynced;
        $fulfillment['synced'] = $computedSynced;
        $fulfillment['iccid'] = $iccid;
        $fulfillment['smdp_address'] = $smdpValue;
        $fulfillment['lpa'] = $lpaValue;

        $before = json_encode($p->fulfillment_payload);
        $after = json_encode($fulfillment);
        if ($before !== $after) {
            $p->fulfillment_payload = $fulfillment;
            $p->save();
            $updated++;
        }
    }

    return response()->json(['ok' => true, 'updated' => $updated]);
})->name('myesims.sync');

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

    $topupEsimId = trim((string) data_get($payment->provider_payload, 'topup.esim_id', ''));
    if ($topupEsimId !== '') {
        $userPayments = Payment::query()
            ->where('user_id', $payment->user_id)
            ->whereNotNull('fulfillment_payload')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();
        $ownsTarget = false;
        foreach ($userPayments as $p) {
            $fp = is_array($p->fulfillment_payload) ? $p->fulfillment_payload : [];
            if (trim((string) ($fp['esim_id'] ?? '')) === $topupEsimId) {
                $ownsTarget = true;
                break;
            }
        }
        if (! $ownsTarget) {
            return response()->json(['ok' => true]);
        }
    }

    $fulfillment = $topupEsimId !== ''
        ? $gloEsim->topupEsim($topupEsimId, (string) $payment->bundle_id, 'cm_'.$orderId, $email, (string) $payment->package_type, ['name' => (string) ($user?->name ?? '')])
        : $gloEsim->fulfillEsim((string) $payment->bundle_id, 'cm_'.$orderId, $email, (string) $payment->package_type, ['name' => (string) ($user?->name ?? '')]);
    if (($fulfillment['ok'] ?? false) !== true) {
        $payment->fulfillment_payload = array_filter([
            'ok' => false,
            'pending' => (bool) ($fulfillment['pending'] ?? false),
            'attempted_at' => now()->toIso8601String(),
            'error' => (string) ($fulfillment['error'] ?? ''),
            'topup_esim_id' => $topupEsimId !== '' ? $topupEsimId : null,
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
    $topupEsimId = trim((string) data_get($payment->provider_payload, 'topup.esim_id', ''));
    $payment->fulfillment_payload = array_filter([
        'ok' => $synced,
        'pending' => ! $synced,
        'synced' => $synced,
        'attempted_at' => now()->toIso8601String(),
        'order_id' => (string) ($fulfillment['order_id'] ?? ''),
        'topup_esim_id' => $topupEsimId !== '' ? $topupEsimId : null,
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
        Mail::send([], [], function ($message) use ($email, $subject, $html, $qrSvg) {
            $message->to($email)->subject($subject)->html($html);
            if (is_string($qrSvg) && $qrSvg !== '') {
                $message->attachData($qrSvg, 'esim-qr.svg', ['mime' => 'image/svg+xml']);
            }
        });
    } catch (Throwable) {
    }

    return response()->json(['ok' => true]);
});

Route::middleware(['auth'])->get('/crypto/return', function () {
    return view('cryptomus-return', ['orderId' => (string) request('order_id', '')]);
})->name('cryptomus.return');

Route::middleware(['auth'])->get('/api/cryptomus/status', function (Request $request) {
    $orderId = (string) $request->query('order_id', '');
    if ($orderId === '') {
        return response()->json(['message' => 'Missing order_id.'], 422);
    }

    $payment = Payment::where('provider', 'cryptomus')->where('provider_reference', $orderId)->first();
    if (! $payment || (int) $payment->user_id !== (int) Auth::id()) {
        return response()->json(['message' => 'Payment not found.'], 404);
    }

    $fp = is_array($payment->fulfillment_payload) ? $payment->fulfillment_payload : [];

    return response()->json([
        'order_id' => $orderId,
        'status' => (string) ($payment->status ?? ''),
        'is_final' => (bool) (data_get($payment->provider_payload, 'status.is_final') ?? false),
        'fulfilled' => (bool) ($fp['ok'] ?? false),
        'synced' => (bool) ($fp['synced'] ?? false),
        'fulfillment_error' => (string) ($fp['error'] ?? ''),
    ]);
})->name('cryptomus.status.read');

Route::middleware(['auth', 'throttle:30,1'])->post('/api/cryptomus/status', function (Request $request, CryptomusService $cryptomus, GloEsimService $gloEsim, QrCodeService $qr) {
    $orderId = (string) $request->input('order_id', '');
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
            $topupEsimId = trim((string) data_get($payment->provider_payload, 'topup.esim_id', ''));

            $fulfillment = $isPending && $pendingEsimId !== ''
                ? $gloEsim->getEsimDetails($pendingEsimId)
                : ($topupEsimId !== ''
                    ? $gloEsim->topupEsim($topupEsimId, (string) $payment->bundle_id, 'cm_'.$orderId, $email, (string) $payment->package_type, ['name' => (string) (Auth::user()?->name ?? '')])
                    : $gloEsim->fulfillEsim((string) $payment->bundle_id, 'cm_'.$orderId, $email, (string) $payment->package_type, ['name' => (string) (Auth::user()?->name ?? '')]));

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
                $topupEsimId = trim((string) data_get($payment->provider_payload, 'topup.esim_id', ''));
                $payment->fulfillment_payload = array_filter([
                    'ok' => $synced,
                    'pending' => ! $synced,
                    'synced' => $synced,
                    'attempted_at' => now()->toIso8601String(),
                    'order_id' => (string) ($fulfillment['order_id'] ?? ($payment->fulfillment_payload['order_id'] ?? '')),
                    'topup_esim_id' => $topupEsimId !== '' ? $topupEsimId : null,
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
                    Mail::send([], [], function ($message) use ($email, $subject, $html, $qrSvg) {
                        $message->to($email)->subject($subject)->html($html);
                        if (is_string($qrSvg) && $qrSvg !== '') {
                            $message->attachData($qrSvg, 'esim-qr.svg', ['mime' => 'image/svg+xml']);
                        }
                    });
                } catch (Throwable) {
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
