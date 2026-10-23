<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\Payment;
use App\Models\User;
use App\Services\Gateways\Drivers\PayMongoDriver;
use App\Services\Gateways\Drivers\PaypalDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gateway::query()->updateOrCreate(['code' => 'paymongo'], [
            'name' => 'PayMongo',
            'driver_class' => PayMongoDriver::class,
            'is_global_enabled' => true,
            'config_json' => [
                'secret_key' => 'sk_test_kemopay',
                'public_key' => 'pk_test_kemopay',
                'webhook_secret' => 'whsk_test_kemopay',
            ],
        ]);

        foreach ([
            'gcash' => 'GCash',
            'maya' => 'Maya',
            'grab_pay' => 'GrabPay',
            'shopeepay' => 'ShopeePay',
            'qrph' => 'QR Ph',
        ] as $code => $name) {
            Gateway::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'driver_class' => PayMongoDriver::class,
                'is_global_enabled' => true,
            ]);
        }
    }

    public function test_checkout_request_uses_paymongo_and_returns_the_wallet_redirect(): void
    {
        $this->fakePayMongoRedirect('pi_checkout_gcash', 'https://wallet.example/checkout/gcash');

        $user = User::factory()->withMerchantApiKey('test-checkout-key-1')->create();
        $user->merchant->forceFill(['qr_display_name' => 'Branded Store Name'])->save();
        $this->enableGatewayFor($user, 'gcash');

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'gcash',
            'reference' => 'CHK-REF-1',
            'checkout' => true,
        ], [
            'Authorization' => 'Bearer test-checkout-key-1',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.gateway', 'gcash');
        $response->assertJsonPath('data.redirect_url', 'https://wallet.example/checkout/gcash');
        $response->assertJsonPath('data.checkout_url', 'https://wallet.example/checkout/gcash');
        $response->assertJsonPath('data.merchant.name', 'Branded Store Name');
        $response->assertJsonPath('data.amount', 100);
        $response->assertJsonPath('data.product_amount', 100);
        $response->assertJsonPath('data.gross_amount', 121.5);
        $response->assertJsonPath('data.gatewayhub_platform_fee_percent', 1.5);
        $response->assertJsonPath('data.gatewayhub_platform_fee', 1.5);
        $response->assertJsonPath('data.convenience_fee', 20);
        $response->assertJsonPath('data.customer_total', 121.5);
        $response->assertJsonPath('data.gatewayhub_net_amount', 100);
        $this->assertDatabaseHas('payments', [
            'merchant_id' => $user->merchant_id,
            'gateway_code' => 'gcash',
            'amount' => 100,
            'product_amount' => 100,
            'payer_fee_amount' => 21.5,
            'platform_fee_rate' => 0.015,
            'customer_total' => 121.5,
        ]);
        $response->assertJsonStructure([
            'data' => [
                'transaction_id',
                'amount',
                'product_amount',
                'checkout_url',
                'gross_amount',
                'convenience_fee',
                'customer_total',
                'gatewayhub_platform_fee_percent',
                'gatewayhub_platform_fee',
                'gatewayhub_net_amount',
                'merchant' => ['name', 'logo', 'theme_color'],
            ],
        ]);

        Http::assertSentCount(4);
        Http::assertSent(fn ($request): bool => parse_url($request->url(), PHP_URL_PATH) === '/v1/payment_intents'
            && data_get($request->data(), 'data.attributes.amount') === 12150);
    }

    public function test_wallet_payment_uses_fallback_display_name_when_merchant_name_is_blank(): void
    {
        $this->fakePayMongoRedirect('pi_checkout_maya', 'https://wallet.example/checkout/maya');

        $user = User::factory()->withMerchantApiKey('test-checkout-key-2')->create();
        $user->merchant->forceFill(['name' => '', 'qr_display_name' => null])->save();
        $this->enableGatewayFor($user, 'maya');

        $response = $this->postJson('/api/payments', [
            'amount' => 50,
            'currency' => 'PHP',
            'gateway' => 'maya',
            'reference' => 'CHK-REF-2',
            'checkout' => true,
        ], [
            'Authorization' => 'Bearer test-checkout-key-2',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.gateway', 'maya');
        $response->assertJsonPath('data.redirect_url', 'https://wallet.example/checkout/maya');
        $response->assertJsonPath('data.merchant.name', \App\Models\Merchant::DEFAULT_DISPLAY_NAME);
        $response->assertJsonPath('data.amount', 50);
        $response->assertJsonPath('data.gross_amount', 70.75);
        $response->assertJsonPath('data.customer_total', 70.75);
    }

    public function test_paypal_is_rejected_so_payments_use_only_paymongo_methods(): void
    {
        Http::fake(function ($request) {
            return match (parse_url($request->url(), PHP_URL_PATH)) {
                '/v1/oauth2/token' => Http::response(['access_token' => 'paypal-test-access-token'], 200),
                '/v2/checkout/orders' => Http::response([
                    'id' => 'PAYPAL-ORDER-001',
                    'links' => [['rel' => 'approve', 'href' => 'https://paypal.example/approve/001']],
                ], 201),
                default => Http::response(['message' => 'Unexpected PayPal request.'], 404),
            };
        });

        $user = User::factory()->withMerchantApiKey('test-checkout-key-3')->create();
        $paypal = Gateway::query()->create([
            'code' => 'paypal',
            'name' => 'PayPal',
            'driver_class' => PaypalDriver::class,
            'is_global_enabled' => true,
        ]);
        MerchantGateway::query()->create([
            'merchant_id' => $user->merchant_id,
            'gateway_id' => $paypal->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'paypal',
            'reference' => 'CHK-REF-3',
        ], [
            'Authorization' => 'Bearer test-checkout-key-3',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('error', 'This payment gateway is no longer available.');
        $this->assertDatabaseMissing('payments', [
            'merchant_id' => $user->merchant_id,
            'gateway_code' => 'paypal',
        ]);
        Http::assertNothingSent();
    }

    public function test_coins_ph_method_is_not_available_after_the_paymongo_replacement(): void
    {
        Http::fake();

        $user = User::factory()->withMerchantApiKey('test-checkout-key-4')->create();
        $coins = Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => PayMongoDriver::class,
            'is_global_enabled' => true,
        ]);
        MerchantGateway::query()->create([
            'merchant_id' => $user->merchant_id,
            'gateway_id' => $coins->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'coins',
            'reference' => 'CHK-REF-4',
        ], [
            'Authorization' => 'Bearer test-checkout-key-4',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('error', 'This payment gateway is no longer available.');
        Http::assertNothingSent();
    }

    public function test_checkout_redirect_routes_resolve_for_payment(): void
    {
        $payment = Payment::factory()->create();

        $this->get(route('payment.success', ['transaction' => $payment->id]))->assertOk();
        $this->get(route('payment.failure', ['transaction' => $payment->id]))->assertOk();
        $this->get(route('payment.cancel', ['transaction' => $payment->id]))->assertOk();
        $this->get(route('payment.default', ['transaction' => $payment->id]))->assertOk();
    }

    private function enableGatewayFor(User $user, string $code): void
    {
        $gateway = Gateway::query()->where('code', $code)->firstOrFail();

        MerchantGateway::query()->create([
            'merchant_id' => $user->merchant_id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);
    }

    private function fakePayMongoRedirect(string $intentId, string $redirectUrl): void
    {
        Http::fake(function ($request) use ($intentId, $redirectUrl) {
            return match (parse_url($request->url(), PHP_URL_PATH)) {
                '/v1/merchants/capabilities/payment_methods' => Http::response([
                    'data' => ['attributes' => ['payment_methods' => ['qrph', 'gcash', 'paymaya', 'grab_pay', 'shopeepay']]],
                ], 200),
                '/v1/payment_intents' => Http::response([
                    'data' => ['id' => $intentId, 'attributes' => ['client_key' => $intentId.'_client_key']],
                ], 200),
                '/v1/payment_methods' => Http::response(['data' => ['id' => 'pm_'.str_replace('pi_', '', $intentId)]], 200),
                '/v1/payment_intents/'.$intentId.'/attach' => Http::response([
                    'data' => [
                        'id' => $intentId,
                        'attributes' => [
                            'status' => 'awaiting_next_action',
                            'next_action' => ['redirect' => ['url' => $redirectUrl]],
                        ],
                    ],
                ], 200),
                default => Http::response(['message' => 'Unexpected PayMongo request.'], 404),
            };
        });
    }
}
