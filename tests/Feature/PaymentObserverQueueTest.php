<?php

namespace Tests\Feature;

use App\Enums\PlatformFeeStatus;
use App\Jobs\ProcessPaymentPaidEffectsJob;
use App\Jobs\ProcessPaymentReversalEffectsJob;
use App\Models\Payment;
use App\Models\PlatformFee;
use App\Services\Billing\PlatformFeeService;
use App\Services\Billing\WalletSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentObserverQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_status_transition_dispatches_paid_effects_job(): void
    {
        Queue::fake();

        $payment = Payment::factory()->create([
            'status' => 'pending',
            'paid_at' => null,
            'platform_fee' => null,
            'net_amount' => null,
        ]);

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Queue::assertPushed(ProcessPaymentPaidEffectsJob::class, function (ProcessPaymentPaidEffectsJob $job) use ($payment): bool {
            return $job->paymentId === $payment->id;
        });
    }

    public function test_refunded_status_transition_dispatches_reversal_effects_job(): void
    {
        Queue::fake();

        $payment = Payment::factory()->create([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $payment->update([
            'status' => 'refunded',
        ]);

        Queue::assertPushed(ProcessPaymentReversalEffectsJob::class, function (ProcessPaymentReversalEffectsJob $job) use ($payment): bool {
            return $job->paymentId === $payment->id
                && $job->reason === 'Payment refunded';
        });
    }

    public function test_paid_and_reversal_jobs_process_platform_fee_without_wallet_settlement_when_feature_disabled(): void
    {
        config(['platform.fees' => ['percentage' => 1.5, 'fixed' => 5]]);

        $payment = Payment::factory()->create([
            'amount' => 1000,
            'status' => 'paid',
            'paid_at' => now(),
            'platform_fee' => null,
            'net_amount' => null,
        ]);

        (new ProcessPaymentPaidEffectsJob($payment->id))
            ->handle(app(PlatformFeeService::class), app(WalletSettlementService::class));

        $payment->refresh();
        $this->assertNotNull($payment->platform_fee);
        $this->assertNotNull($payment->net_amount);
        $this->assertSame(
            round((float) $payment->amount, 2),
            round((float) $payment->platform_fee + (float) $payment->net_amount, 2)
        );
        $this->assertSame(0, $payment->walletTransactions()->count());

        $payment->update(['status' => 'refunded']);

        (new ProcessPaymentReversalEffectsJob($payment->id, 'Payment refunded'))
            ->handle(app(PlatformFeeService::class), app(WalletSettlementService::class));

        $fee = PlatformFee::query()->where('payment_id', $payment->id)->firstOrFail();
        $this->assertSame(PlatformFeeStatus::Reversed, $fee->status);
        $this->assertSame(0, $payment->walletTransactions()->count());
    }
}
