<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | wallet_settlement:
    | - false: Disable wallet settlement/disbursement flows (aligned with current phase).
    | - true: Enable legacy settlement controls and wallet pipeline.
    |
    */
    'features' => [
        'wallet_settlement' => filter_var(env('SUREPAY_WALLET_SETTLEMENT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    ],

];
