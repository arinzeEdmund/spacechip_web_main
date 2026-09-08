<?php

return [
    'min_deposit_usd' => env('WALLET_MIN_DEPOSIT_USD', 1),
    'max_deposit_usd' => env('WALLET_MAX_DEPOSIT_USD', 5000),
    'min_balance_minor' => env('WALLET_MIN_BALANCE_MINOR', 0),
];
