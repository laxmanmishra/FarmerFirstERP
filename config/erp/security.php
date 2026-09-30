<?php

return [
    /*
    | Consecutive failed logins before the account is locked (SRS v6.1 §2).
    */
    'max_failed_logins' => (int) env('ERP_MAX_FAILED_LOGINS', 5),

    'lockout_minutes' => (int) env('ERP_LOCKOUT_MINUTES', 15),

    /*
    | Personal access tokens issued to API/mobile clients expire after this many minutes.
    */
    'api_token_expiry_minutes' => (int) env('ERP_API_TOKEN_EXPIRY_MINUTES', 60 * 24 * 7),
];
