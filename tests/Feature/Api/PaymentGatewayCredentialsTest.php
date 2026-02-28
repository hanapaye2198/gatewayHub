<?php

namespace Tests\Feature\Api;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\User;
use App\Services\Gateways\Drivers\CoinsDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentGatewayCredentialsTest extends TestCase
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

    public function test_create_payment_returns_422_when_gateway_enabled_but_credentials_missing(): void
    {
        config()->set('coins.gateway.client_id', '');
        config()->set('coins.gateway.client_secret', '');
        config()->set('coins.gateway.api_base', '');

        $user = User::factory()->create(['api_key' => 'test-key']);
        $coinsGateway = Gateway::query()->where('code', 'coins')->firstOrFail();
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => $coinsGateway->id,
            'is_enabled' => true,
            'config_json' => [
                'client_id' => 'merchant-client-id',
                'client_secret' => 'merchant-client-secret',
                'api_base' => 'sandbox',
            ],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'coins',
            'reference' => 'ref-1',
        ], ['Authorization' => 'Bearer test-key']);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'Gateway "Coins.ph" is missing required platform credentials: client_id, client_secret, api_base. Configure them in SurePay admin settings.']);
    }

    public function test_create_payment_returns_403_when_gateway_globally_disabled(): void
    {
        $gateway = Gateway::query()->where('code', 'coins')->firstOrFail();
        $gateway->update(['is_global_enabled' => false]);

        $user = User::factory()->create(['api_key' => 'test-key']);
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => [
                'client_id' => 'c',
                'client_secret' => 's',
                'api_base' => 'sandbox',
            ],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'coins',
            'reference' => 'ref-1',
        ], ['Authorization' => 'Bearer test-key']);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Gateway is not available.']);
    }

    public function test_create_payment_uses_platform_credentials_from_gateway_record(): void
    {
        config()->set('coins.gateway.client_id', '');
        config()->set('coins.gateway.client_secret', '');
        config()->set('coins.gateway.api_base', '');

        Http::fake([
            '*' => Http::response([
                'status' => 0,
                'data' => [
                    'orderId' => 'QRPH-COINS-PLATFORM-001',
                    'qrCode' => '000201010212...',
                ],
            ], 200),
        ]);

        $gateway = Gateway::query()->where('code', 'coins')->firstOrFail();
        $gateway->update([
            'config_json' => [
                'client_id' => 'db-platform-client-id',
                'client_secret' => 'db-platform-client-secret',
                'api_base' => 'sandbox',
            ],
        ]);

        $user = User::factory()->create(['api_key' => 'test-key']);
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'coins',
            'reference' => 'ref-db-platform-1',
        ], ['Authorization' => 'Bearer test-key']);

        $response->assertStatus(201);
        $response->assertJsonPath('data.gateway', 'coins');
    }
}
