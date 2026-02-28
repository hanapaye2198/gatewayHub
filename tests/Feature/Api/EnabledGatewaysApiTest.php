<?php

namespace Tests\Feature\Api;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnabledGatewaysApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_gateways_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/gateways/enabled');

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'data' => [],
            'error' => 'Unauthenticated.',
        ]);
    }

    public function test_enabled_gateways_endpoint_returns_only_enabled_and_global_gateways_for_merchant(): void
    {
        Gateway::query()->delete();

        $merchantToken = Str::random(64);
        $otherMerchantToken = Str::random(64);
        $merchant = User::factory()->create([
            'api_key' => $merchantToken,
            'role' => 'merchant',
        ]);
        $otherMerchant = User::factory()->create([
            'api_key' => $otherMerchantToken,
            'role' => 'merchant',
        ]);

        $coins = Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => 'App\\Services\\Gateways\\Drivers\\CoinsDriver',
            'is_global_enabled' => true,
        ]);
        $gcash = Gateway::query()->create([
            'code' => 'gcash',
            'name' => 'Gcash',
            'driver_class' => 'App\\Services\\Gateways\\Drivers\\GcashDriver',
            'is_global_enabled' => true,
        ]);
        $maya = Gateway::query()->create([
            'code' => 'maya',
            'name' => 'Maya',
            'driver_class' => 'App\\Services\\Gateways\\Drivers\\MayaDriver',
            'is_global_enabled' => true,
        ]);
        $paypal = Gateway::query()->create([
            'code' => 'paypal',
            'name' => 'PayPal',
            'driver_class' => 'App\\Services\\Gateways\\Drivers\\PaypalDriver',
            'is_global_enabled' => false,
        ]);
        $qrph = Gateway::query()->create([
            'code' => 'qrph',
            'name' => 'QRPH',
            'driver_class' => 'App\\Services\\Gateways\\Drivers\\QrphDriver',
            'is_global_enabled' => true,
        ]);

        MerchantGateway::query()->create([
            'user_id' => $merchant->id,
            'gateway_id' => $coins->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);
        MerchantGateway::query()->create([
            'user_id' => $merchant->id,
            'gateway_id' => $gcash->id,
            'is_enabled' => false,
            'config_json' => [],
        ]);
        MerchantGateway::query()->create([
            'user_id' => $merchant->id,
            'gateway_id' => $maya->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);
        MerchantGateway::query()->create([
            'user_id' => $merchant->id,
            'gateway_id' => $paypal->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);
        MerchantGateway::query()->create([
            'user_id' => $merchant->id,
            'gateway_id' => $qrph->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);
        MerchantGateway::query()->create([
            'user_id' => $otherMerchant->id,
            'gateway_id' => $gcash->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->withToken($merchantToken)
            ->getJson('/api/gateways/enabled');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('error', null);
        $response->assertJsonPath('data.count', 3);

        $gateways = $response->json('data.gateways');
        $this->assertSame([
            ['code' => 'coins', 'name' => 'Coins.ph'],
            ['code' => 'maya', 'name' => 'Maya'],
            ['code' => 'qrph', 'name' => 'QRPH'],
        ], $gateways);
    }

    public function test_enabled_gateways_endpoint_rejects_non_merchant_role(): void
    {
        Gateway::query()->delete();

        $adminToken = Str::random(64);
        $admin = User::factory()->admin()->create([
            'api_key' => $adminToken,
        ]);

        $response = $this->withToken($adminToken)
            ->getJson('/api/gateways/enabled');

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'data' => [],
            'error' => 'Forbidden.',
        ]);
    }
}
