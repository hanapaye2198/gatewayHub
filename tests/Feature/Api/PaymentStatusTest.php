<?php

namespace Tests\Feature\Api;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\Payment;
use App\Models\User;
use App\Services\Gateways\Drivers\CoinsDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentStatusTest extends TestCase
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

    public function test_status_returns_success_for_paid_payment(): void
    {
        $user = User::factory()->create(['api_key' => 'key-1']);
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => Gateway::first()->id,
            'is_enabled' => true,
            'config_json' => ['client_id' => 'c', 'client_secret' => 's', 'api_base' => 'sandbox'],
        ]);

        $payment = Payment::factory()->paid()->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/payments/'.$payment->id.'/status', [
            'Authorization' => 'Bearer key-1',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'success');
    }

    public function test_status_returns_pending_for_pending_payment(): void
    {
        $user = User::factory()->create(['api_key' => 'key-2']);
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => Gateway::first()->id,
            'is_enabled' => true,
            'config_json' => ['client_id' => 'c', 'client_secret' => 's', 'api_base' => 'sandbox'],
        ]);

        $payment = Payment::factory()->create(['user_id' => $user->id, 'status' => 'pending']);

        $response = $this->getJson('/api/payments/'.$payment->id.'/status', [
            'Authorization' => 'Bearer key-2',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'pending');
    }

    public function test_status_returns_failed_for_failed_payment(): void
    {
        $user = User::factory()->create(['api_key' => 'key-3']);
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => Gateway::first()->id,
            'is_enabled' => true,
            'config_json' => ['client_id' => 'c', 'client_secret' => 's', 'api_base' => 'sandbox'],
        ]);

        $payment = Payment::factory()->failed()->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/payments/'.$payment->id.'/status', [
            'Authorization' => 'Bearer key-3',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'failed');
    }

    public function test_status_returns_404_for_other_merchant_payment(): void
    {
        $user = User::factory()->create(['api_key' => 'key-4']);
        $otherUser = User::factory()->create(['api_key' => 'key-other']);
        MerchantGateway::query()->create([
            'user_id' => $user->id,
            'gateway_id' => Gateway::first()->id,
            'is_enabled' => true,
            'config_json' => ['client_id' => 'c', 'client_secret' => 's', 'api_base' => 'sandbox'],
        ]);

        $payment = Payment::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson('/api/payments/'.$payment->id.'/status', [
            'Authorization' => 'Bearer key-4',
        ]);

        $response->assertStatus(404);
    }

    public function test_status_returns_401_without_bearer_token(): void
    {
        $payment = Payment::factory()->create();

        $response = $this->getJson('/api/payments/'.$payment->id.'/status');

        $response->assertStatus(401);
    }
}
