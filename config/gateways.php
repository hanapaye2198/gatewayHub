<?php

use App\Services\Gateways\Drivers\CoinsDriver;
use App\Services\Gateways\Drivers\GcashDriver;
use App\Services\Gateways\Drivers\MayaDriver;
use App\Services\Gateways\Drivers\PaypalDriver;
use App\Services\Gateways\Drivers\QrphDriver;

return [

    /*
    |--------------------------------------------------------------------------
    | Gateway capabilities (driver class => capability key)
    |--------------------------------------------------------------------------
    |
    | Keys: qr, redirect, api_only. Used so the frontend can react correctly
    | (e.g. show QR vs redirect). No database column; resolved from driver_class.
    | In the current model, customer-facing options are collected via Coins QR.
    |
    */

    'capabilities' => [
        CoinsDriver::class => 'qr',
        GcashDriver::class => 'qr',
        MayaDriver::class => 'qr',
        PaypalDriver::class => 'qr',
        QrphDriver::class => 'qr',
    ],

];
