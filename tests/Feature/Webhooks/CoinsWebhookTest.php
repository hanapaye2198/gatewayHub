<?php

namespace Tests\Feature\Webhooks;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Coins\CoinsSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoinsWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'test-webhook-secret';

    private CoinsSignatureService $signatureService;

    private Gateway $coinsGateway;

    private User $user;

    private MerchantGateway $merchantGateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signatureService = new CoinsSignatureService;
        $this->coinsGateway = Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => 'App\Services\Gateways\Drivers\CoinsDriver',
            'is_global_enabled' => true,
        ]);
        $this->user = User::factory()->create();
        $this->merchantGateway = MerchantGateway::query()->create([
            'user_id' => $this->user->id,
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

    public function test_webhook_returns_401_when_signature_header_missing(): void
    {
        $payload = [
            'referenceId' => 'ORDER-001',
            'status' => 'SUCCEEDED',
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $body = json_encode($payload);

        $response = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid signature.']);
    }

    public function test_webhook_returns_401_when_signature_invalid(): void
    {
        $payload = [
            'referenceId' => 'ORDER-001',
            'status' => 'SUCCEEDED',
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];

        $response = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => 'invalid-signature',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid signature.']);
    }

    public function test_webhook_returns_200_when_payment_not_found(): void
    {
        $payload = [
            'referenceId' => 'non-existent-ref',
            'status' => 'SUCCEEDED',
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $signed = $this->signatureService->sign($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);
    }

    public function test_webhook_returns_200_and_updates_to_paid_when_succeeded(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'ORDER-001',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $settleDate = 1707475200000;
        $payload = [
            'referenceId' => 'ORDER-001',
            'status' => 'SUCCEEDED',
            'amount' => '500.00',
            'currency' => 'PHP',
            'settleDate' => $settleDate,
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $signed = $this->signatureService->sign($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(1707475200, $payment->paid_at->timestamp);

        $event = WebhookEvent::query()->where('event_id', 'like', 'ORDER-001:%')->first();
        $this->assertNotNull($event);
        $this->assertSame('processed', $event->status);
        $this->assertNotNull($event->processed_at);
        $this->assertSame($payload['referenceId'], $event->payload['referenceId'] ?? null);
        $this->assertArrayHasKey('content-type', $event->headers ?? []);
    }

    public function test_webhook_idempotent_when_already_paid(): void
    {
        $payment = Payment::factory()->paid()->create([
            'user_id' => $this->user->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'ORDER-002',
        ]);
        $paidAt = $payment->paid_at;

        $payload = [
            'referenceId' => 'ORDER-002',
            'status' => 'SUCCEEDED',
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $signed = $this->signatureService->sign($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertTrue($paidAt->equalTo($payment->paid_at));
    }

    public function test_webhook_updates_to_failed_when_failed(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'ORDER-003',
            'status' => 'pending',
        ]);

        $payload = [
            'referenceId' => 'ORDER-003',
            'status' => 'FAILED',
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $signed = $this->signatureService->sign($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);
        $payment->refresh();
        $this->assertSame('failed', $payment->status);
    }

    public function test_webhook_updates_to_failed_when_expired(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'ORDER-EXP',
            'status' => 'pending',
        ]);

        $payload = [
            'referenceId' => 'ORDER-EXP',
            'status' => 'EXPIRED',
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $signed = $this->signatureService->sign($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);
        $payment->refresh();
        $this->assertSame('failed', $payment->status);
    }

    public function test_webhook_merges_payload_into_raw_response(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'ORDER-MERGE',
            'status' => 'pending',
            'raw_response' => ['orderId' => 'ORDER-MERGE', 'qrCode' => 'existing-qr'],
        ]);

        $payload = [
            'referenceId' => 'ORDER-MERGE',
            'status' => 'SUCCEEDED',
            'amount' => '500.00',
            'settleDate' => 1707475200000,
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $signed = $this->signatureService->sign($payload, self::WEBHOOK_SECRET);

        $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $raw = $payment->raw_response;
        $this->assertIsArray($raw);
        $this->assertArrayHasKey('orderId', $raw);
        $this->assertSame('ORDER-MERGE', $raw['orderId']);
        $this->assertArrayHasKey('referenceId', $raw);
        $this->assertSame('ORDER-MERGE', $raw['referenceId']);
        $this->assertArrayHasKey('status', $raw);
        $this->assertSame('SUCCEEDED', $raw['status']);
    }

    public function test_webhook_returns_200_for_empty_body(): void
    {
        $response = $this->call(
            'POST',
            '/api/webhooks/coins',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            ''
        );

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);
    }

    public function test_webhook_returns_401_when_timestamp_too_old(): void
    {
        $this->app['config']->set('coins.webhook.max_age', 60);

        $oldTimestamp = (string) (int) ((microtime(true) - 120) * 1000);
        $payload = [
            'referenceId' => 'non-existent-ref',
            'status' => 'SUCCEEDED',
            'timestamp' => $oldTimestamp,
        ];
        $signed = $this->signatureService->sign($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid signature.']);
    }

    public function test_webhook_returns_401_when_timestamp_too_far_in_future(): void
    {
        $this->app['config']->set('coins.webhook.max_age', 60);

        $futureTimestamp = (string) (int) ((microtime(true) + 600) * 1000);
        $payload = [
            'referenceId' => 'non-existent-ref',
            'status' => 'SUCCEEDED',
            'timestamp' => $futureTimestamp,
        ];
        $signed = $this->signatureService->sign($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid signature.']);
    }

    public function test_webhook_duplicate_event_returns_200_without_reprocessing(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'ORDER-DUP',
            'status' => 'pending',
        ]);

        $payload = [
            'referenceId' => 'ORDER-DUP',
            'status' => 'SUCCEEDED',
            'settleDate' => 1707475200000,
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $signed = $this->signatureService->sign($payload, self::WEBHOOK_SECRET);

        $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);

        $response = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
    }

    public function test_webhook_does_not_require_authentication(): void
    {
        $payload = [
            'referenceId' => 'no-auth',
            'status' => 'SUCCEEDED',
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $signed = $this->signatureService->sign($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);
    }
}
