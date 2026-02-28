<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('surepay.features.wallet_settlement', false);

        config()->set('coins.gateway.client_id', 'test-coins-client-id');
        config()->set('coins.gateway.client_secret', 'test-coins-client-secret');
        config()->set('coins.gateway.api_base', 'sandbox');
        config()->set('coins.webhook.secret', 'test-coins-webhook-secret');

        config()->set('gcash.gateway.provider_mode', 'native_direct');
        config()->set('gcash.gateway.client_id', 'test-gcash-client-id');
        config()->set('gcash.gateway.client_secret', 'test-gcash-client-secret');
        config()->set('gcash.gateway.api_base_url', 'https://gcash.example');
        config()->set('gcash.gateway.merchant_id', 'surepay-gcash-merchant-id');
        config()->set('gcash.webhook.secret', 'test-gcash-webhook-secret');

        config()->set('maya.gateway.provider_mode', 'native_checkout');
        config()->set('maya.gateway.client_id', 'test-maya-client-id');
        config()->set('maya.gateway.client_secret', 'test-maya-client-secret');
        config()->set('maya.gateway.api_base', 'sandbox');
        config()->set('maya.webhook.secret', 'test-maya-webhook-secret');

        config()->set('paypal.gateway.client_id', 'test-paypal-client-id');
        config()->set('paypal.gateway.client_secret', 'test-paypal-client-secret');
        config()->set('paypal.gateway.webhook_id', 'test-paypal-webhook-id');
        config()->set('paypal.gateway.mode', 'sandbox');
    }
}
