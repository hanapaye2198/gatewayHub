<?php

namespace Tests\Feature;

use App\Models\CoinsTransaction;
use App\Services\Coins\CoinsGenerateQrSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CoinsQrGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'coins.auth.generate_qr.strategy' => 'auto',
            'coins.auth.generate_qr.timestamp_unit' => 'milliseconds',
            'coins.auth.generate_qr.signature_encoding' => 'hex_lower',
            'coins.auth.generate_qr.max_attempts' => 4,
        ]);
    }

    public function test_generate_qr_page_loads(): void
    {
        $response = $this->get(route('coins.qr'));

        $response->assertStatus(200);
        $response->assertViewIs('coins.qr');
    }

    public function test_generate_qr_validates_amount_required(): void
    {
        $response = $this->postJson(route('coins.generate-qr'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('amount');
    }

    public function test_generate_qr_validates_amount_min_one(): void
    {
        $response = $this->postJson(route('coins.generate-qr'), ['amount' => 0.5]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('amount');
    }

    public function test_generate_qr_calls_api_saves_transaction_and_returns_qr(): void
    {
        config([
            'coins.base_url' => 'https://api.9001.pl-qa.coinsxyz.me',
            'coins.api_key' => 'test-key',
            'coins.secret_key' => 'test-secret',
        ]);
        Http::fake([
            'api.9001.pl-qa.coinsxyz.me/*' => Http::response([
                'status' => 0,
                'data' => [
                    'orderId' => 'coins-order-123',
                    'qrCode' => '00020126...qr-payload',
                ],
            ], 200),
        ]);

        $response = $this->postJson(route('coins.generate-qr'), ['amount' => 100]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'PENDING',
            'amount' => 100,
            'currency' => 'PHP',
            'qr_code_string' => '00020126...qr-payload',
        ]);
        $response->assertJsonStructure(['request_id', 'reference_id']);

        $this->assertDatabaseCount('coins_transactions', 1);
        $tx = CoinsTransaction::first();
        $this->assertNotNull($tx->request_id);
        $this->assertSame('coins-order-123', $tx->reference_id);
        $this->assertSame('100.00', $tx->amount);
        $this->assertSame('PHP', $tx->currency);
        $this->assertSame('PENDING', $tx->status);
        $this->assertSame('00020126...qr-payload', $tx->qr_code_string);
        $this->assertIsArray($tx->raw_response);
        $this->assertArrayHasKey('status', $tx->raw_response);
        $this->assertSame(0, $tx->raw_response['status']);
    }

    public function test_generate_qr_returns_error_when_api_fails(): void
    {
        config([
            'coins.base_url' => 'https://api.9001.pl-qa.coinsxyz.me',
            'coins.api_key' => 'test-key',
            'coins.secret_key' => 'test-secret',
        ]);
        Http::fake([
            'api.9001.pl-qa.coinsxyz.me/*' => Http::response([
                'status' => -1001,
                'msg' => 'Invalid API key',
            ], 200),
        ]);

        $response = $this->postJson(route('coins.generate-qr'), ['amount' => 50]);

        $response->assertStatus(200);
        $response->assertJson(['success' => false, 'message' => 'Coins API error: Invalid API key']);
        $this->assertDatabaseCount('coins_transactions', 0);
    }

    public function test_generate_qr_maps_qr_feature_not_enabled_error_to_actionable_message(): void
    {
        config([
            'coins.base_url' => 'https://api.9001.pl-qa.coinsxyz.me',
            'coins.api_key' => 'test-key',
            'coins.secret_key' => 'test-secret',
        ]);
        Http::fake([
            'api.9001.pl-qa.coinsxyz.me/*' => Http::response([
                'status' => 88010063,
                'error' => 'You do not support this feature currently, please contact customer service.',
                'data' => null,
            ], 200),
        ]);

        $response = $this->postJson(route('coins.generate-qr'), ['amount' => 50]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'message' => 'Coins API error: Coins.ph account is not enabled for QR payment handling yet. Ask Coins support to enable QR integration for this account/API key.',
        ]);
        $this->assertDatabaseCount('coins_transactions', 0);
    }

    public function test_generate_qr_retries_with_unsorted_signature_when_signature_is_rejected(): void
    {
        config([
            'coins.base_url' => 'https://api.9001.pl-qa.coinsxyz.me',
            'coins.api_key' => 'test-key',
            'coins.secret_key' => 'test-secret',
        ]);

        $requestCount = 0;
        $signer = new CoinsGenerateQrSigner;

        Http::fake([
            'api.9001.pl-qa.coinsxyz.me/*' => function ($request) use (&$requestCount, $signer) {
                $requestCount++;

                $timestamp = (string) ($request->header('Timestamp')[0] ?? '');
                $signature = (string) ($request->header('Signature')[0] ?? '');
                $body = json_decode((string) $request->body(), true) ?? [];

                $expected = $signer->sign(
                    $body,
                    'test-secret',
                    $timestamp,
                    $requestCount === 1 ? CoinsGenerateQrSigner::STRATEGY_RAW_JSON : CoinsGenerateQrSigner::STRATEGY_KV_SORTED_WITH_TIMESTAMP
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
                        'orderId' => 'retry-coins-order-123',
                        'qrCode' => 'retry-qr-payload',
                    ],
                ], 200);
            },
        ]);

        $response = $this->postJson(route('coins.generate-qr'), ['amount' => 55]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'PENDING',
            'qr_code_string' => 'retry-qr-payload',
            'reference_id' => 'retry-coins-order-123',
        ]);

        $this->assertSame(2, $requestCount);
        $this->assertDatabaseCount('coins_transactions', 1);
    }

    public function test_generate_qr_retries_with_raw_json_seconds_on_fourth_attempt(): void
    {
        config([
            'coins.base_url' => 'https://api.9001.pl-qa.coinsxyz.me',
            'coins.api_key' => 'test-key',
            'coins.secret_key' => 'test-secret',
        ]);

        $requestCount = 0;
        $signer = new CoinsGenerateQrSigner;
        $timestamps = [];

        Http::fake([
            'api.9001.pl-qa.coinsxyz.me/*' => function ($request) use (&$requestCount, $signer, &$timestamps) {
                $requestCount++;

                $timestamp = (string) ($request->header('Timestamp')[0] ?? '');
                $timestamps[] = $timestamp;
                $signature = (string) ($request->header('Signature')[0] ?? '');
                $body = json_decode((string) $request->body(), true) ?? [];

                if ($requestCount === 1) {
                    $expected = $signer->sign($body, 'test-secret', $timestamp, CoinsGenerateQrSigner::STRATEGY_RAW_JSON);
                    $this->assertSame($expected['signature'], $signature);
                } elseif ($requestCount === 2) {
                    $expected = $signer->sign($body, 'test-secret', $timestamp, CoinsGenerateQrSigner::STRATEGY_KV_SORTED_WITH_TIMESTAMP);
                    $this->assertSame($expected['signature'], $signature);
                } elseif ($requestCount === 3) {
                    $expected = $signer->sign($body, 'test-secret', $timestamp, CoinsGenerateQrSigner::STRATEGY_KV_INPUT_ORDER_WITH_TIMESTAMP);
                    $this->assertSame($expected['signature'], $signature);
                } else {
                    $expected = $signer->sign($body, 'test-secret', $timestamp, CoinsGenerateQrSigner::STRATEGY_RAW_JSON);
                    $this->assertSame($expected['signature'], $signature);
                    $this->assertSame(10, strlen($timestamp));
                }

                if ($requestCount < 4) {
                    return Http::response([
                        'status' => -1022,
                        'msg' => 'Signature for this request is not valid.',
                    ], 200);
                }

                return Http::response([
                    'status' => 0,
                    'data' => [
                        'orderId' => 'retry-raw-seconds-order-123',
                        'qrCode' => 'retry-raw-seconds-qr-payload',
                    ],
                ], 200);
            },
        ]);

        $response = $this->postJson(route('coins.generate-qr'), ['amount' => 65]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'PENDING',
            'qr_code_string' => 'retry-raw-seconds-qr-payload',
            'reference_id' => 'retry-raw-seconds-order-123',
        ]);

        $this->assertSame(4, $requestCount);
        $this->assertSame($timestamps[0], $timestamps[1]);
        $this->assertSame($timestamps[1], $timestamps[2]);
        $this->assertNotSame($timestamps[2], $timestamps[3]);
        $this->assertDatabaseCount('coins_transactions', 1);
    }

    public function test_generate_qr_does_not_fallback_when_strategy_is_not_auto(): void
    {
        config([
            'coins.base_url' => 'https://api.9001.pl-qa.coinsxyz.me',
            'coins.api_key' => 'test-key',
            'coins.secret_key' => 'test-secret',
            'coins.auth.generate_qr.strategy' => 'raw_json',
            'coins.auth.generate_qr.timestamp_unit' => 'milliseconds',
        ]);

        $requestCount = 0;
        Http::fake([
            'api.9001.pl-qa.coinsxyz.me/*' => function () use (&$requestCount) {
                $requestCount++;

                return Http::response([
                    'status' => -1022,
                    'msg' => 'Signature for this request is not valid.',
                ], 200);
            },
        ]);

        $response = $this->postJson(route('coins.generate-qr'), ['amount' => 65]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'message' => 'Coins API error: Signature for this request is not valid.',
        ]);
        $this->assertSame(1, $requestCount);
        $this->assertDatabaseCount('coins_transactions', 0);
    }

    public function test_generate_qr_uses_seconds_timestamp_unit_when_configured(): void
    {
        config([
            'coins.base_url' => 'https://api.9001.pl-qa.coinsxyz.me',
            'coins.api_key' => 'test-key',
            'coins.secret_key' => 'test-secret',
            'coins.auth.generate_qr.strategy' => 'raw_json',
            'coins.auth.generate_qr.timestamp_unit' => 'seconds',
        ]);

        Http::fake([
            'api.9001.pl-qa.coinsxyz.me/*' => function ($request) {
                $timestamp = (string) ($request->header('Timestamp')[0] ?? '');
                $this->assertSame(10, strlen($timestamp));

                return Http::response([
                    'status' => 0,
                    'data' => [
                        'orderId' => 'seconds-order-123',
                        'qrCode' => 'seconds-qr-payload',
                    ],
                ], 200);
            },
        ]);

        $response = $this->postJson(route('coins.generate-qr'), ['amount' => 75]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'reference_id' => 'seconds-order-123',
            'qr_code_string' => 'seconds-qr-payload',
        ]);
    }

    public function test_generate_qr_returns_error_key_when_response_code_1006(): void
    {
        config([
            'coins.base_url' => 'https://api.9001.pl-qa.coinsxyz.me',
            'coins.api_key' => 'test-key',
            'coins.secret_key' => 'test-secret',
        ]);
        Http::fake([
            'api.9001.pl-qa.coinsxyz.me/*' => Http::response([
                'status' => 1006,
                'code' => 1006,
                'msg' => 'IP not allowed',
            ], 200),
        ]);

        $response = $this->postJson(route('coins.generate-qr'), ['amount' => 10]);

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'IP not whitelisted. Please contact Coins to whitelist server IP.',
        ]);
        $this->assertDatabaseCount('coins_transactions', 0);
    }
}
