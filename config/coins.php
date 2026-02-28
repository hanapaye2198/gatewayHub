<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Coins.ph Dynamic QR API (Fiat)
    |--------------------------------------------------------------------------
    |
    | All values from environment. Do not hardcode keys in code.
    | COINS_BASE_URL, COINS_API_KEY, COINS_SECRET_KEY, COINS_SOURCE.
    |
    */
    'base_url' => env('COINS_BASE_URL') ? rtrim(env('COINS_BASE_URL'), '/') : '',
    'api_key' => env('COINS_API_KEY', ''),
    'secret_key' => env('COINS_SECRET_KEY', ''),
    'source' => env('COINS_SOURCE', 'GATEWAYHUB'),

    /*
    |--------------------------------------------------------------------------
    | Coins.ph Webhook
    |--------------------------------------------------------------------------
    |
    | Signature header name used by Coins.ph when sending webhook callbacks.
    | Example: X-COINS-SIGNATURE or Signature
    |
    | allow_dev_bypass: When true, skips signature verification (for local/testing only).
    | Default is false. Set COINS_WEBHOOK_ALLOW_DEV_BYPASS=true to enable.
    |
    | max_age: Maximum allowed webhook age in seconds for replay protection.
    | Rejects webhooks older than this or too far in the future. Default 300 (5 min).
    |
    */

    'webhook' => [
        'signature_header' => env('COINS_WEBHOOK_SIGNATURE_HEADER', 'X-COINS-SIGNATURE'),
        'secret' => env('COINS_WEBHOOK_SECRET', env('COINS_SECRET_KEY', '')),
        'timestamp_header' => env('COINS_WEBHOOK_TIMESTAMP_HEADER'),
        'allow_dev_bypass' => filter_var(env('COINS_WEBHOOK_ALLOW_DEV_BYPASS', false), FILTER_VALIDATE_BOOLEAN),
        'max_age' => (int) env('COINS_WEBHOOK_MAX_AGE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | SurePay Platform Gateway Credentials (Coins)
    |--------------------------------------------------------------------------
    |
    | Centralized gateway credentials managed by SurePay admin.
    | Merchants can only enable/disable gateway access; credentials stay here.
    |
    */
    'gateway' => [
        'client_id' => env('COINS_GATEWAY_CLIENT_ID', env('COINS_API_KEY', '')),
        'client_secret' => env('COINS_GATEWAY_CLIENT_SECRET', env('COINS_SECRET_KEY', '')),
        'api_base' => env('COINS_GATEWAY_API_BASE', 'sandbox'),
        'source' => env('COINS_SOURCE', 'GATEWAYHUB'),
    ],

];
