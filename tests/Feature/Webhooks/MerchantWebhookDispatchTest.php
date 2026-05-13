<?php

namespace Tests\Feature\Webhooks;

use App\Jobs\SendMerchantWebhookJob;
use App\Models\Merchant;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MerchantWebhookDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_dispatched_when_payment_transitions_from_pending_to_paid(): void
    {
        Queue::fake();

        $merchant = Merchant::factory()->create([
            'webhook_url' => 'https://merchant.test/webhook',
            'webhook_secret' => 'test-secret-key',
        ]);

        $payment = Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Queue::assertPushed(SendMerchantWebhookJob::class, function (SendMerchantWebhookJob $job) use ($payment): bool {
            return $job->paymentId === $payment->id;
        });
    }

    public function test_webhook_dispatched_when_payment_transitions_from_provisioning_to_paid(): void
    {
        Queue::fake();

        $merchant = Merchant::factory()->create([
            'webhook_url' => 'https://merchant.test/webhook',
            'webhook_secret' => 'test-secret-key',
        ]);

        $payment = Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'status' => 'provisioning',
            'paid_at' => null,
        ]);

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Queue::assertPushed(SendMerchantWebhookJob::class, function (SendMerchantWebhookJob $job) use ($payment): bool {
            return $job->paymentId === $payment->id;
        });
    }

    public function test_webhook_dispatched_when_payment_transitions_from_pending_to_failed(): void
    {
        Queue::fake();

        $merchant = Merchant::factory()->create([
            'webhook_url' => 'https://merchant.test/webhook',
            'webhook_secret' => 'test-secret-key',
        ]);

        $payment = Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $payment->update([
            'status' => 'failed',
        ]);

        Queue::assertPushed(SendMerchantWebhookJob::class, function (SendMerchantWebhookJob $job) use ($payment): bool {
            return $job->paymentId === $payment->id;
        });
    }

    public function test_webhook_not_dispatched_when_merchant_has_no_webhook_url(): void
    {
        Queue::fake();
        Log::spy();

        $merchant = Merchant::factory()->create([
            'webhook_url' => null,
            'webhook_secret' => 'test-secret-key',
        ]);

        $payment = Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Queue::assertNotPushed(SendMerchantWebhookJob::class);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($payment): bool {
                return $message === 'Merchant webhook dispatch skipped: missing configuration'
                    && $context['payment_id'] === $payment->id
                    && $context['has_webhook_url'] === false;
            });
    }

    public function test_webhook_not_dispatched_when_merchant_has_no_webhook_secret(): void
    {
        Queue::fake();
        Log::spy();

        $merchant = Merchant::factory()->create([
            'webhook_url' => 'https://merchant.test/webhook',
            'webhook_secret' => null,
        ]);

        $payment = Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Queue::assertNotPushed(SendMerchantWebhookJob::class);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($payment): bool {
                return $message === 'Merchant webhook dispatch skipped: missing configuration'
                    && $context['payment_id'] === $payment->id
                    && $context['has_webhook_secret'] === false;
            });
    }

    public function test_webhook_job_sends_correct_payload_to_merchant_url(): void
    {
        Http::fake([
            'merchant.test/*' => Http::response(['ok' => true], 200),
        ]);

        $merchant = Merchant::factory()->create([
            'webhook_url' => 'https://merchant.test/webhook',
            'webhook_secret' => 'test-secret-key-123',
        ]);

        $payment = Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'status' => 'paid',
            'paid_at' => now(),
            'amount' => 100.00,
            'currency' => 'PHP',
            'gateway_code' => 'gcash',
        ]);

        (new SendMerchantWebhookJob($payment->id))->handle();

        Http::assertSent(function ($request) use ($payment): bool {
            $body = $request->data();

            return $request->url() === 'https://merchant.test/webhook'
                && $body['event'] === 'payment.updated'
                && $body['data']['payment_id'] === $payment->id
                && $body['data']['status'] === 'paid'
                && (float) $body['data']['amount'] === 100.00
                && $body['data']['currency'] === 'PHP'
                && $body['data']['gateway'] === 'gcash'
                && $request->hasHeader('X-Merchant-Signature')
                && $request->hasHeader('X-Merchant-Timestamp')
                && $request->header('User-Agent')[0] === 'GatewayHub-Webhooks/1.0';
        });
    }

    public function test_webhook_job_includes_valid_hmac_signature(): void
    {
        Http::fake([
            'merchant.test/*' => Http::response(['ok' => true], 200),
        ]);

        $secret = 'my-webhook-secret-for-hmac';
        $merchant = Merchant::factory()->create([
            'webhook_url' => 'https://merchant.test/webhook',
            'webhook_secret' => $secret,
        ]);

        $payment = Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        (new SendMerchantWebhookJob($payment->id))->handle();

        Http::assertSent(function ($request) use ($secret): bool {
            $timestamp = $request->header('X-Merchant-Timestamp')[0] ?? '';
            $signature = $request->header('X-Merchant-Signature')[0] ?? '';
            $body = json_encode($request->data(), JSON_UNESCAPED_SLASHES);

            $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

            return $signature === $expected;
        });
    }

    public function test_webhook_job_silently_returns_when_payment_not_found(): void
    {
        Http::fake();

        (new SendMerchantWebhookJob('non-existent-id'))->handle();

        Http::assertNothingSent();
    }

    public function test_webhook_job_logs_warning_when_secret_missing_at_dispatch_time(): void
    {
        Http::fake();
        Log::spy();

        $merchant = Merchant::factory()->create([
            'webhook_url' => 'https://merchant.test/webhook',
            'webhook_secret' => null,
        ]);

        $payment = Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        (new SendMerchantWebhookJob($payment->id))->handle();

        Http::assertNothingSent();

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($payment, $merchant): bool {
                return $message === 'SendMerchantWebhookJob: missing webhook_secret'
                    && $context['payment_id'] === $payment->id
                    && $context['merchant_id'] === $merchant->id;
            });
    }
}
