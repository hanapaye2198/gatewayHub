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

    public function test_patch_rejects_internal_callback_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson(route('merchant.webhook.update'), [
                'webhook_url' => 'http://127.0.0.1/webhook',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['webhook_url']);
    }

    public function test_patch_rejects_metadata_callback_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson(route('merchant.webhook.update'), [
                'webhook_url' => 'http://169.254.169.254/latest/meta-data',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['webhook_url']);
    }

    public function test_webhook_job_skips_internal_callback_url(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $user->merchant->forceFill([
            'webhook_url' => 'http://127.0.0.1/webhook',
            'webhook_secret' => 'merchant-webhook-secret',
        ])->save();

        $payment = Payment::factory()->create([
            'merchant_id' => $user->merchant_id,
            'status' => 'paid',
        ]);

        (new SendMerchantWebhookJob($payment->id))->handle();

        Http::assertNothingSent();
    }

    public function test_patch_rejects_invalid_callback_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson(route('merchant.webhook.update'), [
                'webhook_url' => 'not-a-valid-callback-url',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['webhook_url']);
    }

    public function test_patch_accepts_https_callback_url_with_path_and_query(): void
    {
        $user = User::factory()->create();
        $url = 'https://api.merchant.example:8443/v1/payments/callback?source=gh';

        $this->actingAs($user)
            ->patchJson(route('merchant.webhook.update'), [
                'webhook_url' => $url,
            ])
            ->assertOk();

        $this->assertSame($url, $user->merchant->fresh()->webhook_url);
    }

    public function test_patch_accepts_null_callback_url_to_clear(): void
    {
        $user = User::factory()->create();
        $user->merchant->forceFill([
            'webhook_url' => 'https://old.example/callback',
        ])->save();

        $this->actingAs($user)
            ->patchJson(route('merchant.webhook.update'), [
                'webhook_url' => null,
            ])
            ->assertOk();

        $this->assertNull($user->merchant->fresh()->webhook_url);
    }

    public function test_patch_rejects_callback_url_exceeding_max_length(): void
    {
        $user = User::factory()->create();
        $tooLong = 'https://example.com/'.str_repeat('a', 250);

        $this->actingAs($user)
            ->patchJson(route('merchant.webhook.update'), [
                'webhook_url' => $tooLong,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['webhook_url']);
    }

    public function test_merchant_can_set_short_webhook_secret_via_patch(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('merchant.webhook.update'), [
            'webhook_url' => 'https://merchant.example/webhooks',
            'webhook_secret' => 'tiny',
            'regenerate_secret' => false,
        ]);

        $response->assertOk();
        $this->assertSame('tiny', $user->merchant->fresh()->webhook_secret);
    }

    public function test_patch_webhook_url_without_regenerate_does_not_create_secret(): void
    {
        $user = User::factory()->create();
        $user->merchant->forceFill(['webhook_secret' => null])->save();

        $response = $this->actingAs($user)->patch(route('merchant.webhook.update'), [
            'webhook_url' => 'https://merchant.example/webhooks',
            'regenerate_secret' => false,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'webhook_secret' => null,
        ]);

        $this->assertNull($user->merchant->fresh()->webhook_secret);
    }

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
            'amount' => 1000,
            'status' => 'paid',
            'platform_fee' => 15,
            'net_amount' => 985,
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
            $this->assertSame(1000, $payload['data']['gross_amount'] ?? null);
            $this->assertSame(1.5, $payload['data']['gatewayhub_platform_fee_percent'] ?? null);
            $this->assertSame(15, $payload['data']['gatewayhub_platform_fee'] ?? null);
            $this->assertSame(985, $payload['data']['gatewayhub_net_amount'] ?? null);

            return true;
        });
    }
}
