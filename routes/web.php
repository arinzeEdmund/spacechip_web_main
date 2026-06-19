<?php

use App\Http\Controllers\ProfileController;
use App\Models\Payment;
use App\Models\SocialNumberOrder;
use App\Models\SocialNumberRental;
use App\Models\User;
use App\Models\VirtualNumberMessage;
use App\Models\VirtualNumberProduct;
use App\Models\VirtualNumberSubscription;
use App\Models\WalletTransaction;
use App\Services\AiraloService;
use App\Services\CryptomusService;
use App\Services\ExchangeRateService;
use App\Services\GloEsimService;
use App\Services\MyEsimService;
use App\Services\QrCodeService;
use App\Services\SmsPvaRentService;
use App\Services\SmsPvaService;
use App\Services\TwilioService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
Route::view('/delete-account', 'pages.delete-account')->name('delete.account');

if (! function_exists('social_number_order_payload')) {
    function social_number_order_payload(SocialNumberOrder $order, SmsPvaService $smsPva): array
    {
        $sms = [];
        $orderedAt = $order->ordered_at ?: $order->created_at;
        $expiresAt = $orderedAt ? $orderedAt->copy()->addSeconds(580) : null;
        $phone = trim((string) ($order->phone ?? ''));
        $country = $smsPva->country((string) $order->country);
        $prefix = trim((string) (data_get($order->provider_payload, 'buy.countryCode') ?? data_get($order->provider_payload, 'buy.CountryCode') ?? $country['prefix'] ?? ''));
        $phoneDisplay = $phone;
        if ($phone !== '' && ! str_starts_with($phone, '+') && $prefix !== '') {
            $digits = preg_replace('/\D+/', '', $phone) ?: $phone;
            $prefixDigits = preg_replace('/\D+/', '', $prefix) ?: '';
            if ($prefixDigits !== '' && str_starts_with($digits, $prefixDigits)) {
                $phoneDisplay = '+'.$digits;
            } else {
                $phoneDisplay = $prefix.ltrim($digits, '0');
            }
        }
        if (trim((string) $order->sms_code) !== '' || trim((string) $order->sms_text) !== '') {
            $sms[] = [
                'code' => (string) ($order->sms_code ?? ''),
                'sender' => (string) ($order->sms_sender ?? $order->product_name ?? 'SMS'),
                'text' => (string) ($order->sms_text ?? $order->sms_code ?? ''),
                'created_at' => $order->sms_received_at ? $order->sms_received_at->toIso8601String() : null,
            ];
        }

        return [
            'id' => (int) $order->id,
            'provider' => (string) $order->provider,
            'provider_order_id' => (string) ($order->provider_order_id ?? ''),
            'status' => (string) $order->status,
            'product' => (string) $order->product,
            'product_name' => (string) $order->product_name,
            'country' => (string) $order->country,
            'country_name' => (string) ($order->country_name ?? $order->country),
            'operator' => (string) ($order->operator ?? 'any'),
            'phone' => $phone,
            'phone_display' => $phoneDisplay,
            'sms' => $sms,
            'amount_minor' => (int) $order->sell_amount_minor,
            'amount_formatted' => $smsPva->formatUsd((int) $order->sell_amount_minor),
            'provider_cost_minor' => (int) $order->provider_cost_minor,
            'created_at' => $order->created_at ? $order->created_at->toIso8601String() : null,
            'ordered_at' => $order->ordered_at ? $order->ordered_at->toIso8601String() : null,
            'expires_at' => $expiresAt ? $expiresAt->toIso8601String() : null,
        ];
    }
}

if (! function_exists('social_extract_sms_code')) {
    function social_extract_sms_code(array $json): array
    {
        $rawCode = trim((string) (data_get($json, 'sms.code') ?? $json['sms'] ?? $json['code'] ?? $json['pass'] ?? ''));
        $text = trim((string) (data_get($json, 'sms.fullText') ?? $json['text'] ?? $json['message'] ?? $rawCode));
        $code = $rawCode;

        if ($code === '' && $text !== '' && preg_match('/(?<!\d)(\d{4,8})(?!\d)/', $text, $m)) {
            $code = (string) $m[1];
        }

        return [$code, $text !== '' ? $text : $code];
    }
}

if (! function_exists('social_refund_order')) {
    function social_refund_order(SocialNumberOrder $order, WalletService $wallet, string $reason): void
    {
        if ($order->refunded_at || (int) $order->sell_amount_minor <= 0 || trim((string) $order->sms_code) !== '') {
            return;
        }

        $wallet->credit((int) $order->user_id, (int) $order->sell_amount_minor, 'refund', [
            'reason' => $reason,
            'provider' => 'smspva',
            'social_number_order_id' => $order->id,
            'provider_order_id' => $order->provider_order_id,
        ], $order->payment_id ? (int) $order->payment_id : null);

        $order->refunded_at = now();
        if ($order->payment) {
            $order->payment->status = 'refunded';
            $order->payment->save();
        }
    }
}

if (! function_exists('social_rental_payload')) {
    function social_rental_payload(SocialNumberRental $rental, SmsPvaRentService $rent): array
    {
        $messages = is_array($rental->sms_messages) ? $rental->sms_messages : [];

        return [
            'id' => (int) $rental->id,
            'provider' => (string) $rental->provider,
            'provider_order_id' => (string) ($rental->provider_order_id ?? ''),
            'status' => (string) $rental->status,
            'product' => (string) $rental->product,
            'product_name' => (string) $rental->product_name,
            'country' => (string) $rental->country,
            'country_name' => (string) ($rental->country_name ?? $rental->country),
            'provider_name' => (string) ($rental->provider_name ?? ''),
            'phone' => trim((string) ($rental->phone_country_code ?? '').(string) ($rental->phone ?? '')),
            'raw_phone' => (string) ($rental->phone ?? ''),
            'phone_country_code' => (string) ($rental->phone_country_code ?? ''),
            'auto_renew' => (bool) $rental->auto_renew,
            'monthly_amount_minor' => (int) $rental->monthly_amount_minor,
            'monthly_amount_formatted' => $rent->formatUsd((int) $rental->monthly_amount_minor),
            'current_period_start' => $rental->current_period_start ? $rental->current_period_start->toIso8601String() : null,
            'current_period_end' => $rental->current_period_end ? $rental->current_period_end->toIso8601String() : null,
            'activated_at' => $rental->activated_at ? $rental->activated_at->toIso8601String() : null,
            'last_sms_sync_at' => $rental->last_sms_sync_at ? $rental->last_sms_sync_at->toIso8601String() : null,
            'renewal_failed_count' => (int) $rental->renewal_failed_count,
            'last_renewal_error' => (string) ($rental->last_renewal_error ?? ''),
            'sms' => array_values($messages),
            'created_at' => $rental->created_at ? $rental->created_at->toIso8601String() : null,
        ];
    }
}

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

