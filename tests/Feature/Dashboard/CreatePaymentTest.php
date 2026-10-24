<?php

namespace Tests\Feature\Dashboard;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\Payment;
use App\Models\User;
use App\Services\Gateways\Drivers\PayMongoDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CreatePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::fake([
            'https://api.paymongo.com/v1/merchants/capabilities/payment_methods' => Http::response([
                'data' => ['attributes' => ['payment_methods' => ['qrph', 'gcash', 'paymaya', 'grab_pay', 'shopeepay']]],
            ], 200),
        ]);

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

        Gateway::query()->updateOrCreate(['code' => 'coins'], [
            'name' => 'Coins.ph',
            'driver_class' => PayMongoDriver::class,
            'is_global_enabled' => false,
        ]);
    }

    public function test_create_payment_form_shows_enabled_paymongo_methods_only(): void
    {
        $user = User::factory()->create();
        $gcashGateway = Gateway::query()->where('code', 'gcash')->firstOrFail();
        MerchantGateway::query()->create([
            'merchant_id' => $user->id,
            'gateway_id' => $gcashGateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.payments.create'));

        $response->assertOk();
        $response->assertSee('Create Payment');
        $response->assertSee('GCash');
        $response->assertDontSee('Coins.ph');
        $response->assertDontSee('Maya');
    }

    public function test_create_payment_form_shows_empty_state_when_no_gateways_are_enabled(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard.payments.create'));

        $response->assertOk();
        $response->assertSee('No gateways are enabled');
        $response->assertSee('View gateway status');
        $response->assertDontSee('Configure gateways');
    }

    public function test_store_creates_paymongo_gcash_payment_and_redirects(): void
    {
        config()->set('platform.fees.convenience_fee', 0);

        Http::fake(function ($request) {
            return match (parse_url($request->url(), PHP_URL_PATH)) {
                '/v1/merchants/capabilities/payment_methods' => Http::response([
                    'data' => ['attributes' => ['payment_methods' => ['qrph', 'gcash', 'paymaya', 'grab_pay', 'shopeepay']]],
                ], 200),
                '/v1/payment_intents' => Http::response([
                    'data' => [
                        'id' => 'pi_dashboard_gcash_001',
                        'attributes' => ['client_key' => 'pi_dashboard_gcash_001_client_key'],
                    ],
                ], 200),
                '/v1/payment_methods' => Http::response(['data' => ['id' => 'pm_dashboard_gcash_001']], 200),
                '/v1/payment_intents/pi_dashboard_gcash_001/attach' => Http::response([
                    'data' => [
                        'id' => 'pi_dashboard_gcash_001',
                        'attributes' => [
                            'status' => 'awaiting_next_action',
                            'next_action' => ['redirect' => ['url' => 'https://wallet.example/gcash/dashboard']],
                        ],
                    ],
                ], 200),
                default => Http::response(['message' => 'Unexpected PayMongo request.'], 404),
            };
        });

        $user = User::factory()->create();
        $gcashGateway = Gateway::query()->where('code', 'gcash')->firstOrFail();
        MerchantGateway::query()->create([
            'merchant_id' => $user->id,
            'gateway_id' => $gcashGateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->actingAs($user)->post(route('dashboard.payments.store'), [
            'amount' => '100.00',
            'currency' => 'PHP',
            'gateway' => 'gcash',
        ]);

        $response->assertRedirect('https://wallet.example/gcash/dashboard');
        $this->assertDatabaseHas('payments', [
            'merchant_id' => $user->id,
            'gateway_code' => 'gcash',
            'amount' => 100,
            'product_amount' => 100,
            'payer_fee_amount' => 1.5,
            'customer_total' => 101.5,
            'currency' => 'PHP',
            'provider_reference' => 'pi_dashboard_gcash_001',
            'status' => 'pending',
        ]);

        $payment = Payment::query()->where('merchant_id', $user->id)->firstOrFail();
        $this->assertNull($payment->getQrData()['value'] ?? null);
        $this->assertMatchesRegularExpression(
            '/^KP-'.$user->id.'-[0-9A-HJKMNP-TV-Z]{26}$/',
            (string) ($payment->raw_response['gateway_request_reference'] ?? '')
        );
        $this->assertSame($payment->reference_id, $payment->raw_response['merchant_reference'] ?? null);
    }

    public function test_store_validates_amount(): void
    {
        $user = User::factory()->create();
        $gcashGateway = Gateway::query()->where('code', 'gcash')->firstOrFail();
        MerchantGateway::query()->create([
            'merchant_id' => $user->id,
            'gateway_id' => $gcashGateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->actingAs($user)->post(route('dashboard.payments.store'), [
            'amount' => '',
            'currency' => 'PHP',
            'gateway' => 'gcash',
        ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_store_creates_paymongo_qrph_payment(): void
    {
        Http::fake(function ($request) {
            return match (parse_url($request->url(), PHP_URL_PATH)) {
                '/v1/merchants/capabilities/payment_methods' => Http::response([
                    'data' => ['attributes' => ['payment_methods' => ['qrph', 'gcash', 'paymaya', 'grab_pay', 'shopeepay']]],
                ], 200),
                '/v1/payment_intents' => Http::response([
                    'data' => [
                        'id' => 'pi_dashboard_qrph_001',
                        'attributes' => ['client_key' => 'pi_dashboard_qrph_001_client_key'],
                    ],
                ], 200),
                '/v1/payment_methods' => Http::response(['data' => ['id' => 'pm_dashboard_qrph_001']], 200),
                '/v1/payment_intents/pi_dashboard_qrph_001/attach' => Http::response([
                    'data' => [
                        'id' => 'pi_dashboard_qrph_001',
                        'attributes' => [
                            'status' => 'awaiting_next_action',
                            'next_action' => ['code' => ['image_url' => 'data:image/png;base64,DASHBOARDQR']],
                        ],
                    ],
                ], 200),
                default => Http::response(['message' => 'Unexpected PayMongo request.'], 404),
            };
        });

        $user = User::factory()->create();
        $qrphGateway = Gateway::query()->where('code', 'qrph')->firstOrFail();
        MerchantGateway::query()->create([
            'merchant_id' => $user->id,
            'gateway_id' => $qrphGateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $response = $this->actingAs($user)->post(route('dashboard.payments.store'), [
            'amount' => 250,
            'currency' => 'PHP',
            'gateway' => 'qrph',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/dashboard/payments/', (string) $response->headers->get('Location'));
        $this->assertDatabaseHas('payments', [
            'merchant_id' => $user->id,
            'gateway_code' => 'qrph',
            'provider_reference' => 'pi_dashboard_qrph_001',
            'status' => 'pending',
        ]);
    }
}
