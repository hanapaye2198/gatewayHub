<?php

return [
    'base_url' => env('KEMOFI_SSO_BASE_URL', 'https://kemofi.com'),
    'shared_secret' => env('KEMOFI_SSO_SHARED_SECRET', ''),
    'timeout_seconds' => max(1, min(30, (int) env('KEMOFI_SSO_TIMEOUT_SECONDS', 10))),
];
