<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\Payment;
use App\Models\User;
use App\Services\Coins\CoinsSignatureService;
use App\Services\Gateways\Drivers\CoinsDriver;
use App\Services\Gateways\Drivers\QrphDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QrphGatewayFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_payment_creation_supports_qrph_via_coins_dynamic_qr(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 0,
                'data' => [
                    'orderId' => 'QRPH-ORD-001',
                    'qrCode' => '000201010212...',
                ],
            ], 200),
        ]);

        Gateway::query()->updateOrCreate(['code' => 'coins'], [
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => CoinsDriver::class,
            'is_global_enabled' => true,
        ]);

        $gateway = Gateway::query()->updateOrCreate(['code' => 'qrph'], [
            'code' => 'qrph',
            'name' => 'QRPH',
            'driver_class' => QrphDriver::class,
            'is_global_enabled' => true,
        ]);

        $merchant = User::factory()->create([
            'role' => 'merchant',
            'api_key' => 'qrph-api-key',
        ]);

        MerchantGateway::query()->create([
            'user_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => 150,
            'currency' => 'PHP',
            'gateway' => 'qrph',
            'reference' => 'QRPH-REF-001',
        ], [
            'Authorization' => 'Bearer qrph-api-key',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.gateway', 'qrph');
        $this->assertDatabaseHas('payments', [
            'user_id' => $merchant->id,
            'gateway_code' => 'qrph',
            'status' => 'pending',
        ]);
    }

    public function test_coins_webhook_updates_qrph_payment_status(): void
    {
        config()->set('coins.webhook.allow_dev_bypass', false);
        config()->set('coins.webhook.secret', 'qrph-coins-webhook-secret');

        Payment::factory()->create([
            'gateway_code' => 'qrph',
            'provider_reference' => 'QRPH-WEBHOOK-001',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $payload = [
            'referenceId' => 'QRPH-WEBHOOK-001',
            'status' => 'SUCCEEDED',
            'amount' => '200.00',
            'currency' => 'PHP',
            'settleDate' => (string) (time() * 1000),
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $signed = (new CoinsSignatureService)->sign($payload, 'qrph-coins-webhook-secret');

        $response = $this->postJson('/api/webhooks?provider=coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payments', [
            'gateway_code' => 'qrph',
            'provider_reference' => 'QRPH-WEBHOOK-001',
            'status' => 'paid',
        ]);
    }

    public function test_api_payment_creation_supports_payqrph_via_coins_dynamic_qr(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 0,
                'data' => [
                    'orderId' => 'PAYQRPH-ORD-001',
                    'qrCode' => '000201010212...',
                ],
            ], 200),
        ]);

        Gateway::query()->updateOrCreate(['code' => 'coins'], [
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => CoinsDriver::class,
            'is_global_enabled' => true,
        ]);

        $gateway = Gateway::query()->updateOrCreate(['code' => 'payqrph'], [
            'code' => 'payqrph',
            'name' => 'PayQRPH',
            'driver_class' => QrphDriver::class,
            'is_global_enabled' => true,
        ]);

        $merchant = User::factory()->create([
            'role' => 'merchant',
            'api_key' => 'payqrph-api-key',
        ]);

        MerchantGateway::query()->create([
            'user_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => 220,
            'currency' => 'PHP',
            'gateway' => 'payqrph',
            'reference' => 'PAYQRPH-REF-001',
        ], [
            'Authorization' => 'Bearer payqrph-api-key',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.gateway', 'payqrph');
        $this->assertDatabaseHas('payments', [
            'user_id' => $merchant->id,
            'gateway_code' => 'payqrph',
            'status' => 'pending',
        ]);
    }

    public function test_coins_webhook_updates_payqrph_payment_status(): void
    {
        config()->set('coins.webhook.allow_dev_bypass', false);
        config()->set('coins.webhook.secret', 'payqrph-coins-webhook-secret');

        Payment::factory()->create([
            'gateway_code' => 'payqrph',
            'provider_reference' => 'PAYQRPH-WEBHOOK-001',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $payload = [
            'referenceId' => 'PAYQRPH-WEBHOOK-001',
            'status' => 'SUCCEEDED',
            'amount' => '200.00',
            'currency' => 'PHP',
            'settleDate' => (string) (time() * 1000),
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $signed = (new CoinsSignatureService)->sign($payload, 'payqrph-coins-webhook-secret');

        $response = $this->postJson('/api/webhooks?provider=coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payments', [
            'gateway_code' => 'payqrph',
            'provider_reference' => 'PAYQRPH-WEBHOOK-001',
            'status' => 'paid',
        ]);
    }

    public function test_coins_webhook_updates_maya_labeled_payment_status(): void
    {
        config()->set('coins.webhook.allow_dev_bypass', false);
        config()->set('coins.webhook.secret', 'qrph-coins-webhook-secret');

        Payment::factory()->create([
            'gateway_code' => 'maya',
            'provider_reference' => 'COINS-MAYA-WEBHOOK-001',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $payload = [
            'referenceId' => 'COINS-MAYA-WEBHOOK-001',
            'status' => 'SUCCEEDED',
            'amount' => '200.00',
            'currency' => 'PHP',
            'settleDate' => (string) (time() * 1000),
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $signed = (new CoinsSignatureService)->sign($payload, 'qrph-coins-webhook-secret');

        $response = $this->postJson('/api/webhooks?provider=coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payments', [
            'gateway_code' => 'maya',
            'provider_reference' => 'COINS-MAYA-WEBHOOK-001',
            'status' => 'paid',
        ]);
    }
}
