<?php

return [
    // false = the full Content-Security-Policy is only reported (Report-Only) and violations are logged;
    // true = it is enforced. Flip after the logged violations are clean (see launch:check).
    'csp_enforce' => (bool) env('CSP_ENFORCE', false),

    // Cloudflare Turnstile (free CAPTCHA). Leave the secret empty to disable (local development).
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    // Rate limits. Per-IP numbers are per minute unless noted. IPv6 clients are grouped by /64.
    'limits' => [
        'signup_ip_per_min' => (int) env('LIMIT_SIGNUP_IP_PER_MIN', 5),
        'signup_ip_per_hour' => (int) env('LIMIT_SIGNUP_IP_PER_HOUR', 20),
        'signup_email_per_hour' => (int) env('LIMIT_SIGNUP_EMAIL_PER_HOUR', 3),
        // Circuit breaker protecting email costs and the database: all sign-ups site-wide per minute.
        'signup_global_per_min' => (int) env('LIMIT_SIGNUP_GLOBAL_PER_MIN', 200),
        // Very early, session-free guard for login/register/reset pages and posts (per client per minute).
        'early_post_per_min' => (int) env('LIMIT_EARLY_POST_PER_MIN', 40),
        'early_page_per_min' => (int) env('LIMIT_EARLY_PAGE_PER_MIN', 120),
        'login_ip_per_min' => (int) env('LIMIT_LOGIN_IP_PER_MIN', 15),
        'login_email_per_hour' => (int) env('LIMIT_LOGIN_EMAIL_PER_HOUR', 30),
        'reset_ip_per_min' => (int) env('LIMIT_RESET_IP_PER_MIN', 5),
        'reset_email_per_hour' => (int) env('LIMIT_RESET_EMAIL_PER_HOUR', 3),
        'reset_global_per_min' => (int) env('LIMIT_RESET_GLOBAL_PER_MIN', 300),
    ],
];
