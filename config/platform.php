<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Transaction Fees (Option A: config-based)
    |--------------------------------------------------------------------------
    | Applied when webhook confirms successful payment. Formula:
    |   platform_fee = (gross * percentage/100) + fixed
    |   net_amount = gross - platform_fee
    */

    'fees' => [
        'percentage' => (float) env('PLATFORM_FEE_PERCENTAGE', 1.5),
        'fixed' => (float) env('PLATFORM_FEE_FIXED', 5),
    ],

];