Route::middleware(['auth:sanctum', 'throttle:5,1'])->delete('/api/auth/delete-account', function (Request $request) {
    $user = $request->user();
    if (! $user) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
    $user->tokens()->delete();
    $user->delete();

    return response()->json(['ok' => true]);
})->name('api.auth.delete_account');

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

    $lock = Cache::lock('wallet_verify_lock_'.sha1($reference), 60);
    if (! $lock->get()) {
        return response()->json(['message' => 'Verification in progress, please wait.'], 429);
    }

    try {
        $payment = Payment::where('provider', 'paystack')->where('provider_reference', $reference)->first();
        if (! $payment || (int) $payment->user_id !== (int) Auth::id() || (string) $payment->asset_type !== 'wallet_deposit') {
            return response()->json(['message' => 'Payment record not found.'], 404);
        }

        if ((string) $payment->status === 'fulfilled' && is_array($payment->fulfillment_payload) && (($payment->fulfillment_payload['ok'] ?? false) === true)) {
            return response()->json(['ok' => true, 'wallet' => $payment->fulfillment_payload, 'already_fulfilled' => true]);
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
            return response()->json(['message' => 'Failed to connect to Paystack for verification.'], 502);
        }

        if (! $response->successful()) {
            return response()->json(['message' => 'Paystack verification request failed.'], 502);
        }

        $json = $response->json();
        $status = (string) data_get($json, 'data.status', '');
        if ($status !== 'success') {
            return response()->json(['message' => 'Payment status is: '.$status], 422);
        }

        $txCurrency = strtoupper((string) data_get($json, 'data.currency', ''));
        $txAmount = (int) data_get($json, 'data.amount', 0);
        
        // Use loose comparison for amount to handle potential minor rounding issues if any, 
        // though Paystack should return exact minor units.
        if ($txCurrency === '' || $txAmount <= 0 || $txCurrency !== strtoupper((string) $payment->currency)) {
            return response()->json(['message' => 'Transaction details mismatch.'], 404);
        }

        $walletPayload = is_array($payment->provider_payload) ? (data_get($payment->provider_payload, 'wallet') ?? null) : null;
        $usdMinor = is_array($walletPayload) ? (int) ($walletPayload['usd_amount_minor'] ?? 0) : 0;
        
        // Fallback to metadata if payload is missing
        if ($usdMinor <= 0) {
            $usdMinor = (int) data_get($json, 'data.metadata.usd_amount_minor', 0);
        }

        if ($usdMinor <= 0) {
            return response()->json(['message' => 'Unable to determine credit amount.'], 422);
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

        // Optional email notification (non-blocking)
        try {
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

                Mail::send([], [], function ($message) use ($toEmail, $subject, $html) {
                    $message->to($toEmail)->subject($subject)->html($html);
                });
            }
        } catch (Throwable) {
        }

        return response()->json(['ok' => true, 'wallet' => $payload]);
    } finally {
        $lock->release();
    }
})->name('wallet.deposit.paystack.verify');

Route::middleware(['auth:sanctum', 'verified', 'throttle:10,1'])->post('/api/wallet/pay/esim', function (Request $request, AiraloService $airalo, WalletService $wallet) {
    $type = (string) $request->input('type', '');
    $id = (string) $request->input('id', '');
    $bundleId = (string) $request->input('bundle', '');
    $packageType = (string) $request->input('package_type', 'DATA-ONLY');
    $topupEsimId = trim((string) $request->input('topup_esim_id', ''));
    if ($type === '' || $id === '' || $bundleId === '') {
        return response()->json(['message' => 'Missing checkout parameters.'], 422);
    }

    $asset = $airalo->getAssetDetails($type, $id, $packageType);
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
            $canRenew = (bool) ($fp['can_renew'] ?? false);
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
                'data' => (string) ($bundle['data'] ?? ''),
                'validity' => (string) ($bundle['validity'] ?? ''),
                'price' => (float) $price,
                'price_formatted' => (string) ($bundle['price_formatted'] ?? ''),
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
        ? $airalo->topupEsim($topupEsimId, $bundleId, $reference, $email)
        : $airalo->fulfillEsim($bundleId, $reference, $email, $packageType);

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

    $lpa = (string) ($fulfillment['lpa'] ?? $fulfillment['activation_code'] ?? '');
    $payload = array_filter([
        'ok' => true,
        'synced' => true,
        'attempted_at' => now()->toIso8601String(),
        'type' => $type,
        'id' => $id,
        'bundle' => $bundleId,
        'package_type' => $packageType,
        'topup_esim_id' => $topupEsimId !== '' ? $topupEsimId : null,
        'esim_id' => $fulfillment['esim_id'] ?? null,
        'order_id' => $fulfillment['order_id'] ?? null,
        'activation_code' => (string) ($fulfillment['activation_code'] ?? ''),
        'lpa' => $lpa,
        'puk_code' => (string) ($fulfillment['puk_code'] ?? ''),
        'iccid' => (string) ($fulfillment['iccid'] ?? ''),
        'number' => is_scalar($fulfillment['number'] ?? null) ? (string) ($fulfillment['number'] ?? '') : '',
        'esim_status' => (string) ($fulfillment['esim_status'] ?? ''),
        'direct_installation_link_ios' => (string) ($fulfillment['direct_installation_link_ios'] ?? ''),
        'direct_installation_link_android' => (string) ($fulfillment['direct_installation_link_android'] ?? ''),
        'qr_code_url' => (string) ($fulfillment['qr_code_url'] ?? ''),
        'smdp_address' => (string) ($fulfillment['smdp_address'] ?? ''),
        'airalo' => $fulfillment['raw'] ?? $fulfillment,
    ], fn ($v) => $v !== null);

    $payment->status = 'fulfilled';
    $payment->fulfillment_payload = $payload;
    $payment->save();

    return response()->json(['ok' => true, 'fulfillment' => $payload]);
})->name('wallet.pay.esim');

Route::post('/api/airalo/webhook', function (Request $request, AiraloService $airalo) {
    $payload = $request->all();
    $event = (string) ($payload['event'] ?? '');
    
    Log::info('Airalo Webhook Received', ['event' => $event, 'payload' => $payload]);

    if ($event === 'sim.usage.low') {
        $iccid = (string) ($payload['data']['iccid'] ?? '');
        $usage = (int) ($payload['data']['usage'] ?? 0);
        $limit = (int) ($payload['data']['limit'] ?? 0);
        
        // Find the user who owns this eSIM
        $payment = Payment::where('fulfillment_payload->iccid', $iccid)
            ->where('status', 'fulfilled')
            ->orderByDesc('created_at')
            ->first();

        if ($payment && $payment->user) {
            $user = $payment->user;
            $toEmail = $user->email;
            $subject = 'Low Data Alert for your eSIM';
            
            $usageFormatted = round($usage / 1024 / 1024, 2) . ' MB';
            $limitFormatted = round($limit / 1024 / 1024, 2) . ' MB';
            
            $html = '<div style="font-family: Instrument Sans, Arial, sans-serif; background:#f7f7f8; padding:24px;">'
                .'<div style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:18px; border:1px solid rgba(20,84,84,.12); overflow:hidden;">'
                .'<div style="padding:18px 20px; background: #f27457; color:#fff;">'
                .'<div style="font-weight:900; letter-spacing:.02em;">Spacechip</div>'
                .'<div style="opacity:.9; margin-top:6px;">Low Data Alert</div>'
                .'</div>'
                .'<div style="padding:18px 20px; color:#0b1a1a;">'
                .'<div style="font-weight:900; font-size:16px; margin-bottom:10px;">You are running out of data</div>'
                .'<p>Your eSIM with ICCID <strong>'.$iccid.'</strong> has used '.$usageFormatted.' of its '.$limitFormatted.' data limit.</p>'
                .'<div style="margin-top:20px; text-align:center;">'
                .'<a href="'.url('/dashboard').'" style="display:inline-block; padding:12px 24px; background:#145454; color:#fff; text-decoration:none; border-radius:8px; font-weight:800;">Top Up Now</a>'
                .'</div>'
                .'<div style="margin-top:14px; color:rgba(15,31,31,.62); font-size:13px; line-height:1.5;">'
                .'To avoid interruption, we recommend topping up your data balance from your dashboard.'
                .'</div>'
                .'</div>'
                .'</div>'
                .'</div>';

            try {
                Mail::send([], [], function ($message) use ($toEmail, $subject, $html) {
                    $message->to($toEmail)->subject($subject)->html($html);
                });
                Log::info('Low data notification sent', ['user_id' => $user->id, 'iccid' => $iccid]);
            } catch (Throwable $e) {
                Log::error('Failed to send low data notification', ['error' => $e->getMessage()]);
            }
        }
    }

    return response()->json(['ok' => true]);
})->name('airalo.webhook');

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

