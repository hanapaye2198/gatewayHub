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

    // GCash: legacy (Coins QR) or native_direct with own credentials.
    'gcash' => [
        ['key' => 'provider_mode', 'label' => 'Provider Mode', 'type' => 'select', 'required' => true, 'masked' => false, 'options' => ['legacy' => 'Legacy (Coins QR)', 'native_direct' => 'Native Direct']],
        ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => true, 'masked' => false],
        ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true, 'masked' => true],
        ['key' => 'api_base_url', 'label' => 'API Base URL', 'type' => 'text', 'required' => false, 'masked' => false],
        ['key' => 'merchant_id', 'label' => 'Merchant ID', 'type' => 'text', 'required' => false, 'masked' => false],
        ['key' => 'webhook_key', 'label' => 'Webhook Key', 'type' => 'password', 'required' => false, 'masked' => true],
    ],

    // Maya: legacy (Coins) or native_checkout with own credentials.
    'maya' => [
        ['key' => 'provider_mode', 'label' => 'Provider Mode', 'type' => 'select', 'required' => true, 'masked' => false, 'options' => ['legacy' => 'Legacy (Coins)', 'native_checkout' => 'Native Checkout']],
        ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => true, 'masked' => false],
        ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true, 'masked' => true],
        ['key' => 'api_base', 'label' => 'Environment', 'type' => 'select', 'required' => false, 'masked' => false, 'options' => ['sandbox' => 'Sandbox', 'prod' => 'Production']],
        ['key' => 'webhook_key', 'label' => 'Webhook Key', 'type' => 'password', 'required' => false, 'masked' => true],
    ],

    // PayPal platform credentials.
    'paypal' => [
        ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => true, 'masked' => false],
        ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true, 'masked' => true],
        ['key' => 'api_base', 'label' => 'Environment', 'type' => 'select', 'required' => false, 'masked' => false, 'options' => ['sandbox' => 'Sandbox', 'live' => 'Live']],
        ['key' => 'webhook_id', 'label' => 'Webhook ID', 'type' => 'text', 'required' => false, 'masked' => false],
    ],

    // QRPH uses same platform credentials as Coins (Coins dynamic QR).
    'qrph' => [
        ['key' => 'client_id', 'label' => 'Client ID / API Key', 'type' => 'text', 'required' => true, 'masked' => false],
        ['key' => 'client_secret', 'label' => 'Client Secret / API Secret', 'type' => 'password', 'required' => true, 'masked' => true],
        ['key' => 'api_base', 'label' => 'Environment', 'type' => 'select', 'required' => true, 'masked' => false, 'options' => ['sandbox' => 'Sandbox', 'prod' => 'Production']],
        ['key' => 'webhook_secret', 'label' => 'Webhook Secret', 'type' => 'password', 'required' => false, 'masked' => true],
    ],

    // PayQRPH: same platform credentials as Coins / QRPH (Coins dynamic QR).
    'payqrph' => [
        ['key' => 'client_id', 'label' => 'Client ID / API Key', 'type' => 'text', 'required' => true, 'masked' => false],
        ['key' => 'client_secret', 'label' => 'Client Secret / API Secret', 'type' => 'password', 'required' => true, 'masked' => true],
        ['key' => 'api_base', 'label' => 'Environment', 'type' => 'select', 'required' => true, 'masked' => false, 'options' => ['sandbox' => 'Sandbox', 'prod' => 'Production']],
        ['key' => 'webhook_secret', 'label' => 'Webhook Secret', 'type' => 'password', 'required' => false, 'masked' => true],
    ],

];
