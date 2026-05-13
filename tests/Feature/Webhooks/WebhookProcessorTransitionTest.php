<?php

namespace Tests\Feature\Webhooks;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\Payment;
use App\Models\User;
use App\Services\Coins\CoinsSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookProcessorTransitionTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'test-webhook-secret';

    private CoinsSignatureService $signatureService;

    private Gateway $coinsGateway;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('coins.webhook.allow_dev_bypass', false);
        $this->app['config']->set('coins.webhook.secret', self::WEBHOOK_SECRET);
        $this->signatureService = new CoinsSignatureService;
        $this->coinsGateway = Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => 'App\Services\Gateways\Drivers\CoinsDriver',
            'is_global_enabled' => true,
        ]);
        $this->user = User::factory()->create();
        MerchantGateway::query()->create([
            'merchant_id' => $this->user->id,
            'gateway_id' => $this->coinsGateway->id,
            'is_enabled' => true,
            'config_json' => [
                'client_id' => 'client',
                'client_secret' => 'secret',
                'api_base' => 'sandbox',
                'webhook_secret' => self::WEBHOOK_SECRET,
            ],
        ]);
    }

    public function test_provisioning_payment_transitions_to_paid_on_succeeded_webhook(): void
    {
        $payment = Payment::factory()->create([
            'merchant_id' => $this->user->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'ORDER-PROVISIONING-001',
            'status' => 'provisioning',
            'paid_at' => null,
        ]);

        $payload = [
            'referenceId' => 'ORDER-PROVISIONING-001',
            'status' => 'SUCCEEDED',
            'settleDate' => 1707475200000,
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $signed = $this->signatureService->sign($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks?provider=coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(1707475200, $payment->paid_at->timestamp);
    }

    public function test_provisioning_payment_transitions_to_failed_on_failed_webhook(): void
    {
        $payment = Payment::factory()->create([
            'merchant_id' => $this->user->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'ORDER-PROVISIONING-002',
            'status' => 'provisioning',
            'paid_at' => null,
        ]);

        $payload = [
            'referenceId' => 'ORDER-PROVISIONING-002',
            'status' => 'EXPIRED',
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $signed = $this->signatureService->sign($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks?provider=coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        $payment->refresh();
        $this->assertSame('failed', $payment->status);
    }

    public function test_provisioning_failed_payment_cannot_transition_to_paid(): void
    {
        $payment = Payment::factory()->create([
            'merchant_id' => $this->user->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'ORDER-PROVISIONING-003',
            'status' => 'provisioning_failed',
            'paid_at' => null,
        ]);

        $payload = [
            'referenceId' => 'ORDER-PROVISIONING-003',
            'status' => 'SUCCEEDED',
            'settleDate' => 1707475200000,
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $signed = $this->signatureService->sign($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks?provider=coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);

        $payment->refresh();
        $this->assertSame('provisioning_failed', $payment->status);
        $this->assertNull($payment->paid_at);
    }
}
