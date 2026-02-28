<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Per-gateway credential field definitions (for merchant config UI).
    | Keys: key (form name), label, type (text, password), required, masked (show **** when displaying saved).
    |--------------------------------------------------------------------------
    */

    'coins' => [
        ['key' => 'client_id', 'label' => 'Client ID / API Key', 'type' => 'text', 'required' => true, 'masked' => false],
        ['key' => 'client_secret', 'label' => 'Client Secret / API Secret', 'type' => 'password', 'required' => true, 'masked' => true],
        ['key' => 'api_base', 'label' => 'Environment', 'type' => 'select', 'required' => true, 'masked' => false, 'options' => ['sandbox' => 'Sandbox', 'prod' => 'Production']],
        ['key' => 'webhook_secret', 'label' => 'Webhook Secret', 'type' => 'password', 'required' => false, 'masked' => true],
    ],

    // Customer-facing selections are orchestrated via Coins dynamic QR in the current model.
    'gcash' => [],

    'maya' => [],

    'paypal' => [],

    'qrph' => [],

    'payqrph' => [],

];
