<?php

use App\Services\Gateways\Drivers\PayMongoDriver;
use App\Services\Gateways\Drivers\PaypalDriver;

return [

    /*
    |--------------------------------------------------------------------------
    | Gateway capabilities (driver class => capability key)
    |--------------------------------------------------------------------------
    | QR Ph displays a QR image. PayMongo e-wallets and PayPal redirect.
    */

    'capabilities' => [
        PayMongoDriver::class => 'redirect',
        PaypalDriver::class => 'redirect',
    ],

    'capabilities_by_code' => [
        'qrph' => 'qr',
    ],

];
