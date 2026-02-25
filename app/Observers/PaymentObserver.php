<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\Billing\PlatformFeeService;
use Illuminate\Support\Facades\Log;

class PaymentObserver
{
    /**
     * Handle the Payment "created" event.
     */
    public function created(Payment $payment): void
    {
        //
    }

    /**
     * Handle the Payment "updated" event. Logs state changes and records platform fee when status transitions to paid.
     */
    public function updated(Payment $payment): void
    {
        if ($payment->wasChanged(['status', 'paid_at'])) {
            Log::info('Payment state changed', [
                'payment_id' => $payment->id,
                'reference_id' => $payment->reference_id,
                'gateway' => $payment->gateway_code,
                'status_old' => $payment->getOriginal('status'),
                'status_new' => $payment->status,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ]);
        }

        if ($payment->wasChanged('status') && $payment->status === 'paid') {
            app(PlatformFeeService::class)->record($payment);
        }

        if ($payment->wasChanged('status') && in_array($payment->status, ['refunded', 'failed_after_paid'], true)) {
            $reason = $payment->status === 'refunded' ? 'Payment refunded' : 'Failed after paid';
            app(PlatformFeeService::class)->reverseForPayment($payment, $reason);
        }
    }

    /**
     * Handle the Payment "deleted" event.
     */
    public function deleted(Payment $payment): void
    {
        //
    }

    /**
     * Handle the Payment "restored" event.
     */
    public function restored(Payment $payment): void
    {
        //
    }

    /**
     * Handle the Payment "force deleted" event.
     */
    public function forceDeleted(Payment $payment): void
    {
        //
    }
}
