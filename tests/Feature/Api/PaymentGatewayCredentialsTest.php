<?php

namespace Tests\Feature\Api;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\User;
use App\Services\Gateways\Drivers\PayMongoDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentGatewayCredentialsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gateway::query()->updateOrCreate(['code' => 'paymongo'], [
            'name' => 'PayMongo',
            'driver_class' => PayMongoDriver::class,
            'is_global_enabled' => true,
            'config_json' => [],
        ]);
        Gateway::query()->updateOrCreate(['code' => 'gcash'], [
            'name' => 'GCash',
            'driver_class' => PayMongoDriver::class,
            'is_global_enabled' => true,
        ]);
    }

    public function test_create_payment_returns_422_when_shared_paymongo_credentials_are_missing(): void
    {
        config()->set('paymongo.gateway.secret_key', '');
        config()->set('paymongo.gateway.public_key', '');
        config()->set('paymongo.gateway.webhook_secret', '');

        $user = User::factory()->withMerchantApiKey('test-key')->create();
        $this->enableGcashFor($user);

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'gcash',
            'reference' => 'ref-1',
        ], ['Authorization' => 'Bearer test-key']);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Gateway "PayMongo" is missing required platform credentials: secret_key, public_key, webhook_secret. Configure them in KemoPay admin settings.');
    }

    public function test_create_payment_returns_403_when_payment_method_is_globally_disabled(): void
    {
        $gateway = Gateway::query()->where('code', 'gcash')->firstOrFail();
        $gateway->update(['is_global_enabled' => false]);

        $user = User::factory()->withMerchantApiKey('test-key')->create();
        $this->enableGcashFor($user);

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'gcash',
            'reference' => 'ref-1',
        ], ['Authorization' => 'Bearer test-key']);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Gateway is not available.']);
    }

    public function test_create_payment_is_rejected_before_provisioning_when_paymongo_does_not_allow_the_method(): void
    {
        Cache::flush();
        Http::fake([
            'https://api.paymongo.com/v1/merchants/capabilities/payment_methods' => Http::response([
                'data' => ['attributes' => ['payment_methods' => ['qrph']]],
            ], 200),
        ]);

        $user = User::factory()->withMerchantApiKey('test-key')->create();
        $this->enableGcashFor($user);

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'gcash',
            'reference' => 'ref-paymongo-disabled',
        ], ['Authorization' => 'Bearer test-key']);

        $response->assertStatus(422);
        $response->assertJsonPath('error', "This payment method is not enabled for KemoPay's PayMongo account. Activate it in PayMongo Settings > Payment Methods.");
        $this->assertDatabaseMissing('payments', ['merchant_id' => $user->merchant_id]);
        Http::assertSentCount(1);
    }

    public function test_create_payment_returns_service_unavailable_when_paymongo_capabilities_cannot_be_verified(): void
    {
        Cache::flush();
        Http::fake([
            'https://api.paymongo.com/v1/merchants/capabilities/payment_methods' => Http::response([
                'errors' => [['code' => 'authentication_invalid']],
            ], 401),
        ]);

        $user = User::factory()->withMerchantApiKey('test-key')->create();
        $this->enableGcashFor($user);

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'gcash',
            'reference' => 'ref-paymongo-unverified',
        ], ['Authorization' => 'Bearer test-key']);

        $response->assertStatus(503);
        $response->assertSee('KemoPay could not verify this PayMongo payment method');
        $this->assertDatabaseMissing('payments', ['merchant_id' => $user->merchant_id]);
    }

    public function test_create_payment_uses_shared_platform_credentials_from_paymongo_record(): void
    {
        config()->set('paymongo.gateway.secret_key', '');
        config()->set('paymongo.gateway.public_key', '');
        config()->set('paymongo.gateway.webhook_secret', '');

        Http::fake(function ($request) {
            return match (parse_url($request->url(), PHP_URL_PATH)) {
                '/v1/merchants/capabilities/payment_methods' => Http::response([
                    'data' => ['attributes' => ['payment_methods' => ['qrph', 'gcash', 'paymaya', 'grab_pay', 'shopeepay']]],
                ], 200),
                '/v1/payment_intents' => Http::response([
                    'data' => ['id' => 'pi_shared_config_001', 'attributes' => ['client_key' => 'pi_shared_config_001_client_key']],
                ], 200),
                '/v1/payment_methods' => Http::response(['data' => ['id' => 'pm_shared_config_001']], 200),
                '/v1/payment_intents/pi_shared_config_001/attach' => Http::response([
                    'data' => [
                        'id' => 'pi_shared_config_001',
                        'attributes' => [
                            'status' => 'awaiting_next_action',
                            'next_action' => ['redirect' => ['url' => 'https://wallet.example/gcash']],
                        ],
                    ],
                ], 200),
                default => Http::response(['message' => 'Unexpected PayMongo request.'], 404),
            };
        });

        $paymongoGateway = Gateway::query()->where('code', 'paymongo')->firstOrFail();
        $paymongoGateway->update([
            'config_json' => [
                'secret_key' => 'sk_test_db_shared_secret',
                'public_key' => 'pk_test_db_shared_public',
                'webhook_secret' => 'whsk_test_db_shared_webhook',
            ],
        ]);

        $user = User::factory()->withMerchantApiKey('test-key')->create();
        $this->enableGcashFor($user);

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'gcash',
            'reference' => 'ref-db-platform-1',
        ], ['Authorization' => 'Bearer test-key']);

        $response->assertCreated();
        $response->assertJsonPath('data.gateway', 'gcash');
        $response->assertJsonPath('data.redirect_url', 'https://wallet.example/gcash');
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_db_shared_secret:')));
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('pk_test_db_shared_public:')));
    }

    private function enableGcashFor(User $user): void
    {
        $gateway = Gateway::query()->where('code', 'gcash')->firstOrFail();

        MerchantGateway::query()->create([
            'merchant_id' => $user->merchant_id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);
    }
}
