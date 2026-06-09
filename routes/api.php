<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API Route Index
|--------------------------------------------------------------------------
|
| The operational service endpoints are implemented in routes/web.php because
| this project originally exposed them there. bootstrap/app.php registers this
| file and exempts /api/* from CSRF so mobile clients can use the same stable
| /api paths with Sanctum bearer tokens. Crypto/Cryptomus is intentionally not
| part of the mobile contract.
|
*/

Route::get('/mobile/health', function (Request $request) {
    return response()->json([
        'ok' => true,
        'service' => 'spacechip-mobile-api',
        'version' => '1.0',
        'crypto_payments_enabled' => false,
        'auth' => [
            'type' => 'Bearer',
            'header' => 'Authorization: Bearer <token>',
        ],
    ]);
})->name('api.mobile.health');

Route::get('/mobile/endpoints', function () {
    return response()->json([
        'ok' => true,
        'base_url' => url('/api'),
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer <token>',
        ],
        'excluded' => [
            'crypto_payments',
            'cryptomus_invoice',
            'cryptomus_status',
            'cryptomus_webhook',
        ],
        'groups' => [
            'auth' => [
                ['method' => 'POST', 'path' => '/auth/register', 'auth' => false, 'body' => ['name', 'email', 'password', 'device_name?']],
                ['method' => 'POST', 'path' => '/auth/login', 'auth' => false, 'body' => ['email', 'password', 'device_name?']],
                ['method' => 'GET', 'path' => '/auth/me', 'auth' => true],
                ['method' => 'POST', 'path' => '/auth/logout', 'auth' => true],
                ['method' => 'POST', 'path' => '/auth/resend-verification', 'auth' => true],
                ['method' => 'POST', 'path' => '/auth/forgot-password', 'auth' => false, 'body' => ['email']],
            ],
            'catalog' => [
                ['method' => 'GET', 'path' => '/landing', 'auth' => false],
                ['method' => 'GET', 'path' => '/allassets', 'auth' => false, 'query' => ['tab?', 'page?', 'per_page?', 'q?']],
                ['method' => 'GET', 'path' => '/assets/{type}/{id}/bundles', 'auth' => false, 'query' => ['package_type?']],
            ],
            'esims' => [
                ['method' => 'POST', 'path' => '/paystack/initialize', 'auth' => true, 'body' => ['type', 'id', 'bundle', 'package_type?', 'currency?', 'topup_esim_id?']],
                ['method' => 'POST', 'path' => '/paystack/verify', 'auth' => true, 'body' => ['reference']],
                ['method' => 'POST', 'path' => '/paystack/status', 'auth' => true, 'body' => ['reference']],
                ['method' => 'GET', 'path' => '/my-esims', 'auth' => true, 'query' => ['filter?', 'page?', 'per_page?']],
                ['method' => 'POST', 'path' => '/my-esims/sync', 'auth' => true],
            ],
            'wallet' => [
                ['method' => 'GET', 'path' => '/wallet/balance', 'auth' => true, 'verified' => true],
                ['method' => 'GET', 'path' => '/wallet/transactions', 'auth' => true, 'verified' => true],
                ['method' => 'POST', 'path' => '/wallet/deposit/paystack/initialize', 'auth' => true, 'verified' => true, 'body' => ['amount_usd']],
                ['method' => 'POST', 'path' => '/wallet/deposit/paystack/verify', 'auth' => true, 'verified' => true, 'body' => ['reference']],
                ['method' => 'POST', 'path' => '/wallet/pay/esim', 'auth' => true, 'verified' => true, 'body' => ['type', 'id', 'bundle', 'package_type?', 'topup_esim_id?']],
                ['method' => 'POST', 'path' => '/wallet/pay/virtual-number', 'auth' => true, 'verified' => true, 'body' => ['product_id', 'phone_number', 'number_type?']],
            ],
            'virtual_numbers' => [
                ['method' => 'GET', 'path' => '/virtual-numbers/countries', 'auth' => true, 'verified' => true, 'query' => ['page?', 'per_page?', 'q?', 'context?']],
                ['method' => 'GET', 'path' => '/virtual-numbers/country/{country}', 'auth' => true, 'verified' => true],
                ['method' => 'GET', 'path' => '/virtual-numbers/available', 'auth' => true, 'verified' => true, 'query' => ['product_id', 'limit?', 'page?']],
                ['method' => 'GET', 'path' => '/virtual-numbers/my', 'auth' => true, 'verified' => true],
                ['method' => 'POST', 'path' => '/virtual-numbers/paystack/initialize', 'auth' => true, 'verified' => true, 'body' => ['product_id', 'phone_number', 'number_type?']],
                ['method' => 'POST', 'path' => '/virtual-numbers/paystack/verify', 'auth' => true, 'verified' => true, 'body' => ['reference']],
                ['method' => 'POST', 'path' => '/virtual-numbers/{subscription}/cancel', 'auth' => true, 'verified' => true],
                ['method' => 'GET', 'path' => '/virtual-numbers/{subscription}/messages', 'auth' => true, 'verified' => true, 'query' => ['per_page?']],
                ['method' => 'POST', 'path' => '/virtual-numbers/{subscription}/messages/send', 'auth' => true, 'verified' => true, 'body' => ['to', 'body?', 'media_urls?']],
                ['method' => 'GET', 'path' => '/virtual-numbers/{subscription}/settings', 'auth' => true, 'verified' => true],
                ['method' => 'POST', 'path' => '/virtual-numbers/{subscription}/settings', 'auth' => true, 'verified' => true, 'body' => ['forward_to_email?', 'forward_to_phone?']],
                ['method' => 'GET', 'path' => '/virtual-numbers/messages/{message}/media/{index}', 'auth' => true, 'verified' => true],
                ['method' => 'GET', 'path' => '/virtual-numbers/messages/{message}/recording', 'auth' => true, 'verified' => true],
            ],
            'social_numbers' => [
                ['method' => 'GET', 'path' => '/social-numbers/profile', 'auth' => true, 'verified' => true],
                ['method' => 'GET', 'path' => '/social-numbers/apps', 'auth' => true, 'verified' => true],
                ['method' => 'GET', 'path' => '/social-numbers/countries', 'auth' => true, 'verified' => true],
                ['method' => 'GET', 'path' => '/social-numbers/prices', 'auth' => true, 'verified' => true, 'query' => ['country', 'product']],
                ['method' => 'POST', 'path' => '/social-numbers/buy', 'auth' => true, 'verified' => true, 'body' => ['product', 'country', 'operator?']],
                ['method' => 'GET', 'path' => '/social-numbers/check/{id}', 'auth' => true, 'verified' => true],
                ['method' => 'POST', 'path' => '/social-numbers/orders/{id}/finish', 'auth' => true, 'verified' => true],
                ['method' => 'POST', 'path' => '/social-numbers/orders/{id}/cancel', 'auth' => true, 'verified' => true],
                ['method' => 'POST', 'path' => '/social-numbers/orders/{id}/ban', 'auth' => true, 'verified' => true],
                ['method' => 'POST', 'path' => '/social-numbers/orders/{id}/try-another', 'auth' => true, 'verified' => true],
                ['method' => 'GET', 'path' => '/social-numbers/orders', 'auth' => true, 'verified' => true, 'query' => ['limit?', 'offset?']],
            ],
            'social_rentals' => [
                ['method' => 'GET', 'path' => '/social-rentals/apps', 'auth' => true, 'verified' => true],
                ['method' => 'GET', 'path' => '/social-rentals/countries', 'auth' => true, 'verified' => true],
                ['method' => 'GET', 'path' => '/social-rentals/quote', 'auth' => true, 'verified' => true, 'query' => ['product', 'country', 'provider?']],
                ['method' => 'POST', 'path' => '/social-rentals/buy', 'auth' => true, 'verified' => true, 'body' => ['product', 'country', 'provider?']],
                ['method' => 'POST', 'path' => '/social-rentals/{rental}/activate', 'auth' => true, 'verified' => true],
                ['method' => 'GET', 'path' => '/social-rentals/{rental}/sms', 'auth' => true, 'verified' => true],
                ['method' => 'POST', 'path' => '/social-rentals/{rental}/cancel', 'auth' => true, 'verified' => true],
                ['method' => 'GET', 'path' => '/social-rentals', 'auth' => true, 'verified' => true, 'query' => ['limit?', 'offset?']],
            ],
            'webhooks' => [
                ['method' => 'POST', 'path' => '/airalo/webhook', 'auth' => false, 'provider_signed' => true],
                ['method' => 'POST', 'path' => '/twilio/sms', 'auth' => false, 'provider_signed' => true],
                ['method' => 'POST', 'path' => '/twilio/voice', 'auth' => false, 'provider_signed' => true],
                ['method' => 'POST', 'path' => '/twilio/voice/recording', 'auth' => false, 'provider_signed' => true],
            ],
        ],
    ]);
})->name('api.mobile.endpoints');
