<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | wallet_settlement enables the internal merchant wallet ledger and its API.
    | Payment receipts are credited net of the KemoPay platform fee. The internal
    | ledger does not itself send funds to an external bank or e-wallet.
    |
    */
    'features' => [
        'wallet_settlement' => filter_var(env('SUREPAY_WALLET_SETTLEMENT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    ],

];
