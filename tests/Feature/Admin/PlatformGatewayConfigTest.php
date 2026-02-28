<?php

namespace Tests\Feature\Admin;

use App\Models\Gateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformGatewayConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_platform_gateway_credentials(): void
    {
        $admin = User::factory()->admin()->create();
        $gateway = Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => \App\Services\Gateways\Drivers\CoinsDriver::class,
            'is_global_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.gateways.platform-config', ['gateway' => $gateway]), [
            'config' => [
                'client_id' => 'platform-client-id',
                'client_secret' => 'platform-client-secret',
                'api_base' => 'sandbox',
                'webhook_secret' => 'platform-webhook-secret',
            ],
        ]);

        $response->assertRedirect(route('admin.gateways.index'));
        $response->assertSessionHas('status', 'Platform credentials updated for gateway "Coins.ph".');

        $gateway->refresh();
        $this->assertSame('platform-client-id', $gateway->config_json['client_id'] ?? null);
        $this->assertSame('sandbox', $gateway->config_json['api_base'] ?? null);
        $this->assertSame('platform-webhook-secret', $gateway->config_json['webhook_secret'] ?? null);
    }

    public function test_admin_update_keeps_existing_masked_secrets_when_left_blank(): void
    {
        $admin = User::factory()->admin()->create();
        $gateway = Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => \App\Services\Gateways\Drivers\CoinsDriver::class,
            'is_global_enabled' => true,
            'config_json' => [
                'client_id' => 'old-client-id',
                'client_secret' => 'old-client-secret',
                'api_base' => 'sandbox',
                'webhook_secret' => 'old-webhook-secret',
            ],
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.gateways.platform-config', ['gateway' => $gateway]), [
            'config' => [
                'client_id' => 'new-client-id',
                'client_secret' => '',
                'api_base' => 'prod',
                'webhook_secret' => '',
            ],
        ]);

        $response->assertRedirect(route('admin.gateways.index'));

        $gateway->refresh();
        $this->assertSame('new-client-id', $gateway->config_json['client_id'] ?? null);
        $this->assertSame('old-client-secret', $gateway->config_json['client_secret'] ?? null);
        $this->assertSame('prod', $gateway->config_json['api_base'] ?? null);
        $this->assertSame('old-webhook-secret', $gateway->config_json['webhook_secret'] ?? null);
    }

    public function test_merchant_cannot_update_platform_gateway_credentials(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant']);
        $gateway = Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => \App\Services\Gateways\Drivers\CoinsDriver::class,
            'is_global_enabled' => true,
        ]);

        $response = $this->actingAs($merchant)->patch(route('admin.gateways.platform-config', ['gateway' => $gateway]), [
            'config' => [
                'client_id' => 'bad',
                'client_secret' => 'bad',
                'api_base' => 'sandbox',
            ],
        ]);

        $response->assertForbidden();
    }
}
