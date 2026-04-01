<?php

namespace Tests\Feature\Webhooks;

use App\Jobs\ProcessWebhookJob;
use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Coins\CoinsSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
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
        $this->merchantGateway = MerchantGateway::query()->create([
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

    public function test_webhook_returns_401_when_signature_header_missing(): void
    {
        $payload = [
            'referenceId' => 'ORDER-001',
            'status' => 'SUCCEEDED',
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $body = json_encode($payload);

        $response = $this->postJson('/api/webhooks?provider=coins', $payload, [
            'Content-Type' => 'application/json',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid signature.']);
    }

    public function test_webhook_accepts_client_secret_fallback_when_webhook_secret_not_set(): void
    {
        $this->app['config']->set('coins.webhook.secret', '');
        $this->coinsGateway->update([
            'config_json' => [
                'client_id' => 'client',
                'client_secret' => self::WEBHOOK_SECRET,
                'api_base' => 'sandbox',
            ],
        ]);

        $payment = Payment::factory()->create([
            'merchant_id' => $this->user->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'ORDER-FALLBACK-001',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $payload = [
            'referenceId' => 'ORDER-FALLBACK-001',
            'status' => 'SUCCEEDED',
            'settleDate' => 1707475200000,
        ];
        $signed = $this->signatureService->signWebhook($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'Signature' => $signed['signature'],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
    }

    public function test_webhook_returns_401_when_signature_invalid(): void
    {
        Log::spy();

        $payload = [
            'referenceId' => 'ORDER-001',
            'status' => 'SUCCEEDED',
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];

        $response = $this->postJson('/api/webhooks?provider=coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => 'invalid-signature',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid signature.']);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Coins webhook rejected: invalid signature'
                    && $context['reason'] === 'signature_mismatch'
                    && $context['signature_header'] === 'X-COINS-SIGNATURE'
                    && $context['reference_id'] === 'ORDER-001'
                    && $context['status'] === 'SUCCEEDED'
                    && $context['signature_length'] === strlen('invalid-signature')
                    && is_string($context['body_sha256'])
                    && strlen($context['body_sha256']) === 64;
            });
    }

    public function test_webhook_returns_200_when_payment_not_found(): void
    {
        $payload = [
            'referenceId' => 'non-existent-ref',
            'status' => 'SUCCEEDED',
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $signed = $this->signatureService->sign($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks?provider=coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);
    }

    public function test_webhook_returns_200_and_updates_to_paid_when_succeeded(): void
    {
        $payment = Payment::factory()->create([
            'merchant_id' => $this->user->id,
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

        $event = WebhookEvent::query()->where('event_id', 'like', 'ORDER-001:%')->first();
        $this->assertNotNull($event);
        $this->assertSame('processed', $event->status);
        $this->assertNotNull($event->processed_at);
        $this->assertSame($payload['referenceId'], $event->payload['referenceId'] ?? null);
        $this->assertArrayHasKey('content-type', $event->headers ?? []);
    }

    public function test_dedicated_coins_webhook_route_processes_immediately_without_dispatching_webhook_job(): void
    {
        Queue::fake();

        $payment = Payment::factory()->create([
            'merchant_id' => $this->user->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'ORDER-DEDICATED-001',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $payload = [
            'referenceId' => 'ORDER-DEDICATED-001',
            'status' => 'SUCCEEDED',
            'settleDate' => 1707475200000,
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
        Queue::assertNotPushed(ProcessWebhookJob::class);
    }

    public function test_webhook_accepts_guide_compliant_signature_header_without_timestamp(): void
    {
        $payment = Payment::factory()->create([
            'merchant_id' => $this->user->id,
            'gateway_code' => 'gcash',
            'provider_reference' => 'GUIDE-WEBHOOK-001',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $payload = [
            'requestId' => 'GUIDE-WEBHOOK-001',
            'referenceId' => '2007398545514304270',
            'cashInBank' => 'gcash',
            'channelInvoiceNo' => '304270',
            'errorMsg' => '',
            'settleDate' => '1754038804000',
            'status' => 'SUCCEEDED',
        ];
        $signed = $this->signatureService->signWebhook($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks', $payload, [
            'Content-Type' => 'application/json',
            'Signature' => $signed['signature'],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(1754038804, $payment->paid_at->timestamp);
    }

    public function test_webhook_accepts_uppercase_hex_signature_header_without_timestamp(): void
    {
        $payment = Payment::factory()->create([
            'merchant_id' => $this->user->id,
            'gateway_code' => 'gcash',
            'provider_reference' => 'GUIDE-WEBHOOK-002',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $payload = [
            'requestId' => 'GUIDE-WEBHOOK-002',
            'referenceId' => '2007398545514304271',
            'cashInBank' => 'gcash',
            'channelInvoiceNo' => '304271',
            'errorMsg' => '',
            'settleDate' => '1754038804001',
            'status' => 'SUCCEEDED',
        ];
        $signature = strtoupper($this->signatureService->signWebhook($payload, self::WEBHOOK_SECRET)['signature']);

        $response = $this->postJson('/api/webhooks', $payload, [
            'Content-Type' => 'application/json',
            'Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(1754038804, $payment->paid_at->timestamp);
    }

    public function test_webhook_accepts_raw_payload_signature_from_live_callback_shape(): void
    {
        $payment = Payment::factory()->create([
            'merchant_id' => $this->user->id,
            'gateway_code' => 'gcash',
            'provider_reference' => 'GH-6-01KMYE1RG5HW6MZNZ6K6FJG8WK',
            'status' => 'pending',
            'paid_at' => null,
            'raw_response' => [
                'gateway_request_reference' => 'GH-6-01KMYE1RG5HW6MZNZ6K6FJG8WK',
                'data' => [
                    'requestId' => 'GH-6-01KMYE1RG5HW6MZNZ6K6FJG8WK',
                    'status' => 'PENDING',
                ],
            ],
        ]);

        $rawPayload = '{"amount":"1","settleDate":"1774841898000","senderBic":"","userId":"6","referenceId":"2181934522336370231","errorMsg":"success","senderName":"","senderNumber":"","referenceNumber":"","requestId":"GH-6-01KMYE1RG5HW6MZNZ6K6FJG8WK","cashInBank":"GCash","channelInvoiceNo":"251598","createDate":"1774842864000","status":"SUCCEEDED"}';
        $signature = $this->signatureService->signRawPayload($rawPayload, self::WEBHOOK_SECRET)['signature'];

        $response = $this->call(
            'POST',
            '/api/webhooks/coins',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_SIGNATURE' => $signature,
            ],
            $rawPayload
        );

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(1774841898, $payment->paid_at->timestamp);
    }

    public function test_webhook_accepts_documented_qrph_subset_signature_from_live_callback_shape(): void
    {
        $payment = Payment::factory()->create([
            'merchant_id' => $this->user->id,
            'gateway_code' => 'gcash',
            'provider_reference' => 'GH-6-01KMYF6SCM04FRDST87E0T266X',
            'status' => 'pending',
            'paid_at' => null,
            'raw_response' => [
                'gateway_request_reference' => 'GH-6-01KMYF6SCM04FRDST87E0T266X',
                'data' => [
                    'requestId' => 'GH-6-01KMYF6SCM04FRDST87E0T266X',
                    'status' => 'PENDING',
                ],
            ],
        ]);

        $payload = [
            'amount' => '1',
            'settleDate' => '1774844114000',
            'senderBic' => '',
            'userId' => '6',
            'referenceId' => '2181934522336370232',
            'errorMsg' => 'success',
            'senderName' => '',
            'senderNumber' => '',
            'referenceNumber' => '',
            'requestId' => 'GH-6-01KMYF6SCM04FRDST87E0T266X',
            'cashInBank' => 'GCash',
            'channelInvoiceNo' => '251599',
            'createDate' => '1774844114000',
            'status' => 'SUCCEEDED',
        ];
        $signature = $this->signatureService->signWebhook([
            'requestId' => 'GH-6-01KMYF6SCM04FRDST87E0T266X',
            'referenceId' => '2181934522336370232',
            'cashInBank' => 'GCash',
            'channelInvoiceNo' => '251599',
            'settleDate' => '1774844114000',
            'errorMsg' => 'success',
            'status' => 'SUCCEEDED',
        ], self::WEBHOOK_SECRET)['signature'];

        $response = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(1774844114, $payment->paid_at->timestamp);
    }

    public function test_webhook_can_retry_same_event_after_processing_failure(): void
    {
        $firstPayment = Payment::factory()->create([
            'merchant_id' => $this->user->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'ORDER-RETRY-001',
            'status' => 'pending',
        ]);

        $secondPayment = Payment::factory()->create([
            'merchant_id' => User::factory()->create()->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'ORDER-RETRY-001',
            'status' => 'pending',
        ]);

        $payload = [
            'referenceId' => 'ORDER-RETRY-001',
            'status' => 'SUCCEEDED',
            'settleDate' => 1707475200000,
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $signed = $this->signatureService->sign($payload, self::WEBHOOK_SECRET);

        $failureResponse = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $failureResponse->assertStatus(500);
        $failureResponse->assertJson(['message' => 'Webhook processing failed. Please retry.']);

        $event = WebhookEvent::query()->where('event_id', 'like', 'ORDER-RETRY-001:%')->first();
        $this->assertNotNull($event);
        $this->assertSame('failed', $event->status);

        $secondPayment->delete();

        $successResponse = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $successResponse->assertStatus(200);
        $successResponse->assertJson(['received' => true]);

        $firstPayment->refresh();
        $this->assertSame('paid', $firstPayment->status);

        $event->refresh();
        $this->assertSame('processed', $event->status);
        $this->assertNotNull($event->processed_at);
    }

    public function test_webhook_uses_gateway_request_reference_to_resolve_duplicate_merchant_references(): void
    {
        $firstPayment = Payment::factory()->create([
            'merchant_id' => $this->user->id,
            'gateway_code' => 'coins',
            'reference_id' => 'MERCHANT-REF-001',
            'provider_reference' => 'coins-order-merchant-1',
            'status' => 'pending',
            'raw_response' => [
                'gateway_request_reference' => 'GH-1-REQUEST-REF',
                'merchant_reference' => 'MERCHANT-REF-001',
            ],
        ]);

        $secondPayment = Payment::factory()->create([
            'merchant_id' => User::factory()->create()->id,
            'gateway_code' => 'coins',
            'reference_id' => 'MERCHANT-REF-001',
            'provider_reference' => 'coins-order-merchant-2',
            'status' => 'pending',
            'raw_response' => [
                'gateway_request_reference' => 'GH-2-REQUEST-REF',
                'merchant_reference' => 'MERCHANT-REF-001',
            ],
        ]);

        $payload = [
            'referenceId' => 'GH-2-REQUEST-REF',
            'status' => 'SUCCEEDED',
            'amount' => '200.00',
            'currency' => 'PHP',
            'settleDate' => 1707475200000,
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $signed = $this->signatureService->sign($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);

        $firstPayment->refresh();
        $secondPayment->refresh();
        $this->assertSame('pending', $firstPayment->status);
        $this->assertSame('paid', $secondPayment->status);
    }

    public function test_webhook_updates_payment_when_callback_uses_nested_qrcode_status_and_request_id(): void
    {
        $payment = Payment::factory()->create([
            'merchant_id' => $this->user->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'coins-order-merchant-3',
            'status' => 'pending',
            'paid_at' => null,
            'raw_response' => [
                'gateway_request_reference' => 'GH-REQUEST-REF-003',
                'merchant_reference' => 'MERCHANT-REF-003',
            ],
        ]);

        $payload = [
            'timestamp' => (string) (int) (microtime(true) * 1000),
            'data' => [
                'requestId' => 'GH-REQUEST-REF-003',
                'referenceId' => 'COINS-REFERENCE-003',
                'qrcodeStatus' => 'SUCCEEDED',
                'fiatAmount' => '275.00',
                'fiatCurrency' => 'PHP',
                'updatedTime' => '1707475200000',
            ],
        ];
        $signed = $this->signatureService->sign($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(1707475200, $payment->paid_at->timestamp);
    }

    public function test_webhook_updates_payment_when_callback_reference_matches_stored_raw_response_identifier(): void
    {
        $payment = Payment::factory()->create([
            'merchant_id' => $this->user->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'coins-order-merchant-4',
            'status' => 'pending',
            'paid_at' => null,
            'raw_response' => [
                'data' => [
                    'referenceId' => 'COINS-REFERENCE-004',
                ],
            ],
        ]);

        $payload = [
            'timestamp' => (string) (int) (microtime(true) * 1000),
            'data' => [
                'referenceId' => 'COINS-REFERENCE-004',
                'qrcodeStatus' => 'SUCCEEDED',
                'fiatAmount' => '150.00',
                'fiatCurrency' => 'PHP',
                'completionTime' => '2024-02-09T00:00:00.000+00:00',
            ],
        ];
        $signed = $this->signatureService->sign($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(1707436800, $payment->paid_at->timestamp);
    }

    public function test_webhook_updates_payment_when_checkout_callback_uses_completed_at_and_checkout_id(): void
    {
        $payment = Payment::factory()->create([
            'merchant_id' => $this->user->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'MERCHANT_ORDER_20250102_001234567890',
            'status' => 'pending',
            'paid_at' => null,
            'raw_response' => [
                'checkoutId' => '123456789',
            ],
        ]);

        $payload = [
            'checkoutId' => '123456789',
            'requestId' => 'MERCHANT_ORDER_20250102_001234567890',
            'subMerchantId' => 'SUB_MERCHANT_001',
            'merchantName' => 'demo merchant name',
            'subMerchantReqRefNo' => 'SUB_REF_20250102_001',
            'totalAmount' => '101.14',
            'feeAmount' => '1.02',
            'status' => 'SUCCEEDED',
            'completedAt' => '1735801456',
            'errorCode' => null,
            'errorMsg' => null,
        ];
        $signed = $this->signatureService->signWebhook($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'Signature' => $signed['signature'],
        ]);

        $response->assertStatus(200);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(1735801456, $payment->paid_at->timestamp);

        $event = WebhookEvent::query()->where('provider', 'coins')->where('event_id', '123456789')->first();
        $this->assertNotNull($event);
        $this->assertSame('processed', $event->status);
    }

    public function test_webhook_returns_retryable_error_when_legacy_reference_collides_across_merchants(): void
    {
        $otherMerchant = User::factory()->create();
        MerchantGateway::query()->create([
            'merchant_id' => $otherMerchant->id,
            'gateway_id' => $this->coinsGateway->id,
            'is_enabled' => true,
            'config_json' => [
                'client_id' => 'client-2',
                'client_secret' => 'secret-2',
                'api_base' => 'sandbox',
                'webhook_secret' => 'other-secret',
            ],
        ]);

        $merchantOnePayment = Payment::factory()->create([
            'merchant_id' => $this->user->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'ORDER-COLLISION-001',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $merchantTwoPayment = Payment::factory()->create([
            'merchant_id' => $otherMerchant->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'ORDER-COLLISION-001',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $payload = [
            'referenceId' => 'ORDER-COLLISION-001',
            'status' => 'SUCCEEDED',
            'amount' => '700.00',
            'currency' => 'PHP',
            'settleDate' => 1707475200000,
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];

        $signed = $this->signatureService->sign($payload, self::WEBHOOK_SECRET);
        $response = $this->postJson('/api/webhooks/coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(500);
        $response->assertJson(['message' => 'Webhook processing failed. Please retry.']);

        $merchantOnePayment->refresh();
        $merchantTwoPayment->refresh();
        $this->assertSame('pending', $merchantOnePayment->status);
        $this->assertSame('pending', $merchantTwoPayment->status);

        $event = WebhookEvent::query()->where('event_id', 'like', 'ORDER-COLLISION-001:%')->first();
        $this->assertNotNull($event);
        $this->assertSame('failed', $event->status);
    }

    public function test_webhook_idempotent_when_already_paid(): void
    {
        $payment = Payment::factory()->paid()->create([
            'merchant_id' => $this->user->id,
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

        $response = $this->postJson('/api/webhooks?provider=coins', $payload, [
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
            'merchant_id' => $this->user->id,
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

        $response = $this->postJson('/api/webhooks?provider=coins', $payload, [
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
            'merchant_id' => $this->user->id,
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

        $response = $this->postJson('/api/webhooks?provider=coins', $payload, [
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
            'merchant_id' => $this->user->id,
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

        $this->postJson('/api/webhooks?provider=coins', $payload, [
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
            '/api/webhooks?provider=coins',
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

        $response = $this->postJson('/api/webhooks?provider=coins', $payload, [
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

        $response = $this->postJson('/api/webhooks?provider=coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid signature.']);
    }

    public function test_webhook_duplicate_event_returns_200_without_reprocessing(): void
    {
        $payment = Payment::factory()->create([
            'merchant_id' => $this->user->id,
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

        $this->postJson('/api/webhooks?provider=coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);

        $response = $this->postJson('/api/webhooks?provider=coins', $payload, [
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

        $response = $this->postJson('/api/webhooks?provider=coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);
    }
}
