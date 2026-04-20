<?php

namespace Tests\Unit\Services\Gateways;

use App\Models\Gateway;
use App\Services\Gateways\Drivers\CoinsDriver;
use App\Services\Gateways\PlatformGatewayConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformGatewayConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_gateway_code_with_meta_reports_env_source_when_db_overrides_are_empty(): void
    {
        config([
            'coins.gateway.client_id' => 'env-client-id',
            'coins.gateway.client_secret' => 'env-client-secret',
            'coins.gateway.api_base' => 'sandbox',
            'coins.gateway.source' => 'GATEWAYHUB',
        ]);

        Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => CoinsDriver::class,
            'is_global_enabled' => true,
            'config_json' => [],
        ]);

        $service = new PlatformGatewayConfigService;
        $resolved = $service->forGatewayCodeWithMeta('coins');

        $this->assertSame('env', $resolved['credential_source']);
        $this->assertSame('env-client-id', $resolved['config']['client_id'] ?? null);
        $this->assertSame('env-client-secret', $resolved['config']['client_secret'] ?? null);
    }

    public function test_for_gateway_code_with_meta_reports_db_source_when_db_overrides_exist(): void
    {
        Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => CoinsDriver::class,
            'is_global_enabled' => true,
            'config_json' => [
                'client_id' => 'db-client-id',
                'client_secret' => 'db-client-secret',
                'api_base' => 'prod',
            ],
        ]);

        $service = new PlatformGatewayConfigService;
        $resolved = $service->forGatewayCodeWithMeta('coins');

        $this->assertSame('db', $resolved['credential_source']);
        $this->assertSame('db-client-id', $resolved['config']['client_id'] ?? null);
        $this->assertSame('db-client-secret', $resolved['config']['client_secret'] ?? null);
        $this->assertSame('prod', $resolved['config']['api_base'] ?? null);
    }

    public function test_for_gateway_code_with_meta_does_not_fall_back_webhook_secret_to_client_secret_for_coins(): void
    {
        config()->set('coins.webhook.secret', '');

        Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => CoinsDriver::class,
            'is_global_enabled' => true,
            'config_json' => [
                'client_id' => 'db-client-id',
                'client_secret' => 'db-client-secret',
                'api_base' => 'prod',
            ],
        ]);

        $service = new PlatformGatewayConfigService;
        $resolved = $service->forGatewayCodeWithMeta('coins');

        $this->assertNotSame(
            'db-client-secret',
            $resolved['config']['webhook_secret'] ?? null,
            'client_secret must never be used as a webhook verification secret.'
        );
        $webhookSecret = $resolved['config']['webhook_secret'] ?? '';
        $this->assertTrue(
            $webhookSecret === '' || $webhookSecret === null,
            'webhook_secret must remain unset when not explicitly configured; got ['.(string) $webhookSecret.'].'
        );
    }

    public function test_for_gateway_code_with_meta_ignores_placeholder_db_values(): void
    {
        config([
            'coins.gateway.client_id' => 'env-client-id',
            'coins.gateway.client_secret' => 'env-client-secret',
            'coins.gateway.api_base' => 'sandbox',
        ]);

        Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => CoinsDriver::class,
            'is_global_enabled' => true,
            'config_json' => [
                'client_id' => 'your_real_client_id',
                'client_secret' => 'your_real_client_secret',
            ],
        ]);

        $service = new PlatformGatewayConfigService;
        $resolved = $service->forGatewayCodeWithMeta('coins');

        $this->assertSame('env', $resolved['credential_source']);
        $this->assertSame('env-client-id', $resolved['config']['client_id'] ?? null);
        $this->assertSame('env-client-secret', $resolved['config']['client_secret'] ?? null);
    }
}
