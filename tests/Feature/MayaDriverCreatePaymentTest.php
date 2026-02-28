<?php

namespace Tests\Feature;

use App\Services\Gateways\Drivers\MayaDriver;
use App\Services\Gateways\Exceptions\GatewayException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MayaDriverCreatePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_payment_returns_redirect_url_in_native_checkout_mode(): void
    {
        config(['app.url' => 'http://gatewayhub.test']);

        Http::fake([
            'pg-sandbox.paymaya.com/*' => Http::response([
                'checkoutId' => 'maya-checkout-123',
                'redirectUrl' => 'https://payments.maya.ph/checkout/abc',
            ], 200),
        ]);

        $driver = new MayaDriver([
            'provider_mode' => 'native_checkout',
            'client_id' => 'maya-client-id',
            'client_secret' => 'maya-client-secret',
            'api_base' => 'sandbox',
        ]);

        $result = $driver->createPayment([
            'amount' => 99.99,
            'currency' => 'PHP',
            'reference' => 'ORDER-MAYA-001',
        ]);

        $this->assertSame('maya-checkout-123', $result['external_payment_id']);
        $this->assertSame('maya-checkout-123', $result['provider_reference']);
        $this->assertSame('https://payments.maya.ph/checkout/abc', $result['redirect_url']);
        $this->assertIsArray($result['raw']);
    }

    public function test_create_payment_throws_when_mode_is_legacy(): void
    {
        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('Maya is in legacy mode');

        $driver = new MayaDriver([
            'provider_mode' => 'legacy',
            'client_id' => 'maya-client-id',
            'client_secret' => 'maya-client-secret',
        ]);

        $driver->createPayment([
            'amount' => 10,
            'currency' => 'PHP',
            'reference' => 'ORDER-MAYA-LEGACY',
        ]);
    }

    public function test_create_payment_throws_when_redirect_url_missing(): void
    {
        Http::fake([
            'pg-sandbox.paymaya.com/*' => Http::response([
                'checkoutId' => 'maya-checkout-123',
            ], 200),
        ]);

        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('checkout redirect URL missing');

        $driver = new MayaDriver([
            'provider_mode' => 'native_checkout',
            'client_id' => 'maya-client-id',
            'client_secret' => 'maya-client-secret',
            'api_base' => 'sandbox',
        ]);

        $driver->createPayment([
            'amount' => 99.99,
            'currency' => 'PHP',
            'reference' => 'ORDER-MAYA-002',
        ]);
    }
}
