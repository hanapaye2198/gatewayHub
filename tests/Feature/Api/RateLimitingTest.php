<?php

namespace Tests\Feature\Api;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\User;
use App\Services\Gateways\Drivers\PayMongoDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitingTest extends TestCase
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
        Gateway::query()->updateOrCreate(['code' => 'gcash'], [
            'name' => 'GCash',
            'driver_class' => PayMongoDriver::class,
            'is_global_enabled' => true,
        ]);
    }

    public function test_api_returns_429_when_rate_limit_exceeded(): void
    {
        $intentCount = 0;
        $paymentMethodCount = 0;
        Http::fake(function ($request) use (&$intentCount, &$paymentMethodCount) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/v1/merchants/capabilities/payment_methods') {
                return Http::response([
                    'data' => ['attributes' => ['payment_methods' => ['qrph', 'gcash', 'paymaya', 'grab_pay', 'shopeepay']]],
                ], 200);
            }

            if ($path === '/v1/payment_intents' && $request->method() === 'POST') {
                $intentCount++;
                $intentId = 'pi_rate_limit_'.$intentCount;

                return Http::response([
                    'data' => ['id' => $intentId, 'attributes' => ['client_key' => $intentId.'_client_key']],
                ], 200);
            }

            if ($path === '/v1/payment_methods') {
                $paymentMethodCount++;

                return Http::response(['data' => ['id' => 'pm_rate_limit_'.$paymentMethodCount]], 200);
            }

            if (is_string($path) && preg_match('#^/v1/payment_intents/(pi_rate_limit_\d+)/attach$#', $path, $matches) === 1) {
                return Http::response([
                    'data' => [
                        'id' => $matches[1],
                        'attributes' => ['next_action' => ['redirect' => ['url' => 'https://wallet.example/gcash']]],
                    ],
                ], 200);
            }

            return Http::response(['message' => 'Unexpected PayMongo request.'], 404);
        });

        $user = User::factory()->withMerchantApiKey('test-api-key')->create();
        MerchantGateway::query()->create([
            'merchant_id' => $user->id,
            'gateway_id' => Gateway::query()->where('code', 'gcash')->firstOrFail()->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $maxAttempts = config('rate-limiting.api.max_attempts', 60);
        $headers = ['Authorization' => 'Bearer test-api-key'];

        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = $this->postJson('/api/payments', [
                'amount' => 100,
                'currency' => 'PHP',
                'gateway' => 'gcash',
                'reference' => 'ref-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            ], $headers);
            $response->assertSuccessful();
        }

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'gcash',
            'reference' => 'ref-over-limit',
        ], $headers);

        $response->assertStatus(429);
        $response->assertJson(['error' => 'Too many requests. Please try again later.']);
    }

    public function test_webhooks_return_429_when_rate_limit_exceeded(): void
    {
        $limitKey = '127.0.0.1';
        $key = md5('webhooks'.$limitKey);
        RateLimiter::clear($key);
        for ($i = 0; $i < config('rate-limiting.webhooks.max_attempts', 200); $i++) {
            RateLimiter::hit($key);
        }

        $response = $this->postJson('/api/webhooks/coins', [
            'referenceId' => 'x',
            'status' => 'SUCCEEDED',
            'timestamp' => (string) (time() * 1000),
        ]);

        $response->assertStatus(429);
        $response->assertJson(['error' => 'Too many webhook requests. Please try again later.']);
    }
}
