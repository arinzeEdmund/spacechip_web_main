<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
        'key' => env('POSTMARK_TOKEN'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'airalo' => [
        'base_url' => env('AIRALO_BASE_URL', 'https://partners-api.airalo.com/v2'),
        'client_id' => env('AIRALO_CLIENT_ID'),
        'client_secret' => env('AIRALO_CLIENT_SECRET'),
        'currency' => env('AIRALO_CURRENCY', 'USD'),
        'sandbox' => env('AIRALO_SANDBOX', true),
    ],

    'gloesim' => [
        'base_url' => env('GLOESIM_BASE_URL', 'https://sandbox.gloesim.com/api'),
        'dealer_email' => env('GLOESIM_DEALER_EMAIL'),
        'dealer_password' => env('GLOESIM_DEALER_PASSWORD'),
        'currency' => env('GLOESIM_CURRENCY', 'USD'),
    ],

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'currency' => env('PAYSTACK_CURRENCY', 'NGN'),
        'owner_email' => env('PAYSTACK_OWNER_EMAIL'),
    ],

    'exchange_rates' => [
        'usd_latest_url' => env('EXCHANGE_RATE_USD_LATEST_URL', 'https://open.er-api.com/v6/latest/USD'),
        'cache_minutes' => env('EXCHANGE_RATE_CACHE_MINUTES', 60),
        'fallback_usd_to_ngn' => env('ESIM_USD_TO_NGN_RATE'),
        'esim_markup_multiplier' => env('ESIM_EXCHANGE_RATE_MARKUP_MULTIPLIER', 1),
    ],

    'cryptomus' => [
        'merchant' => env('CRYPTOMUS_MERCHANT'),
        'payment_key' => env('CRYPTOMUS_PAYMENT_KEY'),
        'api_url' => env('CRYPTOMUS_API_URL', 'https://api.cryptomus.com'),
        'verify_ssl' => env('CRYPTOMUS_VERIFY_SSL', true),
        'force_ipv4' => env('CRYPTOMUS_FORCE_IPV4', false),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'sms_webhook_url' => env('TWILIO_SMS_WEBHOOK_URL'),
        'voice_webhook_url' => env('TWILIO_VOICE_WEBHOOK_URL'),
    ],

    'fivesim' => [
        'base_url' => env('FIVESIM_BASE_URL', 'https://5sim.net/v1'),
        'token' => env('FIVESIM_TOKEN'),
    ],

    'smspva' => [
        'base_url' => env('SMSPVA_BASE_URL', 'https://smspva.com/priemnik.php'),
        'v2_base_url' => env('SMSPVA_V2_BASE_URL', 'https://api.smspva.com'),
        'rent_base_url' => env('SMSPVA_RENT_BASE_URL', 'https://smspva.io/api/rent.php'),
        'api_key' => env('SMSPVA_API_KEY'),
        'markup_multiplier' => env('SMSPVA_MARKUP_MULTIPLIER', 1.4),
        'minimum_sell_minor' => env('SMSPVA_MINIMUM_SELL_MINOR', 100),
        'rent_markup_multiplier' => env('SMSPVA_RENT_MARKUP_MULTIPLIER', 1.35),
        'rent_minimum_sell_minor' => env('SMSPVA_RENT_MINIMUM_SELL_MINOR', 500),
    ],

];