Route::middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->get('/api/social-numbers/profile', function (SmsPvaService $smsPva) {
    if (! $smsPva->isConfigured()) {
        return response()->json(['message' => 'SMSPVA is not configured.'], 500);
    }

    $res = $smsPva->profile();
    if (($res['ok'] ?? false) !== true) {
        return response()->json(['message' => (string) ($res['error'] ?? 'Unable to fetch profile.')], 502);
    }

    return response()->json([
        'ok' => true,
        'profile' => $res['json'] ?? null,
    ]);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->get('/api/social-numbers/apps', function (SmsPvaService $smsPva) {
    $items = array_values(array_map(function (array $app) {
        return [
            'key' => (string) $app['key'],
            'name' => (string) $app['name'],
            'icon' => (string) $app['icon'],
            'description' => (string) $app['desc'],
            'category' => 'activation',
            'qty' => 1,
            'price' => null,
            'available' => true,
        ];
    }, $smsPva->apps()));

    return response()->json(['ok' => true, 'items' => $items]);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->get('/api/social-numbers/countries', function (SmsPvaService $smsPva) {
    return response()->json(['ok' => true, 'items' => $smsPva->countries()]);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:60,1'])->get('/api/social-numbers/operators', function (Request $request, SmsPvaService $smsPva) {
    $country = strtoupper(trim((string) $request->query('country', '')));
    if ($country === '') {
        return response()->json(['message' => 'Missing country.'], 422);
    }

    $operators = $smsPva->operators($country);

    return response()->json(['ok' => true, 'country' => $country, 'items' => $operators]);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->get('/api/social-numbers/prices', function (Request $request, SmsPvaService $smsPva) {
    if (! $smsPva->isConfigured()) {
        return response()->json(['message' => 'SMSPVA is not configured.'], 500);
    }

    $country = strtoupper(trim((string) $request->query('country', '')));
    $product = trim((string) $request->query('product', ''));
    if ($country === '' || $product === '') {
        return response()->json(['message' => 'Invalid country/product.'], 422);
    }

    $cacheKey = 'smspva.quote.v1.'.$country.'.'.$product;
    $quote = Cache::remember($cacheKey, now()->addSeconds(45), fn () => $smsPva->quote($product, $country));
    if (! is_array($quote) || ($quote['ok'] ?? false) !== true) {
        return response()->json(['message' => (string) ($quote['error'] ?? 'Unable to fetch prices.')], 502);
    }

    $count = (int) ($quote['count'] ?? 0);
    $sellMinor = (int) ($quote['sell_amount_minor'] ?? 0);

    return response()->json([
        'ok' => true,
        'country' => $country,
        'product' => $product,
        'min_cost' => $sellMinor > 0 ? $smsPva->formatUsd($sellMinor) : null,
        'total_count' => $count,
        'total_operators' => $count > 0 ? 1 : 0,
        'limit' => 1,
        'offset' => 0,
        'has_more' => false,
        'operators' => [[
            'operator' => 'any',
            'cost' => $sellMinor > 0 ? $smsPva->formatUsd($sellMinor) : null,
            'count' => $count,
            'rate' => null,
        ]],
    ]);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:15,1'])->post('/api/social-numbers/buy', function (Request $request, SmsPvaService $smsPva, WalletService $wallet) {
    if (! $smsPva->isConfigured()) {
        return response()->json(['message' => 'SMSPVA is not configured.'], 500);
    }

    $data = $request->validate([
        'product' => ['required', 'string'],
        'country' => ['required', 'string'],
        'operator' => ['nullable', 'string'],
    ]);

    $product = trim((string) $data['product']);
    $country = strtoupper(trim((string) $data['country']));
    $operator = trim((string) ($data['operator'] ?? 'any')) ?: 'any';
    $quote = $smsPva->quote($product, $country);
    if (($quote['ok'] ?? false) !== true) {
        return response()->json(['message' => (string) ($quote['error'] ?? 'Unable to quote number.')], 422);
    }

    $count = (int) ($quote['count'] ?? 0);
    if ($count <= 0) {
        return response()->json(['message' => 'No numbers are currently available for this app and country.'], 422);
    }

    $app = is_array($quote['app'] ?? null) ? $quote['app'] : [];
    $countryRow = is_array($quote['country'] ?? null) ? $quote['country'] : [];
    $amountMinor = (int) ($quote['sell_amount_minor'] ?? 0);
    if ($amountMinor <= 0) {
        return response()->json(['message' => 'Unable to determine price.'], 422);
    }

    $reference = 'sn_'.(string) Str::uuid();
    $payment = Payment::create([
        'user_id' => Auth::id(),
        'provider' => 'wallet',
        'provider_reference' => $reference,
        'status' => 'created',
        'asset_type' => 'social_number',
        'asset_id' => $product,
        'bundle_id' => $country,
        'package_type' => 'SMS_ACTIVATION',
        'currency' => 'USD',
        'amount_minor' => $amountMinor,
        'provider_payload' => [
            'provider' => 'smspva',
            'product' => $product,
            'country' => $country,
            'operator' => $operator,
            'quote' => $quote,
        ],
    ]);

    try {
        $wallet->debit((int) Auth::id(), $amountMinor, 'buy_social_number', [
            'reference' => $reference,
            'provider' => 'smspva',
            'product' => $product,
            'country' => $country,
            'operator' => $operator,
        ], (int) $payment->id, (int) env('WALLET_MIN_BALANCE_MINOR', 0));
    } catch (Throwable $e) {
        $payment->status = 'failed_insufficient_wallet';
        $payment->fulfillment_payload = ['ok' => false, 'error' => $e->getMessage()];
        $payment->save();

        return response()->json(['message' => $e->getMessage()], 422);
    }

    $buy = $smsPva->buyNumberV2((string) ($app['service'] ?? ''), $country, $operator);
    if (($buy['ok'] ?? false) !== true) {
        try {
            $wallet->credit((int) Auth::id(), $amountMinor, 'refund', [
                'reference' => $reference,
                'provider' => 'smspva',
                'reason' => (string) ($buy['error'] ?? 'SMSPVA purchase failed.'),
            ], (int) $payment->id);
        } catch (Throwable) {
        }

        $payment->status = 'paid_failed_provision_refunded';
        $payment->fulfillment_payload = ['ok' => false, 'error' => (string) ($buy['error'] ?? 'SMSPVA purchase failed.')];
        $payment->save();

        return response()->json(['message' => 'Number purchase failed. Wallet was refunded.'], 502);
    }

    $payload = is_array(data_get($buy, 'json.data')) ? data_get($buy, 'json.data') : [];
    $providerOrderId = (string) ($payload['orderId'] ?? '');
    $phone = (string) ($payload['phoneNumber'] ?? '');
    if ($providerOrderId === '' || $providerOrderId === '-1' || $phone === '') {
        try {
            $wallet->credit((int) Auth::id(), $amountMinor, 'refund', [
                'reference' => $reference,
                'provider' => 'smspva',
                'reason' => 'SMSPVA did not return a usable number.',
            ], (int) $payment->id);
        } catch (Throwable) {
        }

        $payment->status = 'paid_failed_provision_refunded';
        $payment->fulfillment_payload = ['ok' => false, 'payload' => $payload];
        $payment->save();

        return response()->json(['message' => 'SMSPVA did not return a usable number. Wallet was refunded.'], 502);
    }

    $order = SocialNumberOrder::create([
        'user_id' => Auth::id(),
        'payment_id' => $payment->id,
        'provider' => 'smspva',
        'provider_order_id' => $providerOrderId,
        'status' => 'PENDING',
        'product' => $product,
        'product_name' => (string) ($app['name'] ?? $product),
        'service_code' => (string) ($app['service'] ?? ''),
        'country' => $country,
        'country_name' => (string) ($countryRow['name'] ?? $country),
        'operator' => $operator,
        'phone' => $phone,
        'provider_cost_minor' => (int) ($quote['provider_cost_minor'] ?? 0),
        'sell_amount_minor' => $amountMinor,
        'currency' => 'USD',
        'provider_payload' => ['api_version' => 'v2', 'buy' => $payload, 'quote' => $quote],
        'ordered_at' => now(),
    ]);

    $payment->status = 'paid';
    $payment->asset_id = (string) $order->id;
    $payment->fulfillment_payload = ['ok' => true, 'order_id' => $order->id, 'provider_order_id' => $providerOrderId, 'phone' => $phone];
    $payment->save();

    return response()->json(['ok' => true, 'order' => social_number_order_payload($order, $smsPva)]);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:60,1'])->get('/api/social-numbers/check/{id}', function (int $id, SmsPvaService $smsPva, WalletService $wallet) {
    $order = SocialNumberOrder::query()->where('user_id', Auth::id())->findOrFail($id);
    $orderedAt = $order->ordered_at ?: $order->created_at;
    $hasExpiredLocally = $orderedAt ? $orderedAt->copy()->addSeconds(580)->isPast() : false;
    $elapsedSeconds = $orderedAt ? (int) $orderedAt->diffInSeconds(now()) : null;

    Log::info('SocialOTP poll', [
        'order_id'         => $order->id,
        'user_id'          => $order->user_id,
        'provider_order'   => $order->provider_order_id,
        'product'          => $order->product_name,
        'country'          => $order->country,
        'status'           => $order->status,
        'elapsed_seconds'  => $elapsedSeconds,
        'expired_locally'  => $hasExpiredLocally,
    ]);

    if (in_array((string) $order->status, ['PENDING', 'WAITING', 'RECEIVED'], true) && $order->provider_order_id) {
        // Always use v1 getSms for polling — it queries the order directly by ID.
        // The v2 /activation/orders list only shows WAITING orders; once SMSPVA
        // marks an order SMS_READY the entry disappears from that list, so findOrderV2
        // returns null and the code is never captured. v1 getSms works for both
        // v1 and v2 order IDs and reliably returns response "1" when SMS is ready.
        $res = $smsPva->getSms((string) $order->service_code, (string) $order->country, (string) $order->provider_order_id);
        if (($res['ok'] ?? false) === true) {
            $json = is_array($res['json'] ?? null) ? $res['json'] : [];
            $order->provider_payload = array_merge(is_array($order->provider_payload) ? $order->provider_payload : [], ['last_sms_poll' => $json]);
            $response = (string) ($json['response'] ?? $json['responce'] ?? '');
            [$code, $text] = social_extract_sms_code($json);
            if ($response === '1' || $code !== '') {
                $order->status = 'RECEIVED';
                $order->sms_code = $code;
                $order->sms_text = $text;
                $order->sms_sender = (string) ($json['sender'] ?? $order->product_name);
                $order->sms_received_at = $order->sms_received_at ?: now();
                $order->provider_payload = array_merge(is_array($order->provider_payload) ? $order->provider_payload : [], ['last_sms' => $json]);
                $order->save();
                $logMsg = sprintf(
                    '[SocialOTP] ORDER #%s RECEIVED — Code: %s | Text: %s | Product: %s | Country: %s | Elapsed: %ss',
                    $order->id, $code, $text, $order->product_name, $order->country, $elapsedSeconds ?? '?'
                );
                error_log($logMsg);
                Log::warning($logMsg, [
                    'order_id'        => $order->id,
                    'user_id'         => $order->user_id,
                    'provider_order'  => $order->provider_order_id,
                    'sms_code'        => $code,
                    'sms_text'        => $text,
                    'elapsed_seconds' => $elapsedSeconds,
                ]);
            } elseif ($response === '3' && $hasExpiredLocally) {
                $smsPva->ban((string) $order->service_code, (string) $order->provider_order_id);
                social_refund_order($order, $wallet, 'SMSPVA order expired before SMS arrived.');
                $order->status = 'TIMEOUT';
                $order->canceled_at = $order->canceled_at ?: now();
                $order->save();
                Log::warning('SocialOTP timeout (provider expired)', [
                    'order_id'        => $order->id,
                    'user_id'         => $order->user_id,
                    'provider_order'  => $order->provider_order_id,
                    'elapsed_seconds' => $elapsedSeconds,
                ]);
            } elseif (in_array($response, ['2', '3', '4'], true)) {
                $order->status = $order->sms_code ? 'RECEIVED' : 'PENDING';
                $order->save();
            }
        } else {
            Log::warning('SocialOTP provider poll failed', [
                'order_id'       => $order->id,
                'user_id'        => $order->user_id,
                'provider_order' => $order->provider_order_id,
                'provider_error' => $res['error'] ?? null,
            ]);
        }
    }

    $order = $order->fresh();
    $orderedAt = $order->ordered_at ?: $order->created_at;
    if (
        in_array((string) $order->status, ['PENDING', 'WAITING'], true)
        && trim((string) $order->sms_code) === ''
        && $orderedAt
        && $orderedAt->copy()->addSeconds(580)->isPast()
    ) {
        $usesV2 = (string) data_get($order->provider_payload, 'api_version') === 'v2';
        if ($usesV2) {
            $smsPva->banV2((string) $order->provider_order_id);
        }
        $smsPva->ban((string) $order->service_code, (string) $order->country, (string) $order->provider_order_id);
        social_refund_order($order, $wallet, 'Social number OTP timed out after 10 minutes.');
        $order->status = 'TIMEOUT';
        $order->canceled_at = $order->canceled_at ?: now();
        $order->save();
        Log::warning('SocialOTP timeout (local expiry)', [
            'order_id'        => $order->id,
            'user_id'         => $order->user_id,
            'provider_order'  => $order->provider_order_id,
            'elapsed_seconds' => $elapsedSeconds,
        ]);
    }

    return response()->json(['ok' => true, 'order' => social_number_order_payload($order->fresh(), $smsPva)]);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:20,1'])->post('/api/social-numbers/orders/{id}/finish', function (int $id, SmsPvaService $smsPva) {
    $order = SocialNumberOrder::query()->where('user_id', Auth::id())->findOrFail($id);
    if (trim((string) $order->sms_code) === '') {
        return response()->json(['message' => 'No OTP has been received for this order yet.'], 422);
    }

    if (! in_array((string) $order->status, ['CANCELED', 'BANNED', 'TIMEOUT'], true)) {
        $order->status = 'FINISHED';
        $order->finished_at = now();
        $order->save();
    }

    return response()->json(['ok' => true, 'order' => social_number_order_payload($order, $smsPva)]);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:20,1'])->post('/api/social-numbers/orders/{id}/cancel', function (int $id, SmsPvaService $smsPva, WalletService $wallet) {
    $order = SocialNumberOrder::query()->where('user_id', Auth::id())->findOrFail($id);
    if (! in_array((string) $order->status, ['FINISHED', 'CANCELED', 'BANNED', 'TIMEOUT'], true)) {
        if ((string) data_get($order->provider_payload, 'api_version') === 'v2') {
            $smsPva->cancelV2((string) $order->provider_order_id);
        } else {
            $smsPva->cancel((string) $order->service_code, (string) $order->country, (string) $order->provider_order_id);
        }
        social_refund_order($order, $wallet, 'Social number canceled before SMS was received.');
        $order->status = 'CANCELED';
        $order->canceled_at = now();
        $order->save();
    }

    return response()->json(['ok' => true, 'order' => social_number_order_payload($order, $smsPva)]);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:20,1'])->post('/api/social-numbers/orders/{id}/ban', function (int $id, SmsPvaService $smsPva, WalletService $wallet) {
    $order = SocialNumberOrder::query()->where('user_id', Auth::id())->findOrFail($id);
    if (! in_array((string) $order->status, ['FINISHED', 'CANCELED', 'BANNED', 'TIMEOUT'], true)) {
        if ((string) data_get($order->provider_payload, 'api_version') === 'v2') {
            $smsPva->banV2((string) $order->provider_order_id);
        } else {
            $smsPva->ban((string) $order->service_code, (string) $order->provider_order_id);
        }
        social_refund_order($order, $wallet, 'Social number banned before SMS was received.');
        $order->status = 'BANNED';
        $order->canceled_at = now();
        $order->save();
    }

    return response()->json(['ok' => true, 'order' => social_number_order_payload($order, $smsPva)]);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:10,1'])->post('/api/social-numbers/orders/{id}/try-another', function (int $id, SmsPvaService $smsPva, WalletService $wallet) {
    if (! $smsPva->isConfigured()) {
        return response()->json(['message' => 'SMSPVA is not configured.'], 500);
    }

    $old = SocialNumberOrder::query()->where('user_id', Auth::id())->findOrFail($id);
    if (trim((string) $old->sms_code) !== '' || in_array((string) $old->status, ['RECEIVED', 'FINISHED'], true)) {
        return response()->json(['message' => 'This order already received an OTP.'], 422);
    }
    if (in_array((string) $old->status, ['CANCELED', 'BANNED'], true)) {
        return response()->json(['message' => 'This order is already closed.'], 422);
    }

    $product = (string) $old->product;
    $country = (string) $old->country;
    $operator = (string) ($old->operator ?: 'any');

    $quote = $smsPva->quote($product, $country);
    if (($quote['ok'] ?? false) !== true) {
        return response()->json(['message' => (string) ($quote['error'] ?? 'Unable to quote another number.')], 422);
    }
    if ((int) ($quote['count'] ?? 0) <= 0) {
        return response()->json(['message' => 'No replacement numbers are currently available.'], 422);
    }

    if ((string) $old->status !== 'TIMEOUT') {
        try {
            $cancel = (string) data_get($old->provider_payload, 'api_version') === 'v2'
                ? $smsPva->cancelV2((string) $old->provider_order_id)
                : $smsPva->cancel((string) $old->service_code, (string) $old->country, (string) $old->provider_order_id);
            if (($cancel['ok'] ?? false) !== true) {
                return response()->json(['message' => (string) ($cancel['error'] ?? 'Unable to close the current number. Try again in a moment.')], 422);
            }
            social_refund_order($old, $wallet, 'Social number replaced before SMS was received.');
            $old->status = 'CANCELED';
            $old->canceled_at = now();
            $old->save();
        } catch (Throwable $e) {
            return response()->json(['message' => 'Unable to close the current number. Try again in a moment.'], 502);
        }
    }

    $app = is_array($quote['app'] ?? null) ? $quote['app'] : [];
    $countryRow = is_array($quote['country'] ?? null) ? $quote['country'] : [];
    $amountMinor = (int) ($quote['sell_amount_minor'] ?? 0);
    if ($amountMinor <= 0) {
        return response()->json(['message' => 'Unable to determine replacement price.'], 422);
    }

    $reference = 'sn_'.(string) Str::uuid();
    $payment = Payment::create([
        'user_id' => Auth::id(),
        'provider' => 'wallet',
        'provider_reference' => $reference,
        'status' => 'created',
        'asset_type' => 'social_number',
        'asset_id' => $product,
        'bundle_id' => $country,
        'package_type' => 'SMS_ACTIVATION',
        'currency' => 'USD',
        'amount_minor' => $amountMinor,
        'provider_payload' => [
            'provider' => 'smspva',
            'product' => $product,
            'country' => $country,
            'operator' => $operator,
            'quote' => $quote,
            'replaces_order_id' => $old->id,
        ],
    ]);

    try {
        $wallet->debit((int) Auth::id(), $amountMinor, 'buy_social_number', [
            'reference' => $reference,
            'provider' => 'smspva',
            'product' => $product,
            'country' => $country,
            'operator' => $operator,
            'replaces_order_id' => $old->id,
        ], (int) $payment->id, (int) env('WALLET_MIN_BALANCE_MINOR', 0));
    } catch (Throwable $e) {
        $payment->status = 'failed_insufficient_wallet';
        $payment->fulfillment_payload = ['ok' => false, 'error' => $e->getMessage()];
        $payment->save();

        return response()->json(['message' => $e->getMessage()], 422);
    }

    $buy = $smsPva->buyNumberV2((string) ($app['service'] ?? ''), $country, $operator);
    if (($buy['ok'] ?? false) !== true) {
        try {
            $wallet->credit((int) Auth::id(), $amountMinor, 'refund', [
                'reference' => $reference,
                'provider' => 'smspva',
                'reason' => (string) ($buy['error'] ?? 'SMSPVA replacement purchase failed.'),
                'replaces_order_id' => $old->id,
            ], (int) $payment->id);
        } catch (Throwable) {
        }

        $payment->status = 'paid_failed_provision_refunded';
        $payment->fulfillment_payload = ['ok' => false, 'error' => (string) ($buy['error'] ?? 'SMSPVA replacement purchase failed.')];
        $payment->save();

        return response()->json(['message' => 'Replacement number purchase failed. Wallet was refunded.'], 502);
    }

    $payload = is_array(data_get($buy, 'json.data')) ? data_get($buy, 'json.data') : [];
    $providerOrderId = (string) ($payload['orderId'] ?? '');
    $phone = (string) ($payload['phoneNumber'] ?? '');
    if ($providerOrderId === '' || $providerOrderId === '-1' || $phone === '') {
        try {
            $wallet->credit((int) Auth::id(), $amountMinor, 'refund', [
                'reference' => $reference,
                'provider' => 'smspva',
                'reason' => 'SMSPVA did not return a usable replacement number.',
                'replaces_order_id' => $old->id,
            ], (int) $payment->id);
        } catch (Throwable) {
        }

        $payment->status = 'paid_failed_provision_refunded';
        $payment->fulfillment_payload = ['ok' => false, 'payload' => $payload];
        $payment->save();

        return response()->json(['message' => 'SMSPVA did not return a usable replacement number. Wallet was refunded.'], 502);
    }

    $order = SocialNumberOrder::create([
        'user_id' => Auth::id(),
        'payment_id' => $payment->id,
        'provider' => 'smspva',
        'provider_order_id' => $providerOrderId,
        'status' => 'PENDING',
        'product' => $product,
        'product_name' => (string) ($app['name'] ?? $old->product_name ?? $product),
        'service_code' => (string) ($app['service'] ?? $old->service_code),
        'country' => $country,
        'country_name' => (string) ($countryRow['name'] ?? $old->country_name ?? $country),
        'operator' => $operator,
        'phone' => $phone,
        'provider_cost_minor' => (int) ($quote['provider_cost_minor'] ?? 0),
        'sell_amount_minor' => $amountMinor,
        'currency' => 'USD',
        'provider_payload' => ['api_version' => 'v2', 'buy' => $payload, 'quote' => $quote, 'replaces_order_id' => $old->id],
        'ordered_at' => now(),
    ]);

    $payment->status = 'paid';
    $payment->asset_id = (string) $order->id;
    $payment->fulfillment_payload = ['ok' => true, 'order_id' => $order->id, 'provider_order_id' => $providerOrderId, 'phone' => $phone, 'replaces_order_id' => $old->id];
    $payment->save();

    return response()->json(['ok' => true, 'order' => social_number_order_payload($order, $smsPva)]);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->get('/api/social-numbers/orders', function (Request $request, SmsPvaService $smsPva) {
    $limit = max(1, min(50, (int) $request->query('limit', 20)));
    $offset = max(0, (int) $request->query('offset', 0));

    $query = SocialNumberOrder::query()
        ->where('user_id', Auth::id())
        ->orderByDesc('created_at');

    $total = (clone $query)->count();
    $items = $query->skip($offset)->take($limit)->get()->map(fn (SocialNumberOrder $order) => social_number_order_payload($order, $smsPva))->values();

    return response()->json([
        'ok' => true,
        'total' => $total,
        'items' => $items,
    ]);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->get('/api/social-rentals/apps', function (SmsPvaRentService $rent) {
    $items = array_values(array_map(function (array $app) {
        return [
            'key' => (string) $app['key'],
            'name' => (string) $app['name'],
            'icon' => (string) $app['icon'],
            'description' => (string) $app['desc'],
            'available' => true,
        ];
    }, $rent->apps()));

    return response()->json(['ok' => true, 'items' => $items]);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->get('/api/social-rentals/countries', function (SmsPvaRentService $rent) {
    return response()->json(['ok' => true, 'items' => $rent->countries()]);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->get('/api/social-rentals/quote', function (Request $request, SmsPvaRentService $rent) {
    if (! $rent->isConfigured()) {
        return response()->json(['message' => 'SMSPVA is not configured.'], 500);
    }

    $product = trim((string) $request->query('product', ''));
    $country = strtoupper(trim((string) $request->query('country', '')));
    $provider = trim((string) $request->query('provider', 'all_providers')) ?: 'all_providers';
    $quote = $rent->quote($product, $country, $provider, 'month', 1);
    if (($quote['ok'] ?? false) !== true) {
        return response()->json(['message' => (string) ($quote['error'] ?? 'Unable to quote monthly rental.')], 422);
    }

    return response()->json([
        'ok' => true,
        'product' => $product,
        'country' => $country,
        'provider' => $provider,
        'count' => (int) ($quote['count'] ?? 0),
        'providers' => array_values(array_filter(array_map('strval', (array) ($quote['providers'] ?? [])))),
        'provider_cost_minor' => (int) ($quote['provider_cost_minor'] ?? 0),
        'monthly_amount_minor' => (int) ($quote['sell_amount_minor'] ?? 0),
        'monthly_amount_formatted' => $rent->formatUsd((int) ($quote['sell_amount_minor'] ?? 0)),
        'period' => '1 month',
    ]);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:10,1'])->post('/api/social-rentals/buy', function (Request $request, SmsPvaRentService $rent, WalletService $wallet) {
    if (! $rent->isConfigured()) {
        return response()->json(['message' => 'SMSPVA is not configured.'], 500);
    }

    $data = $request->validate([
        'product' => ['required', 'string'],
        'country' => ['required', 'string'],
        'provider' => ['nullable', 'string'],
    ]);

    $product = trim((string) $data['product']);
    $country = strtoupper(trim((string) $data['country']));
    $provider = trim((string) ($data['provider'] ?? 'all_providers')) ?: 'all_providers';
    $quote = $rent->quote($product, $country, $provider, 'month', 1);
    if (($quote['ok'] ?? false) !== true) {
        return response()->json(['message' => (string) ($quote['error'] ?? 'Unable to quote monthly rental.')], 422);
    }
    if ((int) ($quote['count'] ?? 0) <= 0) {
        return response()->json(['message' => 'No monthly rental numbers are available for this app and country.'], 422);
    }

    $app = is_array($quote['app'] ?? null) ? $quote['app'] : [];
    $countryRow = is_array($quote['country'] ?? null) ? $quote['country'] : [];
    $amountMinor = (int) ($quote['sell_amount_minor'] ?? 0);
    if ($amountMinor <= 0) {
        return response()->json(['message' => 'Unable to determine monthly price.'], 422);
    }

    $reference = 'snrent_'.(string) Str::uuid();
    $payment = Payment::create([
        'user_id' => Auth::id(),
        'provider' => 'wallet',
        'provider_reference' => $reference,
        'status' => 'created',
        'asset_type' => 'social_number_rental',
        'asset_id' => $product,
        'bundle_id' => $country,
        'package_type' => 'MONTHLY',
        'currency' => 'USD',
        'amount_minor' => $amountMinor,
        'provider_payload' => [
            'provider' => 'smspva_rent',
            'product' => $product,
            'country' => $country,
            'selected_provider' => $provider,
            'quote' => $quote,
        ],
    ]);

    try {
        $wallet->debit((int) Auth::id(), $amountMinor, 'buy_social_rental', [
            'reference' => $reference,
            'provider' => 'smspva_rent',
            'product' => $product,
            'country' => $country,
            'selected_provider' => $provider,
        ], (int) $payment->id, (int) env('WALLET_MIN_BALANCE_MINOR', 0));
    } catch (Throwable $e) {
        $payment->status = 'failed_insufficient_wallet';
        $payment->fulfillment_payload = ['ok' => false, 'error' => $e->getMessage()];
        $payment->save();

        return response()->json(['message' => $e->getMessage()], 422);
    }

    $create = $rent->create((string) ($app['service'] ?? ''), $country, 'month', 1, $provider);
    if (($create['ok'] ?? false) !== true) {
        try {
            $wallet->credit((int) Auth::id(), $amountMinor, 'refund', [
                'reference' => $reference,
                'provider' => 'smspva_rent',
                'reason' => (string) ($create['error'] ?? 'SMSPVA rental purchase failed.'),
            ], (int) $payment->id);
        } catch (Throwable) {
        }

        $payment->status = 'paid_failed_provision_refunded';
        $payment->fulfillment_payload = ['ok' => false, 'error' => (string) ($create['error'] ?? 'SMSPVA rental purchase failed.')];
        $payment->save();

        return response()->json(['message' => 'Monthly number rental failed. Wallet was refunded.'], 502);
    }

    $payload = is_array($create['json']['data'] ?? null) ? $create['json']['data'] : [];
    $providerOrderId = (string) ($payload['id'] ?? '');
    $phone = (string) ($payload['pnumber'] ?? '');
    $ccode = (string) ($payload['ccode'] ?? '');
    if ($providerOrderId === '' || $phone === '') {
        try {
            $wallet->credit((int) Auth::id(), $amountMinor, 'refund', [
                'reference' => $reference,
                'provider' => 'smspva_rent',
                'reason' => 'SMSPVA did not return a usable monthly rental number.',
            ], (int) $payment->id);
        } catch (Throwable) {
        }

        $payment->status = 'paid_failed_provision_refunded';
        $payment->fulfillment_payload = ['ok' => false, 'payload' => $payload];
        $payment->save();

        return response()->json(['message' => 'SMSPVA did not return a usable monthly rental number. Wallet was refunded.'], 502);
    }

    $end = $rent->timestampToCarbon($payload['until'] ?? null) ?: now()->addMonth();
    $rental = SocialNumberRental::create([
        'user_id' => Auth::id(),
        'payment_id' => $payment->id,
        'provider' => 'smspva_rent',
        'provider_order_id' => $providerOrderId,
        'status' => 'pending_activation',
        'product' => $product,
        'product_name' => (string) ($app['name'] ?? $product),
        'service_code' => (string) ($app['service'] ?? ''),
        'country' => $country,
        'country_name' => (string) ($countryRow['name'] ?? $country),
        'provider_name' => $provider !== 'all_providers' ? $provider : null,
        'phone' => $phone,
        'phone_country_code' => $ccode,
        'provider_cost_minor' => (int) ($quote['provider_cost_minor'] ?? 0),
        'monthly_amount_minor' => $amountMinor,
        'currency' => 'USD',
        'auto_renew' => true,
        'current_period_start' => now(),
        'current_period_end' => $end,
        'sms_messages' => [],
        'provider_payload' => ['create' => $payload, 'quote' => $quote],
    ]);

    $payment->status = 'paid';
    $payment->asset_id = (string) $rental->id;
    $payment->fulfillment_payload = ['ok' => true, 'rental_id' => $rental->id, 'provider_order_id' => $providerOrderId, 'phone' => trim($ccode.$phone)];
    $payment->save();

    return response()->json(['ok' => true, 'rental' => social_rental_payload($rental, $rent)]);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:20,1'])->post('/api/social-rentals/{rental}/activate', function (SocialNumberRental $rental, SmsPvaRentService $rent) {
    if ((int) $rental->user_id !== (int) Auth::id()) {
        abort(403);
    }
    if (! in_array((string) $rental->status, ['active', 'pending_activation'], true)) {
        return response()->json(['message' => 'This rental cannot be activated.'], 422);
    }

    $res = $rent->activate((string) $rental->provider_order_id);
    if (($res['ok'] ?? false) !== true) {
        return response()->json(['message' => (string) ($res['error'] ?? 'Activation failed.')], 502);
    }

    $rental->status = 'active';
    $rental->activated_at = now();
    $rental->provider_payload = array_merge(is_array($rental->provider_payload) ? $rental->provider_payload : [], ['last_activate' => $res['json'] ?? null]);
    $rental->save();

    return response()->json(['ok' => true, 'rental' => social_rental_payload($rental, $rent)]);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->get('/api/social-rentals/{rental}/sms', function (SocialNumberRental $rental, SmsPvaRentService $rent) {
    if ((int) $rental->user_id !== (int) Auth::id()) {
        abort(403);
    }

    $res = $rent->sms((string) $rental->provider_order_id);
    if (($res['ok'] ?? false) === true) {
        $data = is_array($res['json']['data'] ?? null) ? $res['json']['data'] : [];
        $messages = [];
        foreach ($data as $row) {
            if (! is_array($row)) {
                continue;
            }
            $messages[] = [
                'sender' => (string) ($row['sender'] ?? 'SMS'),
                'text' => (string) ($row['text'] ?? ''),
                'date' => isset($row['date']) && is_numeric($row['date']) ? (int) $row['date'] : null,
                'created_at' => isset($row['date']) && is_numeric($row['date']) ? now()->setTimestamp((int) $row['date'])->toIso8601String() : now()->toIso8601String(),
            ];
        }
        $rental->sms_messages = $messages;
        $rental->last_sms_sync_at = now();
        $rental->provider_payload = array_merge(is_array($rental->provider_payload) ? $rental->provider_payload : [], ['last_sms' => $res['json'] ?? null]);
        $rental->save();
    }

    return response()->json(['ok' => true, 'rental' => social_rental_payload($rental->fresh(), $rent)]);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:20,1'])->post('/api/social-rentals/{rental}/cancel', function (SocialNumberRental $rental, SmsPvaRentService $rent) {
    if ((int) $rental->user_id !== (int) Auth::id()) {
        abort(403);
    }

    $rental->auto_renew = false;
    if (! in_array((string) $rental->status, ['canceled', 'expired'], true)) {
        $rental->status = 'canceled';
        $rental->canceled_at = now();
    }
    $rental->save();

    return response()->json(['ok' => true, 'rental' => social_rental_payload($rental, $rent)]);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->get('/api/social-rentals', function (Request $request, SmsPvaRentService $rent) {
    $limit = max(1, min(50, (int) $request->query('limit', 20)));
    $offset = max(0, (int) $request->query('offset', 0));

    $query = SocialNumberRental::query()
        ->where('user_id', Auth::id())
        ->orderByDesc('created_at');
    $total = (clone $query)->count();
    $items = $query->skip($offset)->take($limit)->get()->map(fn (SocialNumberRental $rental) => social_rental_payload($rental, $rent))->values();

    return response()->json(['ok' => true, 'total' => $total, 'items' => $items]);
});

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

Route::get('/debug/airalo-catalog', function (AiraloService $airalo) {
    $type = request('type', 'local');
    return response()->json($airalo->getRawPackages($type));
});

Route::get('/debug/airalo-asset', function (AiraloService $airalo) {
    $type = request('type', 'country');
    $id = request('id', 'france');
    return response()->json([
        'asset_details' => $airalo->getAssetDetails($type, $id, 'DATA-ONLY'),
        'raw_packages_response' => $airalo->getRawPackages($type === 'region' ? 'regional' : 'local'),
    ]);
});

Route::get('/api/landing', function (AiraloService $airalo) {
    return response()->json([
        'popularCountries' => $airalo->popularCountries(6),
        'popularRegions' => $airalo->popularRegions(7),
        'searchableAssets' => $airalo->searchableAssets(),
    ]);
});

Route::get('/allassets', function () {
    return view('allassets');
});

Route::get('/api/allassets', function (AiraloService $airalo) {
    $tab = (string) request('tab', '');
    if ($tab !== '') {
        $tab = in_array($tab, ['countries', 'regions', 'virtual'], true) ? $tab : 'countries';
        $page = max(1, (int) request('page', 1));
        $perPage = (int) request('per_page', 30);
        $perPage = max(10, min(60, $perPage));
        $q = trim((string) request('q', ''));
        $qLower = mb_strtolower($q);

        if ($tab === 'countries') {
            $items = $airalo->allCountriesWithPrices('DATA-ONLY');
        } elseif ($tab === 'regions') {
            $items = $airalo->allRegionsWithPrices();
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
        'countries' => $airalo->allCountriesWithPrices('DATA-ONLY'),
        'regions' => $airalo->allRegionsWithPrices(),
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

Route::get('/api/assets/{type}/{id}/bundles', function (string $type, string $id, AiraloService $airalo) {
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
});

Route::get('/assets/{type}/{id}', function (string $type, string $id, AiraloService $airalo) {
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
})->name('asset.details');

Route::middleware(['auth'])->get('/checkout', function (AiraloService $airalo, ExchangeRateService $exchangeRates) {
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

    $paystackCurrency = (string) config('services.paystack.currency', 'NGN');
    $paystackCurrency = strtoupper(trim($paystackCurrency)) ?: 'NGN';
    $rateSource = null;
    $usdToNgnRate = 0.0;
    $paystackAmountNgnRaw = null;
    $paystackAmountNgnAdjusted = null;
    $paystackAmountMinorNgn = 0;
    if ($paystackCurrency === 'NGN') {
        $ratePayload = $exchangeRates->usdToNgn();
        $usdToNgnRate = (float) ($ratePayload['rate'] ?? 0);
        $rateSource = (string) ($ratePayload['source'] ?? 'unavailable');
        if ($usdToNgnRate <= 0) {
            abort(422, 'Unable to fetch USD to NGN exchange rate. Set ESIM_USD_TO_NGN_RATE as a fallback or try again shortly.');
        }
        $paystackAmountNgnRaw = ((float) $usdPrice) * $usdToNgnRate;
        $paystackAmountNgnAdjusted = $paystackAmountNgnRaw;
        $paystackAmountMinorNgn = (int) round($paystackAmountNgnRaw * 100);
    }
    $paystackAmountMinorUsd = (int) round(((float) $usdPrice) * 100);

    if (($paystackCurrency === 'NGN' && $paystackAmountMinorNgn <= 0) || $paystackAmountMinorUsd <= 0) {
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
            $canRenew = (bool) ($fp['can_renew'] ?? false);
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
        'paystackAmountNgnRaw' => $paystackAmountNgnRaw === null ? 0.0 : (float) $paystackAmountNgnRaw,
        'paystackAmountNgnAdjusted' => $paystackAmountNgnAdjusted === null ? 0.0 : (float) $paystackAmountNgnAdjusted,
        'usdToNgnRateSource' => $rateSource,
    ]);
})->name('checkout');

Route::middleware(['auth:sanctum', 'throttle:10,1'])->post('/api/paystack/initialize', function (AiraloService $airalo, ExchangeRateService $exchangeRates) {
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

    $asset = $airalo->getAssetDetails((string) $type, (string) $id, (string) $packageType);
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
        $ratePayload = $exchangeRates->usdToNgn();
        $usdToNgnRate = (float) ($ratePayload['rate'] ?? 0);
        $rateSource = (string) ($ratePayload['source'] ?? 'unavailable');
        if ($usdToNgnRate <= 0) {
            return response()->json(['message' => 'Unable to fetch USD to NGN exchange rate. Set ESIM_USD_TO_NGN_RATE as a fallback or try again shortly.'], 422);
        }
        $amountNgnRaw = ((float) $price) * $usdToNgnRate;
        $amountNgnAdjusted = $amountNgnRaw;
        $amountMinor = (int) round($amountNgnRaw * 100);
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
            $canRenew = (bool) ($fp['can_renew'] ?? false);
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

Route::middleware(['auth:sanctum', 'throttle:20,1'])->post('/api/paystack/verify', function (AiraloService $airalo, QrCodeService $qr) {
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

    // Safeguard: Check if payment already has fulfillment payload in DB
    if (is_array($payment->fulfillment_payload) && ($payment->fulfillment_payload['ok'] ?? false) === true) {
        $existing = [
            'ok' => true,
            'status' => $payment->status,
            'reference' => $payment->provider_reference,
            'fulfillment' => $payment->fulfillment_payload,
        ];
        Cache::put($fulfilledCacheKey, $existing, now()->addHours(6));
        return response()->json($existing);
    }

    $lock = Cache::lock('fulfillment_lock_'.sha1($reference), 60);
    if (! $lock->get()) {
        return response()->json(['message' => 'Fulfillment in progress, please wait.'], 429);
    }

    try {
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

        $asset = $airalo->getAssetDetails($type, $id, $packageType);
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
                if (trim((string) ($fp['esim_id'] ?? '')) === $topupEsimId || trim((string) ($fp['iccid'] ?? '')) === $topupEsimId) {
                    $ownsTarget = true;
                    break;
                }
            }
            if (! $ownsTarget) {
                return response()->json(['message' => 'Payment not found.'], 404);
            }
        }

        $fulfillment = $topupEsimId !== ''
            ? $airalo->topupEsim($topupEsimId, $bundleId, $reference, $email)
            : $airalo->fulfillEsim($bundleId, $reference, $email, $packageType);
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

        $details = $esimId !== '' ? $airalo->getEsimDetails($esimId) : null;
        $detailsOk = is_array($details) && (($details['ok'] ?? false) === true);

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
                        'airalo' => $detailsOk ? ($details['raw'] ?? $details) : ($fulfillment['raw'] ?? $fulfillment),
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
                'airalo' => $detailsOk ? ($details['raw'] ?? $details) : ($fulfillment['raw'] ?? $fulfillment),
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
    } finally {
        $lock->release();
    }
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

Route::middleware(['auth:sanctum', 'throttle:30,1'])->post('/api/paystack/status', function (AiraloService $airalo, QrCodeService $qr) {
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
    $fulfillment = $resolvedEsimId !== ''
        ? $airalo->getEsimDetails($resolvedEsimId)
        : ($bundleId !== '' ? ($topupEsimId !== '' ? $airalo->topupEsim($topupEsimId, $bundleId, $reference, $email) : $airalo->fulfillEsim($bundleId, $reference, $email, $packageType)) : ['ok' => false, 'error' => 'Missing bundle id.']);

    $detailsOk = ($fulfillment['ok'] ?? false) === true;
    $details = $fulfillment;

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
        'airalo' => $detailsOk ? ($details['raw'] ?? $details) : ($fulfillment['raw'] ?? $fulfillment),
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

Route::middleware(['auth', 'throttle:10,1'])->post('/api/cryptomus/invoice', function (Request $request, CryptomusService $cryptomus, AiraloService $airalo) {
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

    $asset = $airalo->getAssetDetails($type, $id, $packageType);
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
            $canRenew = (bool) ($fp['can_renew'] ?? false);
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

Route::middleware(['auth:sanctum'])->get('/api/my-esims', function (Request $request, QrCodeService $qr, MyEsimService $myEsims) {
    $filter = (string) $request->query('filter', 'valid');
    $page = max(1, (int) $request->query('page', 1));
    $perPage = (int) $request->query('per_page', 10);

    return response()->json($myEsims->listForUser((int) Auth::id(), $filter, $page, $perPage, $qr));
})->name('myesims.list');

Route::middleware(['auth:sanctum', 'throttle:15,1'])->post('/api/my-esims/sync', function (Request $request, AiraloService $airalo, GloEsimService $gloEsim, QrCodeService $qr) {
    $userId = (int) Auth::id();

    $rows = Payment::query()
        ->where('user_id', $userId)
        ->whereIn('provider', ['paystack', 'cryptomus'])
        ->whereNotNull('fulfillment_payload')
        ->orderByDesc('created_at')
        ->limit(60)
        ->get();

    $updated = 0;
    foreach ($rows as $p) {
        $fulfillment = is_array($p->fulfillment_payload) ? $p->fulfillment_payload : [];
        if (($fulfillment['synced'] ?? false) === true) continue;

        $esimId = trim((string) ($fulfillment['esim_id'] ?? ''));
        if ($esimId === '') continue;

        $isAiralo = isset($fulfillment['airalo']) || !isset($fulfillment['gloesim']);
        $service = $isAiralo ? $airalo : $gloEsim;

        $details = $service->getEsimDetails($esimId);
        if (is_array($details) && (($details['ok'] ?? false) === true)) {
            $fulfillment = array_merge($fulfillment, [
                'ok' => true,
                'synced' => true,
                'attempted_at' => now()->toIso8601String(),
                'esim_id' => (string) ($details['esim_id'] ?? $esimId),
                'iccid' => (string) ($details['iccid'] ?? ($fulfillment['iccid'] ?? '')),
                'number' => $details['number'] ?? null,
                'esim_status' => (string) ($details['esim_status'] ?? ''),
                'smdp_address' => (string) ($details['smdp_address'] ?? ''),
                'activation_code' => (string) ($details['activation_code'] ?? ''),
                'lpa' => (string) ($details['lpa'] ?? ''),
                'puk_code' => (string) ($details['puk_code'] ?? ''),
                'qr_code_url' => (string) ($details['qr_code_url'] ?? ''),
                'direct_installation_link_ios' => (string) ($details['direct_installation_link_ios'] ?? ''),
                'direct_installation_link_android' => (string) ($details['direct_installation_link_android'] ?? ''),
            ]);

            if ($isAiralo) {
                $fulfillment['airalo'] = $details['raw'] ?? $details;
            } else {
                $fulfillment['gloesim'] = $details['gloesim_item'] ?? $details;
            }

            $p->fulfillment_payload = $fulfillment;
            $p->save();
            $updated++;
        }
    }

    return response()->json(['ok' => true, 'updated' => $updated]);
})->name('myesims.sync');

Route::post('/api/cryptomus/webhook', function (Request $request, AiraloService $airalo, QrCodeService $qr) {
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
        ? $airalo->topupEsim($topupEsimId, (string) $payment->bundle_id, 'cm_'.$orderId, $email)
        : $airalo->fulfillEsim((string) $payment->bundle_id, 'cm_'.$orderId, $email, (string) $payment->package_type);
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

    $details = $esimId !== '' ? $airalo->getEsimDetails($esimId) : null;
    $detailsOk = is_array($details) && (($details['ok'] ?? false) === true);

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
        'airalo' => $detailsOk ? ($details['raw'] ?? $details) : ($fulfillment['raw'] ?? $fulfillment),
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

Route::middleware(['auth', 'throttle:30,1'])->post('/api/cryptomus/status', function (Request $request, CryptomusService $cryptomus, AiraloService $airalo, QrCodeService $qr) {
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
                ? $airalo->getEsimDetails($pendingEsimId)
                : ($topupEsimId !== ''
                    ? $airalo->topupEsim($topupEsimId, (string) $payment->bundle_id, 'cm_'.$orderId, $email)
                    : $airalo->fulfillEsim((string) $payment->bundle_id, 'cm_'.$orderId, $email, (string) $payment->package_type));

            if (($fulfillment['ok'] ?? false) === true) {
                $esimId = trim((string) ($fulfillment['esim_id'] ?? ''));
                $iccid = (string) ($fulfillment['iccid'] ?? '');

                $details = $esimId !== '' ? $airalo->getEsimDetails($esimId) : null;
                $detailsOk = is_array($details) && (($details['ok'] ?? false) === true);

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
                    'airalo' => $detailsOk ? ($details['raw'] ?? $details) : ($fulfillment['raw'] ?? $fulfillment),
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

Route::get('/dashboard', function (MyEsimService $myEsims, QrCodeService $qr) {
    return view('dashboard', [
        'initialMyEsims' => $myEsims->listForUser((int) Auth::id(), 'valid', 1, 10, $qr),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
