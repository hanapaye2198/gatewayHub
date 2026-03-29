<?php

namespace Tests\Feature\Admin;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantGatewayToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enable_gateway_for_merchant(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $merchant = User::factory()->create();
        $gateway = Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => 'App\Services\Gateways\Drivers\CoinsDriver',
            'is_global_enabled' => true,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.gateways.merchant-update', [
            'gateway' => $gateway,
            'merchant' => $merchant->merchant,
        ]), [
            'is_enabled' => true,
        ]);

        $response->assertRedirect(route('admin.gateways.index'));
        $this->assertDatabaseHas('merchant_gateways', [
            'merchant_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);
    }

    public function test_admin_cannot_enable_gateway_for_merchant_when_gateway_is_globally_disabled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $merchant = User::factory()->create();
        $gateway = Gateway::query()->create([
            'code' => 'maya',
            'name' => 'Maya',
            'driver_class' => 'App\Services\Gateways\Drivers\MayaDriver',
            'is_global_enabled' => false,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.gateways.merchant-update', [
            'gateway' => $gateway,
            'merchant' => $merchant->merchant,
        ]), [
            'is_enabled' => true,
        ]);

        $response->assertRedirect(route('admin.gateways.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('merchant_gateways', [
            'merchant_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);
    }

    public function test_merchant_cannot_update_other_merchant_gateway_access(): void
    {
        $merchantActor = User::factory()->create();
        $merchant = User::factory()->create();
        $gateway = Gateway::query()->create([
            'code' => 'paypal',
            'name' => 'PayPal',
            'driver_class' => 'App\Services\Gateways\Drivers\PaypalDriver',
            'is_global_enabled' => true,
        ]);
        MerchantGateway::query()->create([
            'merchant_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => false,
            'config_json' => [],
        ]);

        $response = $this->actingAs($merchantActor)->patch(route('admin.gateways.merchant-update', [
            'gateway' => $gateway,
            'merchant' => $merchant->merchant,
        ]), [
            'is_enabled' => true,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('merchant_gateways', [
            'merchant_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => false,
        ]);
    }
}
