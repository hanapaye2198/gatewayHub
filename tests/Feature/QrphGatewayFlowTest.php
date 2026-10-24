<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\Payment;
use App\Models\User;
use App\Services\Coins\CoinsSignatureService;
use App\Services\Gateways\Drivers\PayMongoDriver;
use App\Services\Gateways\Drivers\QrphDriver;
use App\Services\Gateways\Exceptions\GatewayException;
use App\Services\PaymentStatusSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QrphGatewayFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

    }

    public function test_api_payment_creation_uses_paymongo_payment_intent_qr_flow(): void
    {
        Http::fake(function ($request) {
            return match (parse_url($request->url(), PHP_URL_PATH)) {
                '/v1/merchants/capabilities/payment_methods' => Http::response([
                    'data' => ['attributes' => ['payment_methods' => ['qrph', 'gcash', 'paymaya', 'grab_pay', 'shopeepay']]],
                ], 200),
                '/v1/payment_intents' => Http::response([
                    'data' => [
                        'id' => 'pi_qrph_001',
                        'attributes' => ['client_key' => 'pi_qrph_001_client_key'],
                    ],
                ], 200),
                '/v1/payment_methods' => Http::response([
                    'data' => ['id' => 'pm_qrph_001'],
                ], 200),
                '/v1/payment_intents/pi_qrph_001/attach' => Http::response([
                    'data' => [
                        'id' => 'pi_qrph_001',
                        'attributes' => [
                            'status' => 'awaiting_next_action',
                            'next_action' => [
                                'code' => ['image_url' => 'data:image/png;base64,QRPHIMAGE'],
                            ],
                        ],
                    ],
                ], 200),
                default => Http::response(['message' => 'Unexpected PayMongo request.'], 404),
            };
        });

        $gateway = Gateway::query()->updateOrCreate(['code' => 'qrph'], [
            'code' => 'qrph',
            'name' => 'QRPH',
            'driver_class' => QrphDriver::class,
            'is_global_enabled' => true,
        ]);

        $merchant = User::factory()->withMerchantApiKey('qrph-api-key')->create();

        MerchantGateway::query()->create([
            'merchant_id' => $merchant->id,
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
        $response->assertJsonPath('data.qr_data', 'data:image/png;base64,QRPHIMAGE');
        $response->assertJsonPath('data.amount', 150);
        $response->assertJsonPath('data.gross_amount', 152.25);
        $response->assertJsonPath('data.gatewayhub_platform_fee', 2.25);
        $response->assertJsonPath('data.gatewayhub_net_amount', 150);
        $this->assertDatabaseHas('payments', [
            'merchant_id' => $merchant->id,
            'gateway_code' => 'qrph',
            'provider_reference' => 'pi_qrph_001',
            'status' => 'pending',
        ]);

        Http::assertSentCount(4);
        Http::assertSent(function ($request): bool {
            return parse_url($request->url(), PHP_URL_PATH) === '/v1/payment_intents'
                && data_get($request->data(), 'data.attributes.amount') === 15225
                && data_get($request->data(), 'data.attributes.payment_method_allowed') === ['qrph']
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_kemopay:'))
                && $request->hasHeader('Idempotency-Key');
        });
        Http::assertSent(fn ($request): bool => parse_url($request->url(), PHP_URL_PATH) === '/v1/payment_methods'
            && data_get($request->data(), 'data.attributes.expiry_seconds') === 1800
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('pk_test_kemopay:')));
        Http::assertSent(fn ($request): bool => parse_url($request->url(), PHP_URL_PATH) === '/v1/payment_intents/pi_qrph_001/attach'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('pk_test_kemopay:')));
    }

    public function test_api_creates_a_gcash_redirect_payment_through_paymongo(): void
    {
        Http::fake(function ($request) {
            return match (parse_url($request->url(), PHP_URL_PATH)) {
                '/v1/merchants/capabilities/payment_methods' => Http::response([
                    'data' => ['attributes' => ['payment_methods' => ['qrph', 'gcash', 'paymaya', 'grab_pay', 'shopeepay']]],
                ], 200),
                '/v1/payment_intents' => Http::response([
                    'data' => [
                        'id' => 'pi_gcash_001',
                        'attributes' => ['client_key' => 'pi_gcash_001_client_key'],
                    ],
                ], 200),
                '/v1/payment_methods' => Http::response(['data' => ['id' => 'pm_gcash_001']], 200),
                '/v1/payment_intents/pi_gcash_001/attach' => Http::response([
                    'data' => [
                        'id' => 'pi_gcash_001',
                        'attributes' => [
                            'status' => 'awaiting_next_action',
                            'next_action' => ['redirect' => ['url' => 'https://wallet.example/gcash/authorize']],
                        ],
                    ],
                ], 200),
                default => Http::response(['message' => 'Unexpected PayMongo request.'], 404),
            };
        });

        $gateway = Gateway::query()->where('code', 'gcash')->firstOrFail();
        $gateway->update([
            'driver_class' => PayMongoDriver::class,
            'is_global_enabled' => true,
        ]);

        $merchant = User::factory()->withMerchantApiKey('gcash-api-key')->create();
        MerchantGateway::query()->create([
            'merchant_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => '150.00',
            'currency' => 'PHP',
            'gateway' => 'gcash',
            'reference' => 'GCASH-PAYMONGO-001',
        ], [
            'Authorization' => 'Bearer gcash-api-key',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.gateway', 'gcash');
        $response->assertJsonPath('data.redirect_url', 'https://wallet.example/gcash/authorize');
        $response->assertJsonPath('data.gross_amount', 152.25);
        $this->assertDatabaseHas('payments', [
            'merchant_id' => $merchant->id,
            'gateway_code' => 'gcash',
            'provider_reference' => 'pi_gcash_001',
            'status' => 'pending',
        ]);

        Http::assertSent(fn ($request): bool => parse_url($request->url(), PHP_URL_PATH) === '/v1/payment_intents'
            && data_get($request->data(), 'data.attributes.amount') === 15225
            && data_get($request->data(), 'data.attributes.payment_method_allowed') === ['gcash']);
        Http::assertSent(fn ($request): bool => parse_url($request->url(), PHP_URL_PATH) === '/v1/payment_methods'
            && data_get($request->data(), 'data.attributes.type') === 'gcash');
        Http::assertSent(fn ($request): bool => parse_url($request->url(), PHP_URL_PATH) === '/v1/payment_intents/pi_gcash_001/attach'
            && str_contains((string) data_get($request->data(), 'data.attributes.return_url'), '/payment/default/'));
    }

    public function test_paymongo_e_wallet_codes_use_their_documented_payment_method_types(): void
    {
        Http::fake(function ($request) {
            return match (parse_url($request->url(), PHP_URL_PATH)) {
                '/v1/payment_intents' => Http::response([
                    'data' => [
                        'id' => 'pi_wallet_001',
                        'attributes' => ['client_key' => 'pi_wallet_001_client_key'],
                    ],
                ], 200),
                '/v1/payment_methods' => Http::response(['data' => ['id' => 'pm_wallet_001']], 200),
                '/v1/payment_intents/pi_wallet_001/attach' => Http::response([
                    'data' => [
                        'id' => 'pi_wallet_001',
                        'attributes' => [
                            'next_action' => ['redirect' => ['url' => 'https://wallet.example/authorize']],
                        ],
                    ],
                ], 200),
                default => Http::response(['message' => 'Unexpected PayMongo request.'], 404),
            };
        });

        $paymentMethods = [
            'gcash' => 'gcash',
            'maya' => 'paymaya',
            'grab_pay' => 'grab_pay',
            'shopeepay' => 'shopeepay',
        ];

        foreach ($paymentMethods as $gatewayCode => $paymentMethodType) {
            $result = (new PayMongoDriver([
                'secret_key' => 'sk_test_kemopay',
                'public_key' => 'pk_test_kemopay',
            ]))->createPayment([
                'amount' => '25.00',
                'currency' => 'PHP',
                'reference' => 'EWALLET-'.$gatewayCode,
                'payment_id' => '00000000-0000-4000-8000-000000000001',
                'gateway_code' => $gatewayCode,
            ]);

            $this->assertSame('https://wallet.example/authorize', $result['redirect_url']);
        }

        foreach ($paymentMethods as $paymentMethodType) {
            Http::assertSent(fn ($request): bool => parse_url($request->url(), PHP_URL_PATH) === '/v1/payment_intents'
                && data_get($request->data(), 'data.attributes.payment_method_allowed') === [$paymentMethodType]);
            Http::assertSent(fn ($request): bool => parse_url($request->url(), PHP_URL_PATH) === '/v1/payment_methods'
                && data_get($request->data(), 'data.attributes.type') === $paymentMethodType);
        }

        $this->assertSame(12, Http::recorded()->count());
    }

    public function test_paymongo_qrph_rejects_amounts_with_more_than_two_decimal_places(): void
    {
        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('no more than two decimal places');

        (new QrphDriver([
            'secret_key' => 'sk_test_kemopay',
            'public_key' => 'pk_test_kemopay',
        ]))->createPayment([
            'reference' => 'QRPH-REF-INVALID-AMOUNT',
            'amount' => '1.001',
            'currency' => 'PHP',
        ]);
    }

    public function test_paymongo_provider_rejection_explains_that_method_activation_is_required(): void
    {
        Http::fake([
            'https://api.paymongo.com/v1/payment_intents' => Http::response([
                'errors' => [[
                    'code' => 'payment_method_not_allowed',
                    'detail' => 'gcash payment method is not allowed.',
                ]],
            ], 400),
        ]);

        try {
            (new PayMongoDriver([
                'secret_key' => 'sk_test_kemopay',
                'public_key' => 'pk_test_kemopay',
            ]))->createPayment([
                'amount' => '10.00',
                'currency' => 'PHP',
                'reference' => 'GCASH-METHOD-NOT-ALLOWED',
                'payment_id' => '00000000-0000-4000-8000-000000000001',
                'gateway_code' => 'gcash',
            ]);
        } catch (GatewayException $exception) {
            $this->assertStringContainsString("not enabled for KemoPay's PayMongo account", $exception->getMessage());
            $this->assertStringContainsString('Settings > Payment Methods', $exception->getMessage());

            return;
        }

        $this->fail('Expected PayMongo method activation error.');
    }

    public function test_api_rejects_invalid_qrph_amount_precision_before_creating_a_payment(): void
    {
        Http::fake([
            'https://api.paymongo.com/v1/merchants/capabilities/payment_methods' => Http::response([
                'data' => ['attributes' => ['payment_methods' => ['qrph', 'gcash', 'paymaya', 'grab_pay', 'shopeepay']]],
            ], 200),
        ]);

        $merchant = User::factory()->withMerchantApiKey('qrph-invalid-amount-api-key')->create();

        $response = $this->postJson('/api/payments', [
            'amount' => '1.001',
            'currency' => 'PHP',
            'gateway' => 'qrph',
            'reference' => 'QRPH-INVALID-AMOUNT',
        ], [
            'Authorization' => 'Bearer qrph-invalid-amount-api-key',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('payments', ['merchant_id' => $merchant->id]);
    }

    public function test_paymongo_webhook_updates_qrph_payment_status(): void
    {
        $payment = Payment::factory()->create([
            'gateway_code' => 'qrph',
            'amount' => 150,
            'customer_total' => 152.25,
            'currency' => 'PHP',
            'provider_reference' => 'pi_qrph_webhook_001',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $timestamp = time();
        $payload = [
            'data' => [
                'id' => 'evt_qrph_paid_001',
                'type' => 'event',
                'attributes' => [
                    'type' => 'payment.paid',
                    'livemode' => false,
                    'created_at' => $timestamp,
                    'data' => [
                        'id' => 'pay_qrph_001',
                        'type' => 'payment',
                        'attributes' => [
                            'payment_intent_id' => 'pi_qrph_webhook_001',
                            'status' => 'paid',
                            'amount' => 15225,
                            'currency' => 'PHP',
                            'paid_at' => $timestamp,
                            'source' => ['type' => 'qrph'],
                        ],
                    ],
                ],
            ],
        ];
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $timestamp.'.'.$rawBody, 'whsk_test_kemopay');

        $response = $this->call('POST', '/api/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature},li=",
        ], $rawBody);

        $response->assertStatus(200);
        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame($timestamp, $payment->paid_at?->timestamp);
    }

    public function test_paymongo_webhook_rejects_an_invalid_signature(): void
    {
        $timestamp = time();
        $rawBody = json_encode([
            'data' => [
                'id' => 'evt_invalid',
                'attributes' => ['livemode' => false],
            ],
        ], JSON_UNESCAPED_SLASHES);

        $response = $this->call('POST', '/api/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te=invalid,li=",
        ], $rawBody);

        $response->assertUnauthorized();
    }

    public function test_paymongo_webhook_requires_the_signature_for_the_event_mode(): void
    {
        $timestamp = time();
        $rawBody = json_encode([
            'data' => [
                'id' => 'evt_qrph_wrong_signature_mode',
                'attributes' => ['livemode' => false],
            ],
        ], JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $timestamp.'.'.$rawBody, 'whsk_test_kemopay');

        $response = $this->call('POST', '/api/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te=,li={$signature}",
        ], $rawBody);

        $response->assertUnauthorized();
    }

    public function test_qrph_driver_verifies_paymongo_webhooks_using_the_event_mode(): void
    {
        $timestamp = time();
        $rawBody = json_encode([
            'data' => [
                'attributes' => ['livemode' => false],
            ],
        ], JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $timestamp.'.'.$rawBody, 'whsk_test_kemopay');
        $request = Request::create('/api/webhooks/paymongo', 'POST', [], [], [], [
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature},li=",
        ], $rawBody);

        $driver = new QrphDriver(['webhook_secret' => 'whsk_test_kemopay']);

        $this->assertTrue($driver->verifyWebhook($request));
    }

    public function test_paymongo_webhook_rejects_an_event_from_the_wrong_api_key_mode(): void
    {
        $timestamp = time();
        $rawBody = json_encode([
            'data' => [
                'id' => 'evt_qrph_live_on_test_key',
                'attributes' => ['livemode' => true],
            ],
        ], JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $timestamp.'.'.$rawBody, 'whsk_test_kemopay');

        $response = $this->call('POST', '/api/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te=,li={$signature}",
        ], $rawBody);

        $response->assertUnauthorized();
    }

    public function test_paymongo_webhook_does_not_mark_paid_when_amount_does_not_match(): void
    {
        $payment = Payment::factory()->create([
            'gateway_code' => 'qrph',
            'amount' => 150,
            'customer_total' => 152.25,
            'currency' => 'PHP',
            'provider_reference' => 'pi_qrph_amount_mismatch',
            'status' => 'pending',
        ]);
        $timestamp = time();
        $payload = [
            'data' => [
                'id' => 'evt_qrph_amount_mismatch',
                'attributes' => [
                    'type' => 'payment.paid',
                    'livemode' => false,
                    'created_at' => $timestamp,
                    'data' => [
                        'id' => 'pay_qrph_amount_mismatch',
                        'type' => 'payment',
                        'attributes' => [
                            'payment_intent_id' => 'pi_qrph_amount_mismatch',
                            'amount' => 14900,
                            'currency' => 'PHP',
                            'paid_at' => $timestamp,
                        ],
                    ],
                ],
            ],
        ];
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $timestamp.'.'.$rawBody, 'whsk_test_kemopay');

        $response = $this->call('POST', '/api/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature},li=",
        ], $rawBody);

        $response->assertOk();
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_payment_status_sync_polls_paymongo_payment_intent(): void
    {
        $timestamp = time();
        Http::fake([
            'orders-api.paymongo.com/v1/payment_intents/pi_qrph_status_001' => Http::response([
                'data' => [
                    'id' => 'pi_qrph_status_001',
                    'attributes' => [
                        'amount' => 15225,
                        'currency' => 'PHP',
                        'status' => 'succeeded',
                        'updated_at' => $timestamp,
                    ],
                ],
            ], 200),
        ]);

        Gateway::query()->updateOrCreate(['code' => 'qrph'], [
            'name' => 'QR Ph (PayMongo)',
            'driver_class' => QrphDriver::class,
            'is_global_enabled' => true,
        ]);

        $payment = Payment::factory()->create([
            'gateway_code' => 'qrph',
            'amount' => 150,
            'customer_total' => 152.25,
            'currency' => 'PHP',
            'provider_reference' => 'pi_qrph_status_001',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        app(PaymentStatusSyncService::class)->syncPendingPayment($payment);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame($timestamp, $payment->paid_at?->timestamp);
        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://orders-api.paymongo.com/v1/payment_intents/pi_qrph_status_001'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_kemopay:')));
    }

    public function test_payment_status_sync_falls_back_when_paymongo_orders_route_is_unavailable(): void
    {
        $timestamp = time();
        Http::fake([
            'orders-api.paymongo.com/v1/payment_intents/pi_qrph_status_fallback' => Http::response([
                'message' => 'The requested route does not exist',
            ], 404),
            'api.paymongo.com/v1/payment_intents/pi_qrph_status_fallback' => Http::response([
                'data' => [
                    'id' => 'pi_qrph_status_fallback',
                    'attributes' => [
                        'amount' => 15225,
                        'currency' => 'PHP',
                        'status' => 'succeeded',
                        'updated_at' => $timestamp,
                    ],
                ],
            ], 200),
        ]);

        Gateway::query()->updateOrCreate(['code' => 'qrph'], [
            'name' => 'QR Ph (PayMongo)',
            'driver_class' => QrphDriver::class,
            'is_global_enabled' => true,
        ]);

        $payment = Payment::factory()->create([
            'gateway_code' => 'qrph',
            'amount' => 150,
            'customer_total' => 152.25,
            'currency' => 'PHP',
            'provider_reference' => 'pi_qrph_status_fallback',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        app(PaymentStatusSyncService::class)->syncPendingPayment($payment);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame($timestamp, $payment->paid_at?->timestamp);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api.paymongo.com/v1/payment_intents/pi_qrph_status_fallback'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_kemopay:')));
    }

    public function test_payment_status_sync_does_not_mark_paid_with_mismatched_paymongo_amount(): void
    {
        Http::fake([
            'orders-api.paymongo.com/v1/payment_intents/pi_qrph_wrong_amount' => Http::response([
                'data' => [
                    'id' => 'pi_qrph_wrong_amount',
                    'attributes' => [
                        'amount' => 14900,
                        'currency' => 'PHP',
                        'status' => 'succeeded',
                    ],
                ],
            ], 200),
        ]);

        Gateway::query()->updateOrCreate(['code' => 'qrph'], [
            'name' => 'QR Ph (PayMongo)',
            'driver_class' => QrphDriver::class,
            'is_global_enabled' => true,
        ]);

        $payment = Payment::factory()->create([
            'gateway_code' => 'qrph',
            'amount' => 150,
            'customer_total' => 152.25,
            'currency' => 'PHP',
            'provider_reference' => 'pi_qrph_wrong_amount',
            'status' => 'pending',
        ]);

        app(PaymentStatusSyncService::class)->syncPendingPayment($payment);

        $this->assertSame('pending', $payment->fresh()->status);
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

    public function test_coins_webhook_cannot_update_a_paymongo_qrph_payment(): void
    {
        config()->set('coins.webhook.allow_dev_bypass', false);
        config()->set('coins.webhook.secret', 'qrph-coins-webhook-secret');

        Payment::factory()->create([
            'gateway_code' => 'qrph',
            'provider_reference' => 'pi_paymongo_qrph_001',
            'status' => 'pending',
            'paid_at' => null,
            'raw_response' => [
                'payment_intent' => ['data' => ['id' => 'pi_paymongo_qrph_001']],
            ],
        ]);

        $payload = [
            'referenceId' => 'pi_paymongo_qrph_001',
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

        $response->assertOk();
        $this->assertDatabaseHas('payments', [
            'gateway_code' => 'qrph',
            'provider_reference' => 'pi_paymongo_qrph_001',
            'status' => 'pending',
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
