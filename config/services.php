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
    ],

    'cryptomus' => [
        'merchant' => env('CRYPTOMUS_MERCHANT'),
        'payment_key' => env('CRYPTOMUS_PAYMENT_KEY'),
        'api_url' => env('CRYPTOMUS_API_URL', 'https://api.cryptomus.com'),
        'verify_ssl' => env('CRYPTOMUS_VERIFY_SSL', true),
        'force_ipv4' => env('CRYPTOMUS_FORCE_IPV4', false),
    ],

];
