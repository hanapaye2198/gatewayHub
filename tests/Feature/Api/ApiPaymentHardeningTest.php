<?php

namespace Tests\Feature\Api;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\User;
use App\Services\Gateways\Drivers\CoinsDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiPaymentHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => CoinsDriver::class,
            'is_global_enabled' => true,
        ]);
    }

    public function test_inactive_merchant_receives_403(): void
    {
        $user = User::factory()->create(['api_key' => 'inactive-key', 'is_active' => false]);
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => Gateway::first()->id,
            'is_enabled' => true,
            'config_json' => ['client_id' => 'c', 'client_secret' => 's', 'api_base' => 'sandbox'],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'coins',
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
            'gateway' => 'coins',
            'reference' => 'ref-1',
        ], ['Authorization' => 'Bearer invalid-key']);

        $response->assertStatus(401);
        $response->assertJson(['success' => false, 'error' => 'Invalid API key.']);
    }

    public function test_amount_zero_returns_422(): void
    {
        $user = User::factory()->create(['api_key' => 'key-1']);
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => Gateway::first()->id,
            'is_enabled' => true,
            'config_json' => ['client_id' => 'c', 'client_secret' => 's', 'api_base' => 'sandbox'],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => 0,
            'currency' => 'PHP',
            'gateway' => 'coins',
            'reference' => 'ref-1',
        ], ['Authorization' => 'Bearer key-1']);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_idempotency_key_returns_cached_response(): void
    {
        Http::fake(['*' => Http::response(['code' => 0, 'data' => ['orderId' => 'ord-1', 'qrCode' => 'qr123']], 200)]);

        $user = User::factory()->create(['api_key' => 'key-idem']);
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => Gateway::first()->id,
            'is_enabled' => true,
            'config_json' => ['client_id' => 'c', 'client_secret' => 's', 'api_base' => 'sandbox'],
        ]);

        $payload = [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'coins',
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
        Http::fake(['*' => Http::response(['code' => 0, 'data' => ['orderId' => 'x', 'qrCode' => 'y']], 200)]);

        $user = User::factory()->create(['api_key' => 'key-format']);
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => Gateway::first()->id,
            'is_enabled' => true,
            'config_json' => ['client_id' => 'c', 'client_secret' => 's', 'api_base' => 'sandbox'],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'coins',
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
}
