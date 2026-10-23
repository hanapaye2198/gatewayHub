<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform payment credential field definitions.
    | PayMongo credentials are shared by QR Ph and all supported e-wallets.
    |--------------------------------------------------------------------------
    */

    'paymongo' => [
        ['key' => 'secret_key', 'label' => 'PayMongo Secret API Key', 'type' => 'password', 'required' => true, 'masked' => true],
        ['key' => 'public_key', 'label' => 'PayMongo Public API Key', 'type' => 'password', 'required' => true, 'masked' => true],
        ['key' => 'webhook_secret', 'label' => 'PayMongo Webhook Secret', 'type' => 'password', 'required' => true, 'masked' => true],
    ],

    'gcash' => [],
    'maya' => [],
    'grab_pay' => [],
    'shopeepay' => [],
    'paypal' => [],
    'qrph' => [],

];
