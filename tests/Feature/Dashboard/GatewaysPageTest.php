<?php

namespace Tests\Feature\Dashboard;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\User;
use App\Services\Gateways\Drivers\CoinsDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GatewaysPageTest extends TestCase
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

    public function test_merchant_sees_gateways_page_with_state_badges(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant']);

        $this->actingAs($merchant);
        $response = $this->get(route('dashboard.gateways'));

        $response->assertOk();
        $response->assertSee('Gateways');
        $response->assertSee('Coins.ph');
        $response->assertSee('Disabled');
    }

    public function test_merchant_sees_configure_button_when_enabled_but_not_configured(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant']);
        $gateway = Gateway::first();
        MerchantGateway::query()->create([
            'user_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $this->actingAs($merchant);

        Livewire::actingAs($merchant)
            ->test('pages::dashboard.gateways')
            ->set('gatewayConfigs.'.$gateway->id.'.enabled', true)
            ->assertSee('Setup Required')
            ->assertSee('Configure');
    }

    public function test_test_gateway_structure_updates_last_tested_and_shows_success_message(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant']);
        $gateway = Gateway::first();
        MerchantGateway::query()->create([
            'user_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => [
                'client_id' => 'test-id',
                'client_secret' => 'test-secret',
                'api_base' => 'sandbox',
            ],
        ]);

        $this->actingAs($merchant);

        $component = Livewire::actingAs($merchant)
            ->test('pages::dashboard.gateways')
            ->set('gatewayConfigs.'.$gateway->id.'.enabled', true)
            ->set('gatewayConfigs.'.$gateway->id.'.client_id', 'test-id')
            ->set('gatewayConfigs.'.$gateway->id.'.client_secret', 'test-secret')
            ->set('gatewayConfigs.'.$gateway->id.'.api_base', 'sandbox')
            ->call('testGateway', $gateway->id);

        $component->assertSet('gatewayConfigs.'.$gateway->id.'.last_test_status', 'success');
        $component->assertSet('gatewayTestMessages.'.$gateway->id.'.type', 'success');

        $merchant->refresh();
        $mg = MerchantGateway::query()->where('user_id', $merchant->id)->where('gateway_id', $gateway->id)->first();
        $this->assertNotNull($mg->last_tested_at);
        $this->assertSame('success', $mg->last_test_status);
    }

    public function test_save_gateway_still_works(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant']);
        $gateway = Gateway::first();
        MerchantGateway::query()->create([
            'user_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $this->actingAs($merchant);

        Livewire::actingAs($merchant)
            ->test('pages::dashboard.gateways')
            ->set('gatewayConfigs.'.$gateway->id.'.enabled', true)
            ->set('gatewayConfigs.'.$gateway->id.'.client_id', 'my-id')
            ->set('gatewayConfigs.'.$gateway->id.'.client_secret', 'my-secret')
            ->set('gatewayConfigs.'.$gateway->id.'.api_base', 'sandbox')
            ->call('saveGateway', $gateway->id)
            ->assertHasNoErrors();

        $mg = MerchantGateway::query()->where('user_id', $merchant->id)->where('gateway_id', $gateway->id)->first();
        $this->assertSame('my-id', $mg->config_json['client_id'] ?? null);
        $this->assertSame('sandbox', $mg->config_json['api_base'] ?? null);
    }
}
