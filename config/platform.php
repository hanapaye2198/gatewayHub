<?php

return [

    /*
    |--------------------------------------------------------------------------
    | KemoPay Platform Transaction Fee
    |--------------------------------------------------------------------------
    | The quoted amount is the base transaction amount.
    | The platform fee is added on top.
    | Customer total = base + platform fee.
    */

    'fees' => [
        'percentage' => 1.5,
    ],

];
