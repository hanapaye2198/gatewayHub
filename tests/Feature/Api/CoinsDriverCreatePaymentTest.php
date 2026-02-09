<?php

namespace Tests\Feature\Api;

use App\Services\Gateways\Drivers\CoinsDriver;
use App\Services\Gateways\Exceptions\CoinsApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CoinsDriverCreatePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_payment_returns_normalized_response_with_qr_and_provider_reference(): void
    {
        Http::fake([
            '*.coins.ph/*' => Http::response([
                'code' => 0,
                'data' => [
                    'orderId' => 'coins-order-123',
                    'qrCode' => '00020126...',
                ],
            ], 200),
        ]);

        $driver = new CoinsDriver([
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'api_base' => 'sandbox',
        ]);

        $result = $driver->createPayment([
            'amount' => 100,
            'currency' => 'PHP',
            'reference' => 'ref-001',
        ]);

        $this->assertArrayHasKey('provider_reference', $result);
        $this->assertSame('coins-order-123', $result['provider_reference']);
        $this->assertArrayHasKey('qr_string', $result);
        $this->assertSame('00020126...', $result['qr_string']);
        $this->assertArrayHasKey('raw_response', $result);
        $this->assertIsArray($result['raw_response']);
        $this->assertSame('pending', $result['status']);
        $this->assertSame('coins-order-123', $result['reference_id']);
    }

    public function test_create_payment_accepts_api_key_and_api_secret_from_coins_portal(): void
    {
        Http::fake([
            '*.coins.ph/*' => Http::response([
                'code' => 0,
                'data' => [
                    'orderId' => 'portal-order-456',
                    'qrCode' => 'portal-qr...',
                ],
            ], 200),
        ]);

        $driver = new CoinsDriver([
            'api_key' => 'portal-api-key',
            'api_secret' => 'portal-api-secret',
            'api_base' => 'sandbox',
        ]);

        $result = $driver->createPayment([
            'amount' => 200,
            'currency' => 'PHP',
            'reference' => 'ref-portal',
        ]);

        $this->assertSame('portal-order-456', $result['provider_reference']);
        $this->assertSame('portal-qr...', $result['qr_string']);
        $this->assertSame('pending', $result['status']);
    }

    public function test_create_payment_prefers_client_id_over_api_key_when_both_present(): void
    {
        Http::fake([
            '*.coins.ph/*' => function ($request) {
                $this->assertSame('preferred-client', $request->header('X-COINS-APIKEY')[0] ?? null);

                return Http::response([
                    'code' => 0,
                    'data' => ['orderId' => 'ok', 'qrCode' => 'qr'],
                ], 200);
            },
        ]);

        $driver = new CoinsDriver([
            'client_id' => 'preferred-client',
            'api_key' => 'fallback-key',
            'client_secret' => 'preferred-secret',
            'api_secret' => 'fallback-secret',
            'api_base' => 'sandbox',
        ]);

        $driver->createPayment([
            'amount' => 1,
            'currency' => 'PHP',
            'reference' => 'ref',
        ]);
    }

    public function test_create_payment_throws_when_config_missing_credentials(): void
    {
        $this->expectException(CoinsApiException::class);
        $this->expectExceptionMessage('missing client_id or client_secret');

        $driver = new CoinsDriver([
            'client_id' => '',
            'client_secret' => 'secret',
            'api_base' => 'sandbox',
        ]);

        $driver->createPayment([
            'amount' => 100,
            'currency' => 'PHP',
            'reference' => 'ref-001',
        ]);
    }

    public function test_create_payment_throws_when_neither_client_nor_api_credentials_provided(): void
    {
        $this->expectException(CoinsApiException::class);
        $this->expectExceptionMessage('missing client_id or client_secret');

        $driver = new CoinsDriver([
            'api_base' => 'sandbox',
        ]);

        $driver->createPayment([
            'amount' => 100,
            'currency' => 'PHP',
            'reference' => 'ref-001',
        ]);
    }

    public function test_create_payment_throws_on_api_error_response(): void
    {
        Http::fake([
            '*.coins.ph/*' => Http::response([
                'code' => -1001,
                'msg' => 'Invalid API key',
            ], 401),
        ]);

        $this->expectException(CoinsApiException::class);
        $this->expectExceptionMessage('Coins.ph API error');

        $driver = new CoinsDriver([
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'api_base' => 'sandbox',
        ]);

        $driver->createPayment([
            'amount' => 100,
            'currency' => 'PHP',
            'reference' => 'ref-001',
        ]);
    }
}
