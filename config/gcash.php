<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GCash Webhook
    |--------------------------------------------------------------------------
    |
    | allow_dev_bypass: When true, skips signature verification (for local/testing only).
    | max_age: Maximum allowed webhook age in seconds for replay protection.
    |
    */

    'webhook' => [
        'allow_dev_bypass' => filter_var(env('GCASH_WEBHOOK_ALLOW_DEV_BYPASS', false), FILTER_VALIDATE_BOOLEAN),
        'max_age' => (int) env('GCASH_WEBHOOK_MAX_AGE', 300),
    ],

];
