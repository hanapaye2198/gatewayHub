<?php

namespace Tests\Feature\Api;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\User;
use App\Services\Gateways\Drivers\CoinsDriver;
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

        Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => CoinsDriver::class,
            'is_global_enabled' => true,
        ]);
    }

    public function test_api_returns_429_when_rate_limit_exceeded(): void
    {
        Http::fake(['*' => Http::response(['code' => 0, 'data' => ['orderId' => 'x', 'qrCode' => 'y']], 200)]);

        $user = User::factory()->withMerchantApiKey('test-api-key')->create();
        MerchantGateway::query()->create([
            'merchant_id' => $user->id,
            'gateway_id' => Gateway::query()->where('code', 'coins')->firstOrFail()->id,
            'is_enabled' => true,
            'config_json' => [
                'client_id' => 'c',
                'client_secret' => 's',
                'api_base' => 'sandbox',
            ],
        ]);

        $maxAttempts = config('rate-limiting.api.max_attempts', 60);
        $headers = ['Authorization' => 'Bearer test-api-key'];

        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = $this->postJson('/api/payments', [
                'amount' => 100,
                'currency' => 'PHP',
                'gateway' => 'coins',
                'reference' => 'ref-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            ], $headers);
            $response->assertSuccessful();
        }

        $response = $this->postJson('/api/payments', [
            'amount' => 100,
            'currency' => 'PHP',
            'gateway' => 'coins',
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
