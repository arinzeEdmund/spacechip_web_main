<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Virtual Number Defaults (Twilio)
    |--------------------------------------------------------------------------
    |
    | Used when a country has no product row yet and live Twilio pricing is
    | unavailable. Amounts are in minor units (cents).
    |
    */

    'default_currency' => env('VIRTUAL_NUMBER_DEFAULT_CURRENCY', 'USD'),

    'default_monthly_amount_minor' => env('VIRTUAL_NUMBER_DEFAULT_MONTHLY_AMOUNT_MINOR', 500),

    'usd_to_ngn_rate' => env('VIRTUAL_NUMBER_USD_TO_NGN_RATE', 0),

];
