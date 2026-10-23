<?php

namespace Tests\Feature\Dashboard;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\User;
use App\Services\Gateways\Drivers\PayMongoDriver;
use App\Services\Gateways\PayMongoMethodCapabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class GatewaysPageTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $payMongoMethods = ['qrph', 'gcash', 'paymaya', 'grab_pay', 'shopeepay'];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::fake(function ($request) {
            if (parse_url($request->url(), PHP_URL_PATH) !== '/v1/merchants/capabilities/payment_methods') {
                return Http::response([], 404);
            }

            return Http::response([
                'data' => ['attributes' => ['payment_methods' => $this->payMongoMethods]],
            ], 200);
        });

        Gateway::query()->updateOrCreate(['code' => 'paymongo'], [
            'name' => 'PayMongo',
            'driver_class' => PayMongoDriver::class,
            'is_global_enabled' => true,
        ]);

        foreach ([
            'gcash' => 'GCash',
            'maya' => 'Maya',
            'grab_pay' => 'GrabPay',
            'shopeepay' => 'ShopeePay',
            'qrph' => 'QR Ph',
        ] as $code => $name) {
            Gateway::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'driver_class' => PayMongoDriver::class,
                'is_global_enabled' => true,
            ]);
        }

        Gateway::query()->updateOrCreate(['code' => 'coins'], [
            'name' => 'Coins.ph',
            'driver_class' => PayMongoDriver::class,
            'is_global_enabled' => false,
        ]);
    }

    public function test_merchant_sees_paymongo_payment_methods_without_coins_ph(): void
    {
        $merchant = User::factory()->create();

        $response = $this->actingAs($merchant)->get(route('dashboard.gateways'));

        $response->assertOk();
        $response->assertSee('Gateways');
        $response->assertSee('GCash');
        $response->assertSee('Maya');
        $response->assertSee('GrabPay');
        $response->assertSee('ShopeePay');
        $response->assertSee('QR Ph');
        $response->assertDontSee('Coins.ph');
        $response->assertDontSee('PayPal');
        $response->assertSee('Enable or disable payment gateways for your merchant account');
    }

    public function test_enabled_gateway_shows_as_on_for_merchant(): void
    {
        $merchant = User::factory()->create();
        $gateway = Gateway::query()->where('code', 'gcash')->firstOrFail();

        MerchantGateway::query()->create([
            'merchant_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->actingAs($merchant)->get(route('dashboard.gateways'));

        $response->assertOk();
        $response->assertSee('Active');
        $response->assertSee('Available for payments');
        $response->assertSee('Disable');
    }

    public function test_enabled_merchant_method_is_not_shown_as_accepting_payments_when_paymongo_has_not_activated_it(): void
    {
        Cache::flush();
        $this->payMongoMethods = ['qrph'];
        Cache::flush();
        $this->assertSame('unavailable', app(PayMongoMethodCapabilityService::class)->statusForGateway('gcash'));

        $merchant = User::factory()->create();
        $gateway = Gateway::query()->where('code', 'gcash')->firstOrFail();
        MerchantGateway::query()->create([
            'merchant_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->actingAs($merchant)->get(route('dashboard.gateways'));

        $response->assertOk();
        $response->assertSee('PayMongo Off');
        $response->assertSee('Not enabled on the KemoPay PayMongo account');
        $response->assertDontSee('Available for payments');
    }

    public function test_merchant_sees_qrph_gateway_option_when_present(): void
    {
        $merchant = User::factory()->create();

        $response = $this->actingAs($merchant)->get(route('dashboard.gateways'));

        $response->assertOk();
        $response->assertSee('QR Ph');
    }

    public function test_merchant_can_toggle_gateway_from_dashboard(): void
    {
        $merchant = User::factory()->create();
        $gateway = Gateway::query()->where('code', 'gcash')->firstOrFail();

        MerchantGateway::query()->create([
            'merchant_id' => $merchant->id,
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
            'merchant_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);
    }

    public function test_merchant_can_toggle_gateway_using_button_action(): void
    {
        $merchant = User::factory()->create();
        $gateway = Gateway::query()->where('code', 'gcash')->firstOrFail();

        MerchantGateway::query()->create([
            'merchant_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => false,
            'config_json' => [],
        ]);

        Livewire::actingAs($merchant)
            ->test('pages::dashboard.gateways')
            ->call('toggleFromButton', $gateway->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('merchant_gateways', [
            'merchant_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
        ]);
    }

    public function test_merchant_cannot_enable_gateway_when_globally_disabled(): void
    {
        $merchant = User::factory()->create();
        $gateway = Gateway::query()->where('code', 'gcash')->firstOrFail();
        $gateway->update(['is_global_enabled' => false]);

        MerchantGateway::query()->create([
            'merchant_id' => $merchant->id,
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
            'merchant_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => false,
        ]);
    }

    public function test_merchant_cannot_enable_method_missing_from_paymongo_capabilities(): void
    {
        Cache::flush();
        $this->payMongoMethods = ['qrph'];
        Cache::flush();
        $this->assertSame('unavailable', app(PayMongoMethodCapabilityService::class)->statusForGateway('gcash'));

        $merchant = User::factory()->create();
        $gateway = Gateway::query()->where('code', 'gcash')->firstOrFail();
        MerchantGateway::query()->create([
            'merchant_id' => $merchant->id,
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
            'merchant_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => false,
        ]);
    }

    public function test_gateways_page_does_not_show_credential_forms_or_tunnel_controls(): void
    {
        $merchant = User::factory()->create();

        $response = $this->actingAs($merchant)->get(route('dashboard.gateways'));

        $response->assertOk();
        $response->assertDontSee('Save credentials');
        $response->assertDontSee('Test Connection');
        $response->assertDontSee('Tunnel wallet');
    }
}
