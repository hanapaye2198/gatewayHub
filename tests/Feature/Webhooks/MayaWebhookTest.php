<?php

namespace Tests\Feature\Webhooks;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MayaWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Gateway $mayaGateway;

    private User $user;

    private MerchantGateway $merchantGateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mayaGateway = Gateway::query()->create([
            'code' => 'maya',
            'name' => 'Maya',
            'driver_class' => 'App\Services\Gateways\Drivers\MayaDriver',
            'is_global_enabled' => true,
        ]);
        $this->user = User::factory()->create();
        $this->merchantGateway = MerchantGateway::query()->create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->mayaGateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);
        $this->app['config']->set('maya.webhook.allow_dev_bypass', true);
    }

    private function paymentSuccessPayload(string $id, string $requestRef, int $amount = 1000): array
    {
        $now = now()->toIso8601String();

        return [
            'id' => $id,
            'isPaid' => true,
            'status' => 'PAYMENT_SUCCESS',
            'amount' => (string) $amount,
            'currency' => 'PHP',
            'requestReferenceNumber' => $requestRef,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
    }

    public function test_webhook_updates_payment_to_paid(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'gateway_code' => 'maya',
            'provider_reference' => 'ORDER-MAYA-001',
            'status' => 'pending',
        ]);

        $payload = $this->paymentSuccessPayload('maya-pay-123', 'ORDER-MAYA-001');

        $response = $this->postJson('/api/webhooks/maya', $payload);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_webhook_uses_id_as_reference_when_no_request_ref(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'gateway_code' => 'maya',
            'provider_reference' => 'maya-uuid-456',
            'status' => 'pending',
        ]);

        $now = now()->toIso8601String();
        $payload = [
            'id' => 'maya-uuid-456',
            'isPaid' => true,
            'status' => 'PAYMENT_SUCCESS',
            'amount' => '500',
            'currency' => 'PHP',
            'createdAt' => $now,
            'updatedAt' => $now,
        ];

        $response = $this->postJson('/api/webhooks/maya', $payload);

        $response->assertStatus(200);
        $payment->refresh();
        $this->assertSame('paid', $payment->status);
    }

    public function test_webhook_updates_to_failed_when_payment_failed(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'gateway_code' => 'maya',
            'provider_reference' => 'ORDER-FAIL',
            'status' => 'pending',
        ]);

        $now = now()->toIso8601String();
        $payload = [
            'id' => 'maya-fail-789',
            'isPaid' => false,
            'status' => 'PAYMENT_FAILED',
            'amount' => '100',
            'currency' => 'PHP',
            'requestReferenceNumber' => 'ORDER-FAIL',
            'createdAt' => $now,
            'updatedAt' => $now,
        ];

        $response = $this->postJson('/api/webhooks/maya', $payload);

        $response->assertStatus(200);
        $payment->refresh();
        $this->assertSame('failed', $payment->status);
    }

    public function test_webhook_duplicate_event_returns_200_without_reprocessing(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'gateway_code' => 'maya',
            'provider_reference' => 'ORDER-DUP',
            'status' => 'pending',
        ]);

        $payload = $this->paymentSuccessPayload('maya-dup-id', 'ORDER-DUP');

        $this->postJson('/api/webhooks/maya', $payload);
        $payment->refresh();
        $this->assertSame('paid', $payment->status);

        $response = $this->postJson('/api/webhooks/maya', $payload);
        $response->assertStatus(200);
        $response->assertJson(['received' => true]);
    }

    public function test_webhook_returns_200_when_payment_not_found(): void
    {
        $payload = $this->paymentSuccessPayload('maya-none', 'non-existent-ref');

        $response = $this->postJson('/api/webhooks/maya', $payload);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);
    }

    public function test_webhook_rejects_when_timestamp_too_old(): void
    {
        $this->app['config']->set('maya.webhook.max_age', 60);

        $oldTime = now()->subMinutes(2)->toIso8601String();
        $payload = [
            'id' => 'maya-old',
            'status' => 'PAYMENT_SUCCESS',
            'amount' => '100',
            'currency' => 'PHP',
            'requestReferenceNumber' => 'old-ref',
            'createdAt' => $oldTime,
            'updatedAt' => $oldTime,
        ];

        $response = $this->postJson('/api/webhooks/maya', $payload);

        $response->assertStatus(401);
    }
}
