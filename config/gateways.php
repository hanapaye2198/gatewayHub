<?php

use App\Services\Gateways\Drivers\PayMongoDriver;

return [

    /*
    |--------------------------------------------------------------------------
    | Gateway capabilities (driver class => capability key)
    |--------------------------------------------------------------------------
    | PayMongo QR Ph displays a QR image. PayMongo wallets redirect.
    */

    'capabilities' => [
        PayMongoDriver::class => 'redirect',
    ],

    'capabilities_by_code' => [
        'qrph' => 'qr',
    ],

];
