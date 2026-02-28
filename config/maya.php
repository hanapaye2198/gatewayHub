<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maya Webhook
    |--------------------------------------------------------------------------
    |
    | allow_dev_bypass: When true, skips signature verification (for local/testing only).
    | max_age: Maximum allowed webhook age in seconds for replay protection.
    |
    */

    'webhook' => [
        'secret' => env('MAYA_WEBHOOK_SECRET', ''),
        'allow_dev_bypass' => filter_var(env('MAYA_WEBHOOK_ALLOW_DEV_BYPASS', false), FILTER_VALIDATE_BOOLEAN),
        'max_age' => (int) env('MAYA_WEBHOOK_MAX_AGE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | SurePay Platform Gateway Credentials (Maya)
    |--------------------------------------------------------------------------
    */
    'gateway' => [
        'provider_mode' => env('MAYA_PROVIDER_MODE', 'native_checkout'),
        'client_id' => env('MAYA_CLIENT_ID', ''),
        'client_secret' => env('MAYA_CLIENT_SECRET', ''),
        'api_base' => env('MAYA_API_BASE', 'sandbox'),
        'redirect_success_url' => env('MAYA_REDIRECT_SUCCESS_URL'),
        'redirect_failure_url' => env('MAYA_REDIRECT_FAILURE_URL'),
        'redirect_cancel_url' => env('MAYA_REDIRECT_CANCEL_URL'),
        'webhook_key' => env('MAYA_WEBHOOK_SECRET', ''),
    ],

];
