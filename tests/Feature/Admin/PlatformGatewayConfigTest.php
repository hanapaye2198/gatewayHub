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
        $gateway = Gateway::query()->where('code', 'paymongo')->firstOrFail();

        $response = $this->actingAs($admin)->patch(route('admin.gateways.platform-config', ['gateway' => $gateway]), [
            'config' => [
                'secret_key' => 'sk_test_platform-key',
                'public_key' => 'pk_test_platform-key',
                'webhook_secret' => 'platform-webhook-secret',
            ],
        ]);

        $response->assertRedirect(route('admin.gateways.index'));
        $response->assertSessionHas('status', 'Platform credentials updated for gateway "PayMongo".');

        $gateway->refresh();
        $this->assertSame('sk_test_platform-key', $gateway->config_json['secret_key'] ?? null);
        $this->assertSame('pk_test_platform-key', $gateway->config_json['public_key'] ?? null);
        $this->assertSame('platform-webhook-secret', $gateway->config_json['webhook_secret'] ?? null);
    }

    public function test_admin_can_configure_shared_paymongo_credentials(): void
    {
        $admin = User::factory()->admin()->create();
        $gateway = Gateway::query()->where('code', 'paymongo')->firstOrFail();

        $response = $this->actingAs($admin)->patch(route('admin.gateways.platform-config', ['gateway' => $gateway]), [
            'config' => [
                'secret_key' => 'sk_test_kemopay_platform',
                'public_key' => 'pk_test_kemopay_platform',
                'webhook_secret' => 'whsk_test_kemopay_platform',
            ],
        ]);

        $response->assertRedirect(route('admin.gateways.index'));
        $gateway->refresh();
        $this->assertSame('sk_test_kemopay_platform', $gateway->config_json['secret_key'] ?? null);
        $this->assertSame('pk_test_kemopay_platform', $gateway->config_json['public_key'] ?? null);
        $this->assertSame('whsk_test_kemopay_platform', $gateway->config_json['webhook_secret'] ?? null);
        $this->assertStringNotContainsString('sk_test_kemopay_platform', (string) $gateway->getRawOriginal('config_json'));
    }

    public function test_admin_cannot_configure_paymongo_api_keys_for_different_modes(): void
    {
        $admin = User::factory()->admin()->create();
        $gateway = Gateway::query()->where('code', 'paymongo')->firstOrFail();

        $response = $this->actingAs($admin)->from(route('admin.gateways.index'))->patch(
            route('admin.gateways.platform-config', ['gateway' => $gateway]),
            [
                'config' => [
                    'secret_key' => 'sk_live_kemopay_platform',
                    'public_key' => 'pk_test_kemopay_platform',
                    'webhook_secret' => 'whsk_test_kemopay_platform',
                ],
            ]
        );

        $response->assertRedirect(route('admin.gateways.index'));
        $response->assertSessionHasErrors('config.public_key');
        $this->assertSame([], $gateway->fresh()->config_json);
    }

    public function test_admin_update_keeps_existing_masked_secrets_when_left_blank(): void
    {
        $admin = User::factory()->admin()->create();
        $gateway = Gateway::query()->where('code', 'paymongo')->firstOrFail();
        $gateway->update([
            'config_json' => [
                'secret_key' => 'sk_test_old-secret',
                'public_key' => 'pk_test_old-public',
                'webhook_secret' => 'old-webhook-secret',
            ],
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.gateways.platform-config', ['gateway' => $gateway]), [
            'config' => [
                'secret_key' => 'sk_test_new-secret',
                'public_key' => '',
                'webhook_secret' => '',
            ],
        ]);

        $response->assertRedirect(route('admin.gateways.index'));

        $gateway->refresh();
        $this->assertSame('sk_test_new-secret', $gateway->config_json['secret_key'] ?? null);
        $this->assertSame('pk_test_old-public', $gateway->config_json['public_key'] ?? null);
        $this->assertSame('old-webhook-secret', $gateway->config_json['webhook_secret'] ?? null);
    }

    public function test_merchant_cannot_update_platform_gateway_credentials(): void
    {
        $merchant = User::factory()->create();
        $gateway = Gateway::query()->where('code', 'paymongo')->firstOrFail();

        $response = $this->actingAs($merchant)->patch(route('admin.gateways.platform-config', ['gateway' => $gateway]), [
            'config' => [
                'secret_key' => 'bad',
                'public_key' => 'bad',
                'webhook_secret' => 'bad',
            ],
        ]);

        $response->assertRedirect(url('/dashboard'));
    }

    public function test_admin_cannot_save_placeholder_paymongo_credentials(): void
    {
        $admin = User::factory()->admin()->create();
        $gateway = Gateway::query()->where('code', 'paymongo')->firstOrFail();

        $response = $this->actingAs($admin)->from(route('admin.gateways.index'))->patch(
            route('admin.gateways.platform-config', ['gateway' => $gateway]),
            [
                'config' => [
                    'secret_key' => 'sk_test_your_secret_key',
                    'public_key' => 'pk_test_your_public_key',
                    'webhook_secret' => 'whsk_your_webhook_secret',
                ],
            ]
        );

        $response->assertRedirect(route('admin.gateways.index'));
        $response->assertSessionHasErrors([
            'config.secret_key',
            'config.public_key',
            'config.webhook_secret',
        ]);

        $gateway->refresh();
        $this->assertSame([], $gateway->config_json ?? []);
    }

    public function test_non_coins_payment_option_does_not_accept_individual_platform_payment_credentials(): void
    {
        $admin = User::factory()->admin()->create();
        $gateway = Gateway::query()->where('code', 'maya')->firstOrFail();

        $response = $this->actingAs($admin)->patch(route('admin.gateways.platform-config', ['gateway' => $gateway]), [
            'config' => [
                'client_id' => 'maya-client-id',
                'client_secret' => 'maya-client-secret',
                'api_base' => 'sandbox',
            ],
        ]);

        $response->assertRedirect(route('admin.gateways.index'));
        $response->assertSessionHas('status', 'Gateway "Maya" does not require platform credential fields.');

        $gateway->refresh();
        $this->assertSame([], $gateway->config_json ?? []);
    }
}
