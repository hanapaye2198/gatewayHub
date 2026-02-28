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
        'secret' => env('GCASH_WEBHOOK_SECRET', ''),
        'allow_dev_bypass' => filter_var(env('GCASH_WEBHOOK_ALLOW_DEV_BYPASS', false), FILTER_VALIDATE_BOOLEAN),
        'max_age' => (int) env('GCASH_WEBHOOK_MAX_AGE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | SurePay Platform Gateway Credentials (GCash)
    |--------------------------------------------------------------------------
    */
    'gateway' => [
        'provider_mode' => env('GCASH_PROVIDER_MODE', 'native_direct'),
        'client_id' => env('GCASH_CLIENT_ID', ''),
        'client_secret' => env('GCASH_CLIENT_SECRET', ''),
        'api_base_url' => env('GCASH_API_BASE_URL', ''),
        'merchant_id' => env('GCASH_MERCHANT_ID', ''),
        'redirect_success_url' => env('GCASH_REDIRECT_SUCCESS_URL'),
        'redirect_failure_url' => env('GCASH_REDIRECT_FAILURE_URL'),
        'redirect_cancel_url' => env('GCASH_REDIRECT_CANCEL_URL'),
        'webhook_key' => env('GCASH_WEBHOOK_SECRET', ''),
    ],

];
