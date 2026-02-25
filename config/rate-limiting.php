<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Rate Limits
    |--------------------------------------------------------------------------
    |
    | Rate limits for the public payment API. Applied per authenticated merchant.
    |
    */

    'api' => [
        'max_attempts' => (int) env('RATE_LIMIT_API_MAX_ATTEMPTS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Rate Limits
    |--------------------------------------------------------------------------
    |
    | Rate limits for webhook endpoints. Higher threshold to support retries
    | and bursts. Applied per IP. Protects against abuse and retry storms.
    |
    */

    'webhooks' => [
        'max_attempts' => (int) env('RATE_LIMIT_WEBHOOKS_MAX_ATTEMPTS', 200),
    ],

];
