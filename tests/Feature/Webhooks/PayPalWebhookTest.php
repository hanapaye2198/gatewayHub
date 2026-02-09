<?php

namespace Tests\Feature\Webhooks;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayPalWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Gateway $paypalGateway;

    private User $user;

    private MerchantGateway $merchantGateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paypalGateway = Gateway::query()->create([
            'code' => 'paypal',
            'name' => 'PayPal',
            'driver_class' => 'App\Services\Gateways\Drivers\PaypalDriver',
            'is_global_enabled' => true,
        ]);
        $this->user = User::factory()->create();
        $this->merchantGateway = MerchantGateway::query()->create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->paypalGateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);
        $this->app['config']->set('paypal.webhook.allow_dev_bypass', true);
    }

    private function paymentCaptureCompletedPayload(string $eventId, string $invoiceId, string $amount = '100.00'): array
    {
        $now = now()->toIso8601String();

        return [
            'id' => $eventId,
            'create_time' => $now,
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource_type' => 'capture',
            'resource' => [
                'id' => 'capture-'.fake()->uuid(),
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => $amount,
                ],
                'invoice_id' => $invoiceId,
                'status' => 'COMPLETED',
            ],
        ];
    }

    public function test_webhook_updates_payment_to_paid(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'gateway_code' => 'paypal',
            'provider_reference' => 'INV-PAYPAL-001',
            'status' => 'pending',
        ]);

        $payload = $this->paymentCaptureCompletedPayload('evt_123', 'INV-PAYPAL-001');

        $response = $this->postJson('/api/webhooks/paypal', $payload);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_webhook_uses_resource_id_when_no_invoice_id(): void
    {
        $captureId = '8AB12345CD67890EF';
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'gateway_code' => 'paypal',
            'provider_reference' => $captureId,
            'status' => 'pending',
        ]);

        $now = now()->toIso8601String();
        $payload = [
            'id' => 'evt_456',
            'create_time' => $now,
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => $captureId,
                'amount' => [
                    'currency_code' => 'PHP',
                    'value' => '500.00',
                ],
                'status' => 'COMPLETED',
            ],
        ];

        $response = $this->postJson('/api/webhooks/paypal', $payload);

        $response->assertStatus(200);
        $payment->refresh();
        $this->assertSame('paid', $payment->status);
    }

    public function test_webhook_updates_to_failed_when_capture_denied(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'gateway_code' => 'paypal',
            'provider_reference' => 'INV-DENIED',
            'status' => 'pending',
        ]);

        $now = now()->toIso8601String();
        $payload = [
            'id' => 'evt_denied',
            'create_time' => $now,
            'event_type' => 'PAYMENT.CAPTURE.DENIED',
            'resource' => [
                'id' => 'cap_denied',
                'invoice_id' => 'INV-DENIED',
                'amount' => ['currency_code' => 'USD', 'value' => '50.00'],
                'status' => 'DECLINED',
            ],
        ];

        $response = $this->postJson('/api/webhooks/paypal', $payload);

        $response->assertStatus(200);
        $payment->refresh();
        $this->assertSame('failed', $payment->status);
    }

    public function test_webhook_duplicate_event_returns_200_without_reprocessing(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'gateway_code' => 'paypal',
            'provider_reference' => 'INV-DUP',
            'status' => 'pending',
        ]);

        $payload = $this->paymentCaptureCompletedPayload('evt_dup_unique', 'INV-DUP');

        $this->postJson('/api/webhooks/paypal', $payload);
        $payment->refresh();
        $this->assertSame('paid', $payment->status);

        $response = $this->postJson('/api/webhooks/paypal', $payload);
        $response->assertStatus(200);
        $response->assertJson(['received' => true]);
    }

    public function test_webhook_returns_200_when_payment_not_found(): void
    {
        $payload = $this->paymentCaptureCompletedPayload('evt_none', 'non-existent-invoice');

        $response = $this->postJson('/api/webhooks/paypal', $payload);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);
    }

    public function test_webhook_rejects_when_timestamp_too_old(): void
    {
        $this->app['config']->set('paypal.webhook.max_age', 60);

        $oldTime = now()->subMinutes(2)->toIso8601String();
        $payload = [
            'id' => 'evt_old',
            'create_time' => $oldTime,
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'cap_old',
                'invoice_id' => 'old-inv',
                'amount' => ['currency_code' => 'USD', 'value' => '10'],
            ],
        ];

        $response = $this->postJson('/api/webhooks/paypal', $payload);

        $response->assertStatus(401);
    }

    public function test_webhook_returns_401_when_signature_verification_fails(): void
    {
        $this->app['config']->set('paypal.webhook.allow_dev_bypass', false);

        $payload = $this->paymentCaptureCompletedPayload('evt_no_sig', 'INV-001');

        $response = $this->postJson('/api/webhooks/paypal', $payload);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid signature.']);
    }
}
