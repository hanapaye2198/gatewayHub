<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GatewayHub Platform Transaction Fee
    |--------------------------------------------------------------------------
    | Applied when webhook confirms successful payment. Formula:
    |   gatewayhub_platform_fee = gross * 1.5%
    |   net_amount = gross - gatewayhub_platform_fee
    */

    'fees' => [
        'percentage' => 1.5,
    ],

];
