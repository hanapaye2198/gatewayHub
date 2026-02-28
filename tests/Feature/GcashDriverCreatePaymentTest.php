<?php

namespace Tests\Feature;

use App\Services\Gateways\Drivers\GcashDriver;
use App\Services\Gateways\Exceptions\GatewayException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GcashDriverCreatePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_payment_returns_redirect_url_in_native_direct_mode(): void
    {
        config(['app.url' => 'http://gatewayhub.test']);

        Http::fake([
            'api.native-gcash.test/*' => Http::response([
                'paymentId' => 'gcash-payment-123',
                'redirectUrl' => 'https://gcash.com/checkout/abc',
            ], 200),
        ]);

        $driver = new GcashDriver([
            'provider_mode' => 'native_direct',
            'client_id' => 'gcash-client-id',
            'client_secret' => 'gcash-client-secret',
            'api_base_url' => 'https://api.native-gcash.test',
            'merchant_id' => 'merchant-001',
            'redirect_success_url' => 'https://merchant.example/success',
            'redirect_failure_url' => 'https://merchant.example/failure',
            'redirect_cancel_url' => 'https://merchant.example/cancel',
        ]);

        $result = $driver->createPayment([
            'amount' => 149.5,
            'currency' => 'PHP',
            'reference' => 'ORDER-GCASH-001',
        ]);

        $this->assertSame('gcash-payment-123', $result['external_payment_id']);
        $this->assertSame('gcash-payment-123', $result['provider_reference']);
        $this->assertSame('https://gcash.com/checkout/abc', $result['redirect_url']);
        $this->assertIsArray($result['raw']);
    }

    public function test_create_payment_throws_when_api_base_url_or_merchant_id_missing(): void
    {
        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('GCash native credentials are incomplete');

        $driver = new GcashDriver([
            'provider_mode' => 'native_direct',
            'client_id' => 'gcash-client-id',
            'client_secret' => 'gcash-client-secret',
        ]);

        $driver->createPayment([
            'amount' => 149.5,
            'currency' => 'PHP',
            'reference' => 'ORDER-GCASH-001',
        ]);
    }

    public function test_create_payment_throws_when_mode_is_legacy(): void
    {
        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('GCash is in legacy mode');

        $driver = new GcashDriver([
            'provider_mode' => 'legacy',
            'client_id' => 'gcash-client-id',
            'client_secret' => 'gcash-client-secret',
        ]);

        $driver->createPayment([
            'amount' => 10,
            'currency' => 'PHP',
            'reference' => 'ORDER-GCASH-LEGACY',
        ]);
    }

    public function test_create_payment_throws_when_redirect_url_missing(): void
    {
        Http::fake([
            'api.native-gcash.test/*' => Http::response([
                'paymentId' => 'gcash-payment-123',
            ], 200),
        ]);

        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('checkout redirect URL missing');

        $driver = new GcashDriver([
            'provider_mode' => 'native_direct',
            'client_id' => 'gcash-client-id',
            'client_secret' => 'gcash-client-secret',
            'api_base_url' => 'https://api.native-gcash.test',
            'merchant_id' => 'merchant-001',
            'redirect_success_url' => 'https://merchant.example/success',
            'redirect_failure_url' => 'https://merchant.example/failure',
            'redirect_cancel_url' => 'https://merchant.example/cancel',
        ]);

        $driver->createPayment([
            'amount' => 10,
            'currency' => 'PHP',
            'reference' => 'ORDER-GCASH-002',
        ]);
    }
}
