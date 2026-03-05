<?php

namespace Tests\Feature\Api;

use App\Services\Coins\CoinsSignatureService;
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
            'api.9001.pl-qa.coinsxyz.me/*' => Http::response([
                'status' => 0,
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

        $this->assertArrayHasKey('external_payment_id', $result);
        $this->assertSame('coins-order-123', $result['external_payment_id']);
        $this->assertArrayHasKey('qr_data', $result);
        $this->assertSame('00020126...', $result['qr_data']);
        $this->assertArrayHasKey('raw', $result);
        $this->assertIsArray($result['raw']);
        $this->assertArrayHasKey('provider_reference', $result);
        $this->assertSame('coins-order-123', $result['provider_reference']);
        $this->assertArrayHasKey('qr_string', $result);
        $this->assertSame('00020126...', $result['qr_string']);
    }

    public function test_create_payment_accepts_api_key_and_api_secret_from_coins_portal(): void
    {
        Http::fake([
            'api.9001.pl-qa.coinsxyz.me/*' => Http::response([
                'status' => 0,
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

        $this->assertSame('portal-order-456', $result['external_payment_id']);
        $this->assertSame('portal-qr...', $result['qr_data']);
        $this->assertSame('portal-order-456', $result['provider_reference']);
    }

    public function test_create_payment_prefers_client_id_over_api_key_when_both_present(): void
    {
        Http::fake([
            'api.9001.pl-qa.coinsxyz.me/*' => function ($request) {
                $this->assertSame('preferred-client', $request->header('X-COINS-APIKEY')[0] ?? null);
                $this->assertNotEmpty($request->header('Timestamp'));
                $this->assertNotEmpty($request->header('Signature'));
                $this->assertSame('application/json', $request->header('Content-Type')[0] ?? null);
                $body = json_decode((string) $request->body(), true) ?? [];
                $this->assertArrayHasKey('requestId', $body);
                $this->assertArrayHasKey('amount', $body);
                $this->assertArrayNotHasKey('signature', $body);
                $this->assertArrayNotHasKey('timestamp', $body);

                return Http::response([
                    'status' => 0,
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

    public function test_create_payment_trims_credentials_and_uses_configured_source(): void
    {
        Http::fake([
            'api.9001.pl-qa.coinsxyz.me/*' => function ($request) {
                $this->assertSame('trimmed-client', $request->header('X-COINS-APIKEY')[0] ?? null);
                $body = json_decode((string) $request->body(), true) ?? [];
                $this->assertSame('SUREPAY-PROD', $body['source'] ?? null);
                $this->assertNotEmpty($request->header('Signature'));
                $this->assertNotEmpty($request->header('Timestamp'));

                return Http::response([
                    'status' => 0,
                    'data' => ['orderId' => 'trimmed-ord', 'qrCode' => 'trimmed-qr'],
                ], 200);
            },
        ]);

        $driver = new CoinsDriver([
            'client_id' => '  trimmed-client  ',
            'client_secret' => '  trimmed-secret  ',
            'api_base' => ' sandbox ',
            'source' => '  SUREPAY-PROD  ',
        ]);

        $result = $driver->createPayment([
            'amount' => 10,
            'currency' => 'PHP',
            'reference' => 'ref-trimmed',
        ]);

        $this->assertSame('trimmed-ord', $result['external_payment_id']);
        $this->assertSame('trimmed-qr', $result['qr_data']);
    }

    public function test_create_payment_retries_with_unsorted_signature_when_signature_is_rejected(): void
    {
        $requestCount = 0;
        $signatureService = new CoinsSignatureService;

        Http::fake([
            'api.9001.pl-qa.coinsxyz.me/*' => function ($request) use (&$requestCount, $signatureService) {
                $requestCount++;

                $timestamp = (string) ($request->header('Timestamp')[0] ?? '');
                $signature = (string) ($request->header('Signature')[0] ?? '');
                $body = json_decode((string) $request->body(), true) ?? [];

                $expected = $signatureService->signForFiatRequest(
                    $body,
                    $timestamp,
                    'retry-secret',
                    false,
                    $requestCount === 1
                );

                $this->assertNotSame('', $timestamp);
                $this->assertSame($expected['signature'], $signature);

                if ($requestCount === 1) {
                    return Http::response([
                        'status' => -1022,
                        'msg' => 'Signature for this request is not valid.',
                    ], 200);
                }

                return Http::response([
                    'status' => 0,
                    'data' => [
                        'orderId' => 'retry-order-123',
                        'qrCode' => 'retry-qr-123',
                    ],
                ], 200);
            },
        ]);

        $driver = new CoinsDriver([
            'client_id' => 'retry-client',
            'client_secret' => 'retry-secret',
            'api_base' => 'sandbox',
        ]);

        $result = $driver->createPayment([
            'amount' => 250,
            'currency' => 'PHP',
            'reference' => 'retry-ref-001',
        ]);

        $this->assertSame(2, $requestCount);
        $this->assertSame('retry-order-123', $result['external_payment_id']);
        $this->assertSame('retry-qr-123', $result['qr_data']);
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
            'api.9001.pl-qa.coinsxyz.me/*' => Http::response([
                'status' => -1001,
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

    public function test_create_payment_throws_on_negative_error_code_with_http_200(): void
    {
        Http::fake([
            'api.9001.pl-qa.coinsxyz.me/*' => Http::response([
                'status' => -1022,
                'msg' => 'Signature for this request is not valid.',
            ], 200),
        ]);

        $this->expectException(CoinsApiException::class);
        $this->expectExceptionMessage('Signature for this request is not valid');

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
