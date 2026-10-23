<?php

namespace Tests\Feature\Api;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\User;
use App\Services\Gateways\Drivers\PayMongoDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiPaymentHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gateway::query()->updateOrCreate(['code' => 'paymongo'], [
            'name' => 'PayMongo',
            'driver_class' => PayMongoDriver::class,
            'is_global_enabled' => true,
            'config_json' => [
                'secret_key' => 'sk_test_kemopay',
                'public_key' => 'pk_test_kemopay',
                'webhook_secret' => 'whsk_test_kemopay',
            ],
        ]);
        Gateway::query()->updateOrCreate(['code' => 'gcash'], [
            'name' => 'GCash',
            'driver_class' => PayMongoDriver::class,
            'is_global_enabled' => true,
        ]);
    }

    public function test_inactive_merchant_receives_403(): void
    {
        $user = User::factory()->withMerchantApiKey('inactive-key')->create();
        $user->merchant->update(['is_active' => false]);

        $gcashGateway = Gateway::query()->where('code', 'gcash')->firstOrFail();
        MerchantGateway::query()->create([
            'merchant_id' => $user->merchant_id,
            'gateway_id' => $gcashGateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'gcash',
            'reference' => 'ref-1',
        ], ['Authorization' => 'Bearer inactive-key']);

        $response->assertStatus(403);
        $response->assertJson(['success' => false, 'error' => 'Merchant account is inactive.']);
    }

    public function test_invalid_api_key_receives_401(): void
    {
        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'gcash',
            'reference' => 'ref-1',
        ], ['Authorization' => 'Bearer invalid-key']);

        $response->assertStatus(401);
        $response->assertJson(['success' => false, 'error' => 'Invalid API key.']);
    }

    public function test_amount_zero_returns_422(): void
    {
        $user = User::factory()->withMerchantApiKey('key-1')->create();
        $gcashGateway = Gateway::query()->where('code', 'gcash')->firstOrFail();
        MerchantGateway::query()->create([
            'merchant_id' => $user->merchant_id,
            'gateway_id' => $gcashGateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => 0,
            'currency' => 'PHP',
            'gateway' => 'gcash',
            'reference' => 'ref-1',
        ], ['Authorization' => 'Bearer key-1']);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_hashed_api_key_can_authenticate_without_plaintext_key(): void
    {
        $user = User::factory()->create();
        DB::table('merchants')->where('id', $user->merchant_id)->update([
            'api_key' => null,
            'api_key_hash' => hash('sha256', 'key-hash-only'),
            'api_key_last_four' => 'only',
            'updated_at' => now(),
        ]);

        $gcashGateway = Gateway::query()->where('code', 'gcash')->firstOrFail();
        MerchantGateway::query()->create([
            'merchant_id' => $user->merchant_id,
            'gateway_id' => $gcashGateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => 0,
            'currency' => 'PHP',
            'gateway' => 'gcash',
            'reference' => 'ref-hash-only',
        ], ['Authorization' => 'Bearer key-hash-only']);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_plaintext_only_legacy_api_key_is_rejected(): void
    {
        $user = User::factory()->create();
        DB::table('merchants')->where('id', $user->merchant_id)->update([
            'api_key' => 'legacy-plaintext-key',
            'api_key_hash' => null,
            'api_key_last_four' => null,
            'updated_at' => now(),
        ]);

        $gcashGateway = Gateway::query()->where('code', 'gcash')->firstOrFail();
        MerchantGateway::query()->create([
            'merchant_id' => $user->merchant_id,
            'gateway_id' => $gcashGateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'gcash',
            'reference' => 'ref-legacy-plaintext',
        ], ['Authorization' => 'Bearer legacy-plaintext-key']);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'error' => 'Invalid API key.',
        ]);
    }

    public function test_idempotency_key_returns_cached_response(): void
    {
        $this->fakePayMongoPayment('pi_idempotency_001');

        $user = User::factory()->withMerchantApiKey('key-idem')->create();
        $gcashGateway = Gateway::query()->where('code', 'gcash')->firstOrFail();
        MerchantGateway::query()->create([
            'merchant_id' => $user->merchant_id,
            'gateway_id' => $gcashGateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $payload = [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'gcash',
            'reference' => 'ref-idem',
        ];
        $headers = [
            'Authorization' => 'Bearer key-idem',
            'Idempotency-Key' => 'unique-key-123',
        ];

        $response1 = $this->postJson('/api/payments', $payload, $headers);
        $response2 = $this->postJson('/api/payments', $payload, $headers);

        $response1->assertStatus(201);
        $response2->assertStatus(201);

        $data1 = $response1->json('data');
        $data2 = $response2->json('data');

        $this->assertSame($data1['payment_id'], $data2['payment_id']);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_api_response_format_has_success_data_error(): void
    {
        $this->fakePayMongoPayment('pi_format_001');

        $user = User::factory()->withMerchantApiKey('key-format')->create();
        $gcashGateway = Gateway::query()->where('code', 'gcash')->firstOrFail();
        MerchantGateway::query()->create([
            'merchant_id' => $user->merchant_id,
            'gateway_id' => $gcashGateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'gcash',
            'reference' => 'ref-format',
        ], ['Authorization' => 'Bearer key-format']);

        $response->assertSuccessful();
        $json = $response->json();
        $this->assertArrayHasKey('success', $json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('error', $json);
        $this->assertTrue($json['success']);
        $this->assertNull($json['error']);
        $this->assertArrayHasKey('payment_id', $json['data']);
    }

    private function fakePayMongoPayment(string $intentId): void
    {
        Http::fake(function ($request) use ($intentId) {
            return match (parse_url($request->url(), PHP_URL_PATH)) {
                '/v1/merchants/capabilities/payment_methods' => Http::response([
                    'data' => ['attributes' => ['payment_methods' => ['qrph', 'gcash', 'paymaya', 'grab_pay', 'shopeepay']]],
                ], 200),
                '/v1/payment_intents' => Http::response([
                    'data' => ['id' => $intentId, 'attributes' => ['client_key' => $intentId.'_client_key']],
                ], 200),
                '/v1/payment_methods' => Http::response(['data' => ['id' => 'pm_'.str_replace('pi_', '', $intentId)]], 200),
                '/v1/payment_intents/'.$intentId.'/attach' => Http::response([
                    'data' => [
                        'id' => $intentId,
                        'attributes' => [
                            'status' => 'awaiting_next_action',
                            'next_action' => ['redirect' => ['url' => 'https://wallet.example/gcash']],
                        ],
                    ],
                ], 200),
                default => Http::response(['message' => 'Unexpected PayMongo request.'], 404),
            };
        });
    }
}
