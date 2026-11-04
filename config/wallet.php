<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Wallet Limits
    |--------------------------------------------------------------------------
    |
    | The wallet is denominated in USD. Deposit limits are in whole dollars;
    | the minimum balance a debit must leave behind is in cents.
    |
    */

    'min_deposit_usd' => env('WALLET_MIN_DEPOSIT_USD', 1),

    'max_deposit_usd' => env('WALLET_MAX_DEPOSIT_USD', 5000),

    'min_balance_minor' => env('WALLET_MIN_BALANCE_MINOR', 0),

];
