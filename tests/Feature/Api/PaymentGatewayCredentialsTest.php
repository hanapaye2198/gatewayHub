<?php

namespace Tests\Feature\Api;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\User;
use App\Services\Gateways\Drivers\CoinsDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $user = User::factory()->create(['api_key' => 'test-key']);
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => Gateway::first()->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'coins',
            'reference' => 'ref-1',
        ], ['Authorization' => 'Bearer test-key']);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'Gateway "Coins.ph" is missing required credentials: client_id, client_secret, api_base. Configure them in Dashboard > Gateways.']);
    }

    public function test_create_payment_returns_403_when_gateway_globally_disabled(): void
    {
        $gateway = Gateway::first();
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
}
