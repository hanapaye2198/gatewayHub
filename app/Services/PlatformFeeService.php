<?php

namespace App\Services;

use App\Models\Payment;

/**
 * Single place for platform fee calculation and persistence.
 * Idempotent: no duplicate calculations when platform_fee is already set.
 * Accounting/reporting only; does not modify gateway amounts.
 */
class PlatformFeeService
{
    /**
     * Apply platform fee and net_amount when eligible, then persist.
     * Eligible when: status is paid and platform_fee is not yet set.
     * When applied, saves the payment; otherwise leaves persistence to the caller.
     */
    public function applyIfEligible(Payment $payment): void
    {
        if ($payment->status !== 'paid') {
            return;
        }

        if ($payment->platform_fee !== null) {
            return;
        }

        $this->calculateAndPersist($payment);
    }

    /**
     * Calculate platform_fee and net_amount and persist to the database.
     */
    private function calculateAndPersist(Payment $payment): void
    {
        $percent = config('platform.fee_percent', 0.005);
        $amount = (float) $payment->amount;
        $fee = round($amount * $percent, 2);
        $net = round($amount - $fee, 2);

        $payment->platform_fee = $fee;
        $payment->net_amount = $net;
        $payment->save();
    }
}
