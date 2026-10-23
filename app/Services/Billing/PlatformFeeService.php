<?php

namespace App\Services\Billing;

use App\Enums\PlatformFeeStatus;
use App\Models\Payment;
use App\Models\PlatformFee;
use App\Models\PlatformFeeRule;
use Illuminate\Support\Facades\DB;

/**
 * Single, safe entry point for platform revenue.
 * Accepts Payment; calculates the KemoPay fee from the configured global rate and creates the platform_fees record.
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
            $productAmount = $locked->product_amount !== null
                ? (float) $locked->product_amount
                : $gross;
            $calculated = $this->calculateFromConfig($productAmount);
            $feeAmount = $locked->payer_fee_amount !== null
                ? (float) $locked->payer_fee_amount
                : $calculated['fee_amount'];
            $feeRate = $locked->platform_fee_rate !== null
                ? (float) $locked->platform_fee_rate
                : $calculated['fee_rate'];
            $netAmount = $locked->product_amount !== null
                ? $productAmount
                : $calculated['net_amount'];

            PlatformFee::query()->create([
                'payment_id' => $locked->id,
                'merchant_id' => $locked->merchant_id,
                'gateway_code' => $locked->gateway_code,
                'gross_amount' => $gross,
                'fee_rate' => $feeRate,
                'fee_amount' => $feeAmount,
                'net_amount' => $netAmount,
                'status' => PlatformFeeStatus::Posted,
            ]);

            $locked->platform_fee = $feeAmount;
            $locked->net_amount = $netAmount;
            $locked->save();
        });
    }

    /**
     * Calculate the customer charge while preserving the merchant's product value.
     *
     * @return array{fee_rate: float, fee_amount: float, product_amount: float, gross_amount: float}
     */
    public function calculatePayerChargedTotals(float $productAmount): array
    {
        $productAmount = round($productAmount, 2);
        $calculated = $this->calculateFromConfig($productAmount);
        $feeAmount = $calculated['fee_amount'];

        return [
            'fee_rate' => $calculated['fee_rate'],
            'fee_amount' => $feeAmount,
            'product_amount' => $productAmount,
            'gross_amount' => round($productAmount + $feeAmount, 2),
        ];
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
     * Calculate the KemoPay platform fee from gross amount.
     * Formula: fee = round(gross × configured percentage / 100, 2).
     * The percentage comes from the active global platform fee rule.
     *
     * @return array{fee_rate: float, fee_amount: float, net_amount: float}
     */
    public function calculateFromConfig(float $grossAmount): array
    {
        $percentage = PlatformFeeRule::configuredPercentage();
        $feeAmount = round($grossAmount * $percentage / 100, 2);
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
