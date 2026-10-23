<?php

return [
    'gateway' => [
        'secret_key' => env('PAYMONGO_SECRET_KEY', ''),
        'public_key' => env('PAYMONGO_PUBLIC_KEY', ''),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET', ''),
        'expiry_seconds' => (int) env('PAYMONGO_QRPH_EXPIRY_SECONDS', 1800),
        'shopeepay_expiry_seconds' => (int) env('PAYMONGO_SHOPEEPAY_EXPIRY_SECONDS', 1200),
    ],

    'webhook' => [
        'max_age' => (int) env('PAYMONGO_WEBHOOK_MAX_AGE', 300),
    ],

    'status_sync' => [
        'fallback_enabled' => filter_var(env('PAYMONGO_STATUS_SYNC_FALLBACK_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'payouts' => [
        'source_account_number' => env('PAYMONGO_PAYOUT_SOURCE_ACCOUNT_NUMBER', ''),
        'source_account_name' => env('PAYMONGO_PAYOUT_SOURCE_ACCOUNT_NAME', ''),
        'callback_url' => env('PAYMONGO_PAYOUT_CALLBACK_URL', ''),
    ],
];
