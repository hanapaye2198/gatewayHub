<?php

namespace Tests\Feature\Dashboard;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\User;
use App\Services\Gateways\Drivers\CoinsDriver;
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
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => Gateway::first()->id,
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
        $response->assertSee('Configure gateways');
    }

    public function test_store_creates_payment_and_redirects(): void
    {
        Http::fake(['*' => Http::response(['code' => 0, 'data' => ['orderId' => 'ord-1', 'qrCode' => 'qr123']], 200)]);

        $user = User::factory()->create(['role' => 'merchant']);
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => Gateway::first()->id,
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
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => Gateway::first()->id,
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
}
