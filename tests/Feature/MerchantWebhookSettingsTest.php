<?php

namespace Tests\Feature;

use App\Jobs\SendMerchantWebhookJob;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class MerchantWebhookSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_update_webhook_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('merchant.webhook.update'), [
            'webhook_url' => 'https://merchant.example/webhooks',
            'regenerate_secret' => true,
        ]);

        $response->assertOk();
        $response->assertJson(fn (AssertableJson $json) => $json
            ->where('success', true)
            ->where('merchant.webhook_url', 'https://merchant.example/webhooks')
            ->where('webhook_secret', fn ($value) => is_string($value) && $value !== '')
        );

        $merchant = $user->merchant->fresh();
        $this->assertSame('https://merchant.example/webhooks', $merchant->webhook_url);
        $this->assertNotNull($merchant->webhook_secret);
    }

    public function test_webhook_settings_section_renders_on_api_credentials_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard.api-credentials'));

        $response->assertOk();
        $response->assertSee('Webhook Settings');
        $response->assertSee('Webhook URL');
    }

    public function test_payment_status_change_dispatches_merchant_webhook_job(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $user->merchant->forceFill([
            'webhook_url' => 'https://merchant.example/webhooks',
            'webhook_secret' => 'merchant-webhook-secret',
        ])->save();

        $payment = Payment::factory()->create([
            'merchant_id' => $user->merchant_id,
            'status' => 'pending',
        ]);

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Bus::assertDispatched(SendMerchantWebhookJob::class, function (SendMerchantWebhookJob $job) use ($payment) {
            return $job->paymentId === $payment->id;
        });
    }

    public function test_webhook_job_signs_payload_with_secret(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $user->merchant->forceFill([
            'webhook_url' => 'https://merchant.example/webhooks',
            'webhook_secret' => 'merchant-webhook-secret',
        ])->save();

        $payment = Payment::factory()->create([
            'merchant_id' => $user->merchant_id,
            'status' => 'paid',
        ]);

        (new SendMerchantWebhookJob($payment->id))->handle();

        Http::assertSent(function (Request $request) use ($payment) {
            $this->assertSame('https://merchant.example/webhooks', $request->url());

            $timestamp = $request->header('X-Merchant-Timestamp')[0] ?? null;
            $signature = $request->header('X-Merchant-Signature')[0] ?? null;

            $this->assertIsString($timestamp);
            $this->assertIsString($signature);

            $body = $request->body();
            $expected = hash_hmac('sha256', $timestamp.'.'.$body, 'merchant-webhook-secret');
            $this->assertSame($expected, $signature);

            $payload = json_decode($body, true);
            $this->assertIsArray($payload);
            $this->assertSame($payment->id, $payload['data']['payment_id'] ?? null);

            return true;
        });
    }
}
