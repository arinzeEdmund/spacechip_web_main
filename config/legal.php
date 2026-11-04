<?php

/**
 * Legal facts shown on policy pages and recorded with consents. Fill the COMPANY_* values in .env, then have a
 * lawyer review every policy page before launch. Bump a version whenever the matching page changes materially:
 * new sign-ups record the version they accepted.
 */
return [
    'company_name' => env('COMPANY_NAME', 'Spacechip Ltd'),
    'company_number' => env('COMPANY_NUMBER'),
    'company_address' => env('COMPANY_ADDRESS'),
    'company_country' => env('COMPANY_COUNTRY'),
    'support_email' => env('SUPPORT_EMAIL', 'support@spacechipltd.com'),
    'privacy_email' => env('PRIVACY_EMAIL', 'privacy@spacechipltd.com'),
    'governing_law' => env('GOVERNING_LAW'),

    // Set LEGAL_REVIEWED=true only after a lawyer has signed off the policy pages; it hides the "draft" banner.
    'reviewed' => (bool) env('LEGAL_REVIEWED', false),

    'versions' => [
        'terms' => '2026-11-05',
        'privacy' => '2026-11-05',
        'refund' => '2026-11-05',
        'acceptable_use' => '2026-11-05',
        'withdrawal_waiver' => '2026-11-05',
    ],

    // Days to answer a data-subject request (GDPR/UK: one month; NDPA: 30 days).
    'privacy_request_days' => 30,

    // Countries we refuse to sell to or register (comprehensively sanctioned places). Counsel must confirm this list.
    // Detected from the edge header (CF-IPCountry / X-Vercel-IP-Country / CloudFront) when your host provides one.
    'blocked_countries' => array_filter(array_map('trim', explode(',', (string) env('BLOCKED_COUNTRIES', 'IR,KP,SY,CU')))),
    'country_block_enabled' => (bool) env('COUNTRY_BLOCK_ENABLED', true),
];
