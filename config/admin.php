<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Allow-list
    |--------------------------------------------------------------------------
    |
    | Comma-separated emails that are treated as admins in addition to users
    | whose `is_admin` flag is set. Parsed here (not with env() at runtime) so
    | it keeps working after `php artisan config:cache`.
    |
    */

    'emails' => array_values(array_filter(array_map(
        fn (string $email) => mb_strtolower(trim($email)),
        explode(',', (string) env('ADMIN_EMAILS', ''))
    ))),

];
