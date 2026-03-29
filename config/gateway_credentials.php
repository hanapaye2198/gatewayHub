<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform payment credential field definitions.
    | Only Coins.ph owns payment-processing credentials in the current model.
    | Customer-facing options like GCash, Maya, PayPal, and QRPH are
    | routed through Coins dynamic QR and do not have separate payment configs.
    |--------------------------------------------------------------------------
    */

    'coins' => [
        ['key' => 'client_id', 'label' => 'Client ID / API Key', 'type' => 'text', 'required' => true, 'masked' => false],
        ['key' => 'client_secret', 'label' => 'Client Secret / API Secret', 'type' => 'password', 'required' => true, 'masked' => true],
        ['key' => 'api_base', 'label' => 'Environment', 'type' => 'select', 'required' => true, 'masked' => false, 'options' => ['sandbox' => 'Sandbox', 'prod' => 'Production']],
        ['key' => 'webhook_secret', 'label' => 'Webhook Secret', 'type' => 'password', 'required' => false, 'masked' => true],
    ],

    'gcash' => [],
    'maya' => [],
    'paypal' => [],
    'qrph' => [],

];
