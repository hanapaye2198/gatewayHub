<?php

namespace Tests\Feature;

use App\Models\CoinsTransaction;
use App\Services\Coins\CoinsSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CoinsQrGenerationTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_generate_qr_retries_with_unsorted_signature_when_signature_is_rejected(): void
    {
        config([
            'coins.base_url' => 'https://api.9001.pl-qa.coinsxyz.me',
            'coins.api_key' => 'test-key',
            'coins.secret_key' => 'test-secret',
        ]);

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
                    'test-secret',
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
