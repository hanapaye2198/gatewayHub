<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GatewayHub Platform Transaction Fee
    |--------------------------------------------------------------------------
    | The quoted amount is the base transaction amount.
    | Platform fee and the fixed convenience fee are added on top.
    | Customer total = base + platform fee + convenience fee.
    */

    'fees' => [
        'percentage' => 1.5,
        'convenience_fee' => (float) env('PLATFORM_CONVENIENCE_FEE', 20),
    ],

];
