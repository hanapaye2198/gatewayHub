<?php

return [

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
        'allow_dev_bypass' => filter_var(env('COINS_WEBHOOK_ALLOW_DEV_BYPASS', false), FILTER_VALIDATE_BOOLEAN),
        'max_age' => (int) env('COINS_WEBHOOK_MAX_AGE', 300),
    ],

];
