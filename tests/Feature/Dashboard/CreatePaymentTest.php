<?php

namespace Tests\Feature\Dashboard;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\User;
use App\Services\Gateways\Drivers\CoinsDriver;
use App\Services\Gateways\Drivers\MayaDriver;
use App\Services\Gateways\Drivers\QrphDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CreatePaymentTest extends TestCase
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

    public function test_create_payment_form_shows_enabled_gateways_only(): void
    {
        $user = User::factory()->create(['role' => 'merchant']);
        $coinsGateway = Gateway::query()->where('code', 'coins')->firstOrFail();
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => $coinsGateway->id,
            'is_enabled' => true,
            'config_json' => ['client_id' => 'c', 'client_secret' => 's', 'api_base' => 'sandbox'],
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.payments.create'));

        $response->assertOk();
        $response->assertSee('Create Payment');
        $response->assertSee('Coins.ph');
    }

    public function test_create_payment_form_redirects_when_no_enabled_gateways(): void
    {
        $user = User::factory()->create(['role' => 'merchant']);

        $response = $this->actingAs($user)->get(route('dashboard.payments.create'));

        $response->assertOk();
        $response->assertSee('No gateways are enabled');
        $response->assertSee('View gateway status');
        $response->assertDontSee('Configure gateways');
    }

    public function test_store_creates_payment_and_redirects(): void
    {
        Http::fake(['*' => Http::response(['code' => 0, 'data' => ['orderId' => 'ord-1', 'qrCode' => 'qr123']], 200)]);

        $user = User::factory()->create(['role' => 'merchant']);
        $coinsGateway = Gateway::query()->where('code', 'coins')->firstOrFail();
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => $coinsGateway->id,
            'is_enabled' => true,
            'config_json' => ['client_id' => 'c', 'client_secret' => 's', 'api_base' => 'sandbox'],
        ]);

        $response = $this->actingAs($user)->post(route('dashboard.payments.store'), [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'coins',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'gateway_code' => 'coins',
            'amount' => 100,
            'currency' => 'PHP',
            'status' => 'pending',
        ]);
    }

    public function test_store_validates_amount(): void
    {
        $user = User::factory()->create(['role' => 'merchant']);
        $coinsGateway = Gateway::query()->where('code', 'coins')->firstOrFail();
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => $coinsGateway->id,
            'is_enabled' => true,
            'config_json' => ['client_id' => 'c', 'client_secret' => 's', 'api_base' => 'sandbox'],
        ]);

        $response = $this->actingAs($user)->post(route('dashboard.payments.store'), [
            'amount' => '',
            'currency' => 'PHP',
            'gateway' => 'coins',
        ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_store_creates_coins_dynamic_qr_when_maya_option_is_selected(): void
    {
        Http::fake([
            'api.9001.pl-qa.coinsxyz.me/openapi/fiat/v1/generate_qr_code' => Http::response([
                'status' => 0,
                'data' => [
                    'orderId' => 'COINS-MAYA-ORD-123',
                    'qrCode' => '000201010212...',
                ],
            ], 200),
        ]);

        Gateway::query()->create([
            'code' => 'maya',
            'name' => 'Maya',
            'driver_class' => MayaDriver::class,
            'is_global_enabled' => true,
        ]);

        $user = User::factory()->create(['role' => 'merchant']);
        $mayaGateway = Gateway::query()->where('code', 'maya')->firstOrFail();

        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => $mayaGateway->id,
            'is_enabled' => true,
            'config_json' => [
                'provider_mode' => 'native_checkout',
                'client_id' => 'maya-client-id',
                'client_secret' => 'maya-client-secret',
                'api_base' => 'sandbox',
            ],
        ]);

        $response = $this->actingAs($user)->post(route('dashboard.payments.store'), [
            'amount' => 250,
            'currency' => 'PHP',
            'gateway' => 'maya',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/dashboard/payments/', (string) $response->headers->get('Location'));
        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'gateway_code' => 'maya',
            'provider_reference' => 'COINS-MAYA-ORD-123',
            'status' => 'pending',
        ]);
    }

    public function test_create_payment_form_shows_payqrph_when_enabled_for_merchant(): void
    {
        Gateway::query()->updateOrCreate(['code' => 'payqrph'], [
            'code' => 'payqrph',
            'name' => 'PayQRPH',
            'driver_class' => QrphDriver::class,
            'is_global_enabled' => true,
        ]);

        $user = User::factory()->create(['role' => 'merchant']);
        $payQrphGateway = Gateway::query()->where('code', 'payqrph')->firstOrFail();
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => $payQrphGateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.payments.create'));

        $response->assertOk();
        $response->assertSee('PayQRPH');
        $response->assertSee('value="payqrph"', false);
    }

    public function test_create_payment_form_shows_qrph_when_enabled_for_merchant(): void
    {
        Gateway::query()->updateOrCreate(['code' => 'qrph'], [
            'code' => 'qrph',
            'name' => 'QRPH',
            'driver_class' => QrphDriver::class,
            'is_global_enabled' => true,
        ]);

        $user = User::factory()->create(['role' => 'merchant']);
        $qrphGateway = Gateway::query()->where('code', 'qrph')->firstOrFail();
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => $qrphGateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.payments.create'));

        $response->assertOk();
        $response->assertSee('QRPH');
        $response->assertSee('value="qrph"', false);
    }
}
