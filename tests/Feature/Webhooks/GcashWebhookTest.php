<?php

namespace Tests\Feature\Webhooks;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GcashWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Gateway $gcashGateway;

    private User $user;

    private MerchantGateway $merchantGateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gcashGateway = Gateway::query()->create([
            'code' => 'gcash',
            'name' => 'GCash',
            'driver_class' => 'App\Services\Gateways\Drivers\GcashDriver',
            'is_global_enabled' => true,
        ]);
        $this->user = User::factory()->create();
        $this->merchantGateway = MerchantGateway::query()->create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gcashGateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);
        $this->app['config']->set('gcash.webhook.allow_dev_bypass', true);
    }

    private function paymentPaidPayload(string $eventId, string $paymentId, string $externalRef, int $amount = 10000): array
    {
        $now = (int) time();

        return [
            'data' => [
                'id' => $eventId,
                'type' => 'event',
                'attributes' => [
                    'type' => 'payment.paid',
                    'livemode' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'data' => [
                        'id' => $paymentId,
                        'type' => 'payment',
                        'attributes' => [
                            'amount' => $amount,
                            'currency' => 'PHP',
                            'status' => 'paid',
                            'external_reference_number' => $externalRef,
                            'paid_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_webhook_updates_payment_to_paid(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'gateway_code' => 'gcash',
            'provider_reference' => 'ORDER-GCASH-001',
            'status' => 'pending',
        ]);

        $payload = $this->paymentPaidPayload(
            'evt_test123',
            'pay_test456',
            'ORDER-GCASH-001'
        );

        $response = $this->postJson('/api/webhooks/gcash', $payload);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_webhook_uses_payment_id_as_reference_when_no_external_ref(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'gateway_code' => 'gcash',
            'provider_reference' => 'pay_test789',
            'status' => 'pending',
        ]);

        $now = (int) time();
        $payload = [
            'data' => [
                'id' => 'evt_abc',
                'type' => 'event',
                'attributes' => [
                    'type' => 'payment.paid',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'data' => [
                        'id' => 'pay_test789',
                        'type' => 'payment',
                        'attributes' => [
                            'amount' => 5000,
                            'currency' => 'PHP',
                            'status' => 'paid',
                            'paid_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/gcash', $payload);

        $response->assertStatus(200);
        $payment->refresh();
        $this->assertSame('paid', $payment->status);
    }

    public function test_webhook_duplicate_event_returns_200_without_reprocessing(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'gateway_code' => 'gcash',
            'provider_reference' => 'ORDER-DUP',
            'status' => 'pending',
        ]);

        $payload = $this->paymentPaidPayload('evt_dup', 'pay_dup', 'ORDER-DUP');

        $this->postJson('/api/webhooks/gcash', $payload);
        $payment->refresh();
        $this->assertSame('paid', $payment->status);

        $response = $this->postJson('/api/webhooks/gcash', $payload);
        $response->assertStatus(200);
        $response->assertJson(['received' => true]);
    }

    public function test_webhook_returns_200_when_payment_not_found(): void
    {
        $payload = $this->paymentPaidPayload('evt_none', 'pay_none', 'non-existent-ref');

        $response = $this->postJson('/api/webhooks/gcash', $payload);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);
    }

    public function test_webhook_rejects_when_timestamp_too_old(): void
    {
        $this->app['config']->set('gcash.webhook.allow_dev_bypass', true);
        $this->app['config']->set('gcash.webhook.max_age', 60);

        $oldTs = (int) time() - 120;
        $payload = [
            'data' => [
                'id' => 'evt_old',
                'type' => 'event',
                'attributes' => [
                    'type' => 'payment.paid',
                    'created_at' => $oldTs,
                    'updated_at' => $oldTs,
                    'data' => [
                        'id' => 'pay_old',
                        'type' => 'payment',
                        'attributes' => [
                            'amount' => 100,
                            'currency' => 'PHP',
                            'status' => 'paid',
                            'paid_at' => $oldTs,
                            'created_at' => $oldTs,
                            'updated_at' => $oldTs,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/gcash', $payload);

        $response->assertStatus(401);
    }
}
