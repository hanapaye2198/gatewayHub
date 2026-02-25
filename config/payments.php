<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    |
    | ISO 4217 currency codes accepted for payment creation. Gateways may
    | support a subset (e.g. Coins.ph: PHP).
    |
    */

    'currencies' => array_map('trim', explode(',', env('PAYMENT_CURRENCIES', 'PHP,USD'))),

];
