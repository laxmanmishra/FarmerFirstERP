<?php

return [
    /*
    | Temperature bands by days until the expected purchase date (SRS §8).
    | A band covers days up to and including its value.
    */
    'temperature_max_days' => [
        'extra_hot' => 1,
        'hot' => 6,
        'warm' => 14,
    ],

    /*
    | Duplicate enquiry window (SRS §9): open enquiries for the same farmer/mobile
    | whose expected purchase date is within this many days are flagged.
    */
    'duplicate_window_days' => (int) env('ERP_DUPLICATE_WINDOW_DAYS', 90),

    /*
    | A telecaller's claim lapses after this many minutes without a call attempt,
    | returning the enquiry to the common queue (SRS §10).
    */
    'claim_timeout_minutes' => (int) env('ERP_CLAIM_TIMEOUT_MINUTES', 30),

    /*
    | Follow-up reminder lead time (minutes before due).
    */
    'follow_up_reminder_minutes' => (int) env('ERP_FOLLOW_UP_REMINDER_MINUTES', 60),

    'exchange_photo_max_kb' => 5120,

    'exchange_photo_max_count' => 6,
];
