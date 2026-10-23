<?php

namespace Tests\Feature\Api;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
        Cache::flush();
        Http::fake([
            'https://api.paymongo.com/v1/merchants/capabilities/payment_methods' => Http::response([
                'data' => ['attributes' => ['payment_methods' => ['qrph', 'paymaya']]],
            ], 200),
        ]);

        $merchantToken = Str::random(64);
        $otherMerchantToken = Str::random(64);
        $merchant = User::factory()->withMerchantApiKey($merchantToken)->create();
        $otherMerchant = User::factory()->withMerchantApiKey($otherMerchantToken)->create();

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
            'merchant_id' => $merchant->id,
            'gateway_id' => $coins->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);
        MerchantGateway::query()->create([
            'merchant_id' => $merchant->id,
            'gateway_id' => $gcash->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);
        MerchantGateway::query()->create([
            'merchant_id' => $merchant->id,
            'gateway_id' => $maya->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);
        MerchantGateway::query()->create([
            'merchant_id' => $merchant->id,
            'gateway_id' => $paypal->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);
        MerchantGateway::query()->create([
            'merchant_id' => $merchant->id,
            'gateway_id' => $qrph->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);
        MerchantGateway::query()->create([
            'merchant_id' => $otherMerchant->id,
            'gateway_id' => $gcash->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->withToken($merchantToken)
            ->getJson('/api/gateways/enabled');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('error', null);
        $response->assertJsonPath('data.count', 2);
        $response->assertJsonPath('data.provider_availability.paymongo.methods.gcash', 'unavailable');
        $response->assertJsonPath('data.provider_availability.paymongo.methods.maya', 'available');
        $response->assertJsonStructure([
            'data' => [
                'gateways',
                'count',
                'merchant' => [
                    'name',
                    'logo',
                    'theme_color',
                ],
            ],
        ]);

        $gateways = $response->json('data.gateways');
        $response->assertDontSee('PayPal');
        $this->assertSame([
            ['code' => 'maya', 'name' => 'Maya'],
            ['code' => 'qrph', 'name' => 'QRPH'],
        ], $gateways);
    }

    public function test_enabled_gateways_endpoint_rejects_invalid_api_key(): void
    {
        Gateway::query()->delete();

        $adminToken = Str::random(64);
        User::factory()->admin()->create();

        $response = $this->withToken($adminToken)
            ->getJson('/api/gateways/enabled');

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'data' => [],
            'error' => 'Invalid API key.',
        ]);
    }
}
