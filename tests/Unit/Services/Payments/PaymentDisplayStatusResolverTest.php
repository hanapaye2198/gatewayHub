<?php

namespace Tests\Unit\Services\Payments;

use App\Enums\PaymentDisplayStatus;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentDisplayStatusResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentDisplayStatusResolverTest extends TestCase
{
    use RefreshDatabase;

    private PaymentDisplayStatusResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PaymentDisplayStatusResolver;
    }

    public function test_paid_payment_displays_as_paid(): void
    {
        $payment = Payment::factory()->paid()->create();

        $this->assertSame(PaymentDisplayStatus::Paid, $this->resolver->resolve($payment));
    }

    public function test_pending_payment_displays_as_pending(): void
    {
        $payment = Payment::factory()->create(['status' => 'pending']);

        $this->assertSame(PaymentDisplayStatus::Pending, $this->resolver->resolve($payment));
    }

    public function test_provisioning_failed_displays_as_provisioning_failed(): void
    {
        $payment = Payment::factory()->create(['status' => 'provisioning_failed']);

        $this->assertSame(PaymentDisplayStatus::ProvisioningFailed, $this->resolver->resolve($payment));
    }

    public function test_expired_webhook_displays_as_expired_while_database_status_remains_failed(): void
    {
        $payment = Payment::factory()->failed()->create([
            'raw_response' => ['status' => 'PENDING'],
        ]);
        $this->attachWebhook($payment, 'EXPIRED', now());

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame(PaymentDisplayStatus::Expired, $this->resolver->resolve($payment->fresh()->load('webhookEvents')));

        $summary = $this->resolver->summarize(Payment::query()->whereKey($payment->id));
        $this->assertSame(1, $summary['expired_count']);
        $this->assertSame(0, $summary['failed_count']);
    }

    public function test_failed_webhook_displays_as_failed(): void
    {
        $payment = Payment::factory()->failed()->create();
        $this->attachWebhook($payment, 'FAILED', now());

        $this->assertSame(PaymentDisplayStatus::Failed, $this->resolver->resolve($payment->fresh()->load('webhookEvents')));
    }

    public function test_cancelled_webhook_displays_as_failed(): void
    {
        $payment = Payment::factory()->failed()->create();
        $this->attachWebhook($payment, 'CANCELLED', now());

        $this->assertSame(PaymentDisplayStatus::Failed, $this->resolver->resolve($payment->fresh()->load('webhookEvents')));
    }

    public function test_canceled_webhook_displays_as_failed(): void
    {
        $payment = Payment::factory()->failed()->create();
        $this->attachWebhook($payment, 'CANCELED', now());

        $this->assertSame(PaymentDisplayStatus::Failed, $this->resolver->resolve($payment->fresh()->load('webhookEvents')));
    }

    public function test_missing_provider_status_falls_back_to_failed(): void
    {
        $payment = Payment::factory()->failed()->create([
            'raw_response' => ['gateway_request_reference' => 'GH-1'],
        ]);
        $payment->setRelation('webhookEvents', collect());

        $this->assertSame(PaymentDisplayStatus::Failed, $this->resolver->resolve($payment));
    }

    public function test_local_expires_at_does_not_classify_failed_payment_as_expired(): void
    {
        $payment = Payment::factory()->failed()->create([
            'raw_response' => [
                'expires_at' => now()->subHour()->toIso8601String(),
            ],
        ]);
        $payment->setRelation('webhookEvents', collect());

        $this->assertSame(PaymentDisplayStatus::Failed, $this->resolver->resolve($payment));
    }

    public function test_resolve_does_not_lazy_load_webhook_events(): void
    {
        $payment = Payment::factory()->failed()->create([
            'raw_response' => ['status' => 'EXPIRED'],
        ]);

        $this->assertSame(PaymentDisplayStatus::Expired, $this->resolver->resolve($payment));
        $this->assertFalse($payment->relationLoaded('webhookEvents'));
    }

    public function test_latest_webhook_status_wins_over_older_events(): void
    {
        $payment = Payment::factory()->failed()->create();
        $this->attachWebhook($payment, 'FAILED', now()->subMinutes(2));
        $this->attachWebhook($payment, 'EXPIRED', now()->subMinute());

        $this->assertSame(
            PaymentDisplayStatus::Expired,
            $this->resolver->resolve($payment->fresh()->load('webhookEvents'))
        );
    }

    public function test_summarize_splits_expired_from_genuine_failed(): void
    {
        Payment::factory()->paid()->create();
        Payment::factory()->create(['status' => 'pending']);
        Payment::factory()->create(['status' => 'provisioning_failed']);
        Payment::factory()->failed()->create(['raw_response' => ['status' => 'EXPIRED']]);
        Payment::factory()->failed()->create(['raw_response' => ['status' => 'FAILED']]);

        $summary = $this->resolver->summarize(Payment::query());

        $this->assertSame(1, $summary['expired_count']);
        $this->assertSame(1, $summary['failed_count']);
        $this->assertSame(1, $summary['pending_count']);
        $this->assertSame(1, $summary['provisioning_failed_count']);
        $this->assertSame(5, $summary['total_transactions']);
    }

    public function test_failed_filter_excludes_expired_evidence(): void
    {
        $expired = Payment::factory()->failed()->create([
            'reference_id' => 'EXPIRED-FILTER',
            'raw_response' => ['status' => 'EXPIRED'],
        ]);
        $failed = Payment::factory()->failed()->create([
            'reference_id' => 'FAILED-FILTER',
            'raw_response' => ['status' => 'FAILED'],
        ]);

        $expiredIds = Payment::query();
        $this->resolver->applyFilter($expiredIds, 'expired');
        $this->assertEqualsCanonicalizing([$expired->id], $expiredIds->pluck('id')->all());

        $failedIds = Payment::query();
        $this->resolver->applyFilter($failedIds, 'failed');
        $this->assertEqualsCanonicalizing([$failed->id], $failedIds->pluck('id')->all());
    }

    public function test_stale_raw_response_expired_does_not_override_later_failed_webhook_for_display_or_counts(): void
    {
        $payment = Payment::factory()->failed()->create([
            'raw_response' => [
                'qrcodeStatus' => 'EXPIRED',
                'status' => 'FAILED',
            ],
        ]);
        $this->attachWebhook($payment, 'EXPIRED', now()->subMinutes(2), ['qrcodeStatus' => 'EXPIRED']);
        $this->attachWebhook($payment, 'FAILED', now()->subMinute(), ['status' => 'FAILED']);

        $resolved = $this->resolver->resolve($payment->fresh()->load('webhookEvents'));
        $this->assertSame(PaymentDisplayStatus::Failed, $resolved);

        $summary = $this->resolver->summarize(Payment::query()->whereKey($payment->id));
        $this->assertSame(0, $summary['expired_count']);
        $this->assertSame(1, $summary['failed_count']);

        $expiredIds = Payment::query()->whereKey($payment->id);
        $this->resolver->applyFilter($expiredIds, 'expired');
        $this->assertSame([], $expiredIds->pluck('id')->all());

        $failedIds = Payment::query()->whereKey($payment->id);
        $this->resolver->applyFilter($failedIds, 'failed');
        $this->assertEqualsCanonicalizing([$payment->id], $failedIds->pluck('id')->all());
    }

    public function test_latest_expired_webhook_wins_over_stale_raw_response_failed_for_display_and_counts(): void
    {
        $payment = Payment::factory()->failed()->create([
            'raw_response' => [
                'status' => 'FAILED',
                'qrcodeStatus' => 'EXPIRED',
            ],
        ]);
        $this->attachWebhook($payment, 'FAILED', now()->subMinutes(2), ['status' => 'FAILED']);
        $this->attachWebhook($payment, 'EXPIRED', now()->subMinute(), ['qrcodeStatus' => 'EXPIRED']);

        $resolved = $this->resolver->resolve($payment->fresh()->load('webhookEvents'));
        $this->assertSame(PaymentDisplayStatus::Expired, $resolved);

        $summary = $this->resolver->summarize(Payment::query()->whereKey($payment->id));
        $this->assertSame(1, $summary['expired_count']);
        $this->assertSame(0, $summary['failed_count']);

        $expiredIds = Payment::query()->whereKey($payment->id);
        $this->resolver->applyFilter($expiredIds, 'expired');
        $this->assertEqualsCanonicalizing([$payment->id], $expiredIds->pluck('id')->all());

        $failedIds = Payment::query()->whereKey($payment->id);
        $this->resolver->applyFilter($failedIds, 'failed');
        $this->assertSame([], $failedIds->pluck('id')->all());
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function attachWebhook(Payment $payment, string $status, \DateTimeInterface $receivedAt, ?array $payload = null): void
    {
        WebhookEvent::query()->create([
            'provider' => 'coins',
            'event_id' => $payment->id.'-'.$status.'-'.$receivedAt->getTimestamp(),
            'payment_id' => $payment->id,
            'payload' => $payload ?? ['status' => $status, 'referenceId' => $payment->provider_reference],
            'headers' => [],
            'received_at' => $receivedAt,
            'processed_at' => $receivedAt,
            'status' => 'processed',
        ]);
    }
}
