<?php

use App\Services\Gateways\Drivers\CoinsDriver;
use App\Services\Gateways\Drivers\PaypalDriver;

return [

    /*
    |--------------------------------------------------------------------------
    | Gateway capabilities (driver class => capability key)
    |--------------------------------------------------------------------------
    |
    | Keys: qr, redirect, api_only. Used so the frontend can react correctly
    | (e.g. show QR vs redirect). No database column; resolved from driver_class.
    | GCash / Maya: add when implemented (default is api_only).
    |
    */

    'capabilities' => [
        CoinsDriver::class => 'qr',
        PaypalDriver::class => 'redirect',
        // GcashDriver::class => 'api_only',
        // MayaDriver::class => 'api_only',
    ],

];
