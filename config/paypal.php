<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PayPal Webhook
    |--------------------------------------------------------------------------
    |
    | client_id, client_secret: From PayPal Developer Portal (REST API credentials).
    | webhook_id: The webhook ID from your PayPal app webhook configuration.
    | mode: sandbox or live. Controls API base URL.
    | allow_dev_bypass: When true, skips signature verification (local/testing only).
    | max_age: Replay protection window in seconds.
    |
    */

    'webhook' => [
        'client_id' => env('PAYPAL_CLIENT_ID', ''),
        'client_secret' => env('PAYPAL_CLIENT_SECRET', ''),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID', ''),
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'allow_dev_bypass' => filter_var(env('PAYPAL_WEBHOOK_ALLOW_DEV_BYPASS', false), FILTER_VALIDATE_BOOLEAN),
        'max_age' => (int) env('PAYPAL_WEBHOOK_MAX_AGE', 300),
    ],

];
