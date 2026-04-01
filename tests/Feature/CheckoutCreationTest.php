<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\User;
use App\Services\Gateways\Drivers\CoinsDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutCreationTest extends TestCase
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
            'config_json' => [
                'client_id' => 'c',
                'client_secret' => 's',
                'api_base' => 'sandbox',
            ],
        ]);
    }

    public function test_checkout_request_includes_merchant_name_from_display_name(): void
    {
        Http::fake([
            'api.9001.pl-qa.coinsxyz.me/*' => function (\Illuminate\Http\Client\Request $request) {
                $body = json_decode($request->body(), true);
                $this->assertIsArray($body);
                $this->assertSame('Branded Store Name', $body['merchantName'] ?? null);
                $this->assertArrayHasKey('redirectUrl', $body);
                $this->assertArrayHasKey('productDetails', $body);

                return Http::response([
                    'status' => 0,
                    'data' => [
                        'checkoutId' => 'chk-001',
                        'checkoutUrl' => 'https://checkout.coins.test/pay/chk-001',
                    ],
                ], 200);
            },
        ]);

        $user = User::factory()->withMerchantApiKey('test-checkout-key-1')->create();
        $user->merchant->forceFill([
            'qr_display_name' => 'Branded Store Name',
        ])->save();

        MerchantGateway::query()->create([
            'merchant_id' => $user->merchant_id,
            'gateway_id' => Gateway::query()->where('code', 'coins')->firstOrFail()->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'coins',
            'reference' => 'CHK-REF-1',
            'checkout' => true,
        ], [
            'Authorization' => 'Bearer test-checkout-key-1',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.checkout_url', 'https://checkout.coins.test/pay/chk-001');
        $response->assertJsonStructure([
            'data' => [
                'transaction_id',
                'checkout_url',
                'merchant' => [
                    'name',
                    'logo',
                    'theme_color',
                ],
            ],
        ]);

        Http::assertSentCount(1);
    }

    public function test_checkout_uses_fallback_display_name_when_merchant_name_blank(): void
    {
        Http::fake([
            'api.9001.pl-qa.coinsxyz.me/*' => function (\Illuminate\Http\Client\Request $request) {
                $body = json_decode($request->body(), true);
                $this->assertSame(\App\Models\Merchant::DEFAULT_DISPLAY_NAME, $body['merchantName'] ?? null);

                return Http::response([
                    'status' => 0,
                    'data' => [
                        'checkoutId' => 'chk-002',
                        'checkoutUrl' => 'https://checkout.coins.test/pay/chk-002',
                    ],
                ], 200);
            },
        ]);

        $user = User::factory()->withMerchantApiKey('test-checkout-key-2')->create();
        $user->merchant->forceFill([
            'name' => '',
            'qr_display_name' => null,
        ])->save();

        MerchantGateway::query()->create([
            'merchant_id' => $user->merchant_id,
            'gateway_id' => Gateway::query()->where('code', 'coins')->firstOrFail()->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => 50,
            'currency' => 'PHP',
            'gateway' => 'coins',
            'reference' => 'CHK-REF-2',
            'checkout' => true,
        ], [
            'Authorization' => 'Bearer test-checkout-key-2',
        ]);

        $response->assertStatus(201);
    }

    public function test_checkout_rejects_non_coins_gateway(): void
    {
        Http::fake();

        $user = User::factory()->withMerchantApiKey('test-checkout-key-3')->create();

        Gateway::query()->create([
            'code' => 'maya',
            'name' => 'Maya',
            'driver_class' => \App\Services\Gateways\Drivers\MayaDriver::class,
            'is_global_enabled' => true,
            'config_json' => [
                'provider_mode' => 'native_direct',
                'client_id' => 'c',
                'client_secret' => 's',
                'api_base_url' => 'https://api.test',
                'merchant_id' => 'mid',
            ],
        ]);

        MerchantGateway::query()->create([
            'merchant_id' => $user->merchant_id,
            'gateway_id' => Gateway::query()->where('code', 'maya')->firstOrFail()->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'maya',
            'reference' => 'CHK-REF-3',
            'checkout' => true,
        ], [
            'Authorization' => 'Bearer test-checkout-key-3',
        ]);

        $response->assertStatus(403);
        Http::assertNothingSent();
    }

    public function test_checkout_redirect_routes_resolve_for_payment(): void
    {
        $payment = \App\Models\Payment::factory()->create();

        $this->get(route('payment.success', ['transaction' => $payment->id]))->assertOk();
        $this->get(route('payment.failure', ['transaction' => $payment->id]))->assertOk();
        $this->get(route('payment.cancel', ['transaction' => $payment->id]))->assertOk();
        $this->get(route('payment.default', ['transaction' => $payment->id]))->assertOk();
    }
}
