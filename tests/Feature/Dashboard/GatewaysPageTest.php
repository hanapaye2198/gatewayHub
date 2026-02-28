<?php

namespace Tests\Feature\Dashboard;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\User;
use App\Services\Gateways\Drivers\CoinsDriver;
use App\Services\Gateways\Drivers\QrphDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_merchant_sees_gateways_page_with_self_service_message(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant']);

        $this->actingAs($merchant);
        $response = $this->get(route('dashboard.gateways'));

        $response->assertOk();
        $response->assertSee('Gateways');
        $response->assertSee('Coins.ph');
        $response->assertSee('Turn payment gateways on or off for your account');
    }

    public function test_enabled_gateway_shows_as_on_for_merchant(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant']);
        $gateway = Gateway::query()->where('code', 'coins')->firstOrFail();

        MerchantGateway::query()->create([
            'user_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->actingAs($merchant)->get(route('dashboard.gateways'));

        $response->assertOk();
        $response->assertSee('Enabled');
        $response->assertSee('On');
        $response->assertSee('Turn Off');
    }

    public function test_merchant_sees_qrph_gateway_option_when_present(): void
    {
        Gateway::query()->updateOrCreate(['code' => 'qrph'], [
            'code' => 'qrph',
            'name' => 'QRPH',
            'driver_class' => QrphDriver::class,
            'is_global_enabled' => true,
        ]);

        $merchant = User::factory()->create(['role' => 'merchant']);

        $response = $this->actingAs($merchant)->get(route('dashboard.gateways'));

        $response->assertOk();
        $response->assertSee('QRPH');
    }

    public function test_merchant_can_toggle_gateway_from_dashboard(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant']);
        $gateway = Gateway::query()->where('code', 'coins')->firstOrFail();

        MerchantGateway::query()->create([
            'user_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => false,
            'config_json' => [],
        ]);

        Livewire::actingAs($merchant)
            ->test('pages::dashboard.gateways')
            ->set('gatewayStates.'.$gateway->id.'.enabled', true)
            ->call('toggleEnabled', $gateway->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('merchant_gateways', [
            'user_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);
    }

    public function test_merchant_can_toggle_gateway_using_button_action(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant']);
        $gateway = Gateway::query()->where('code', 'coins')->firstOrFail();

        MerchantGateway::query()->create([
            'user_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => false,
            'config_json' => [],
        ]);

        Livewire::actingAs($merchant)
            ->test('pages::dashboard.gateways')
            ->call('toggleFromButton', $gateway->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('merchant_gateways', [
            'user_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);
    }

    public function test_merchant_cannot_enable_gateway_when_globally_disabled(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant']);
        $gateway = Gateway::query()->where('code', 'coins')->firstOrFail();
        $gateway->update(['is_global_enabled' => false]);

        MerchantGateway::query()->create([
            'user_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => false,
            'config_json' => [],
        ]);

        Livewire::actingAs($merchant)
            ->test('pages::dashboard.gateways')
            ->set('gatewayStates.'.$gateway->id.'.enabled', true)
            ->call('toggleEnabled', $gateway->id)
            ->assertHasErrors('gateway.'.$gateway->id);

        $this->assertDatabaseHas('merchant_gateways', [
            'user_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => false,
        ]);
    }

    public function test_gateways_page_does_not_show_credential_forms_or_tunnel_controls(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant']);

        $response = $this->actingAs($merchant)->get(route('dashboard.gateways'));

        $response->assertOk();
        $response->assertDontSee('Save credentials');
        $response->assertDontSee('Test Connection');
        $response->assertDontSee('Tunnel wallet');
    }

    public function test_merchant_can_ping_coins_public_api_from_gateways_header(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant']);

        config()->set('coins.gateway.api_base', 'sandbox');

        Http::fake([
            'https://api.9001.pl-qa.coinsxyz.me/openapi/v1/ping' => Http::response(['ok' => true], 200),
        ]);

        Livewire::actingAs($merchant)
            ->test('pages::dashboard.gateways')
            ->call('pingCoinsPublicApi')
            ->assertSet('coinsPingMessage.type', 'success')
            ->assertSet('coinsPingMessage.message', 'Coins API ping successful (HTTP 200).');
    }
}
