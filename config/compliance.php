<?php

return [
    // Identity checks (KYC). When a customer's 30-day USD purchase volume reaches this amount they must be
    // approved by an admin before buying more. Empty/0 turns the gate off. Counsel should set the level per country.
    'kyc_threshold_usd' => env('KYC_THRESHOLD_USD') !== null && env('KYC_THRESHOLD_USD') !== '' ? (float) env('KYC_THRESHOLD_USD') : null,
    'kyc_window_days' => (int) env('KYC_WINDOW_DAYS', 30),
];
