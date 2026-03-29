<?php

namespace App\Services\Billing;

use App\Enums\PlatformFeeStatus;
use App\Models\Payment;
use App\Models\PlatformFee;
use App\Models\PlatformFeeRule;
use Illuminate\Support\Facades\DB;

/**
 * Single, safe entry point for platform revenue.
 * Accepts Payment; resolves rule, calculates fee_amount/net_amount, creates platform_fees record.
 * Idempotent; uses transaction and payment row lock. No UI or gateway logic.
 */
class PlatformFeeService
{
    /**
     * Record platform fee when payment has transitioned to paid. Call after webhook verification and idempotency checks.
     * Aborts if status != paid or ledger already exists. Uses DB transaction and payment row lock.
     */
    public function record(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $locked = Payment::query()
                ->where('id', $payment->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                return;
            }

            if ($locked->status !== 'paid') {
                return;
            }

            if ($locked->platformFee()->exists()) {
                return;
            }

            $gross = (float) $locked->amount;

            $paymentDate = $locked->paid_at ?? $locked->created_at ?? now();
            $rule = app(FeeRuleResolver::class)->resolve(
                (int) $locked->merchant_id,
                $locked->gateway_code,
                $paymentDate
            );

            $calculated = $rule !== null
                ? $this->calculateFromRule($gross, $rule)
                : $this->calculateFromConfig($gross);

            PlatformFee::query()->create([
                'payment_id' => $locked->id,
                'merchant_id' => $locked->merchant_id,
                'gateway_code' => $locked->gateway_code,
                'gross_amount' => $gross,
                'fee_rate' => $calculated['fee_rate'],
                'fee_amount' => $calculated['fee_amount'],
                'net_amount' => $calculated['net_amount'],
                'status' => PlatformFeeStatus::Posted,
            ]);

            $locked->platform_fee = $calculated['fee_amount'];
            $locked->net_amount = $calculated['net_amount'];
            $locked->save();
        });
    }

    /**
     * Mark platform fee as reversed when a payment is refunded.
     * Does not delete records; stores reason and reversed_at. Idempotent if already reversed.
     */
    public function reverseForPayment(Payment $payment, string $reason): void
    {
        $fee = $payment->platformFee()->first();
        if ($fee === null || $fee->status === PlatformFeeStatus::Reversed) {
            return;
        }

        $fee->update([
            'status' => PlatformFeeStatus::Reversed,
            'reversal_reason' => $reason,
            'reversed_at' => now(),
        ]);
    }

    /**
     * Calculate from config (percentage + fixed). Formula: (gross * percentage/100) + fixed.
     *
     * @return array{fee_rate: float, fee_amount: float, net_amount: float}
     */
    public function calculateFromConfig(float $grossAmount): array
    {
        $percentage = config('platform.fees.percentage', 0);
        $fixed = config('platform.fees.fixed', 0);
        $feeAmount = round(($grossAmount * $percentage / 100) + $fixed, 2);
        $netAmount = round($grossAmount - $feeAmount, 2);

        return [
            'fee_rate' => (float) $percentage / 100,
            'fee_amount' => $feeAmount,
            'net_amount' => $netAmount,
        ];
    }

    /**
     * Calculate fee_amount and net_amount from gross and rule.
     *
     * @return array{fee_rate: float, fee_amount: float, net_amount: float}
     */
    public function calculateFromRule(float $grossAmount, PlatformFeeRule $rule): array
    {
        $feeValue = (float) $rule->fee_value;

        if ($rule->fee_type === 'percentage') {
            $feeAmount = round($grossAmount * $feeValue, 2);
            $feeRate = $feeValue;
        } else {
            $feeAmount = round($feeValue, 2);
            $feeRate = 0.0;
        }

        $netAmount = round($grossAmount - $feeAmount, 2);

        return [
            'fee_rate' => $feeRate,
            'fee_amount' => $feeAmount,
            'net_amount' => $netAmount,
        ];
    }
}
