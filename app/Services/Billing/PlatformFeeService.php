<?php

namespace App\Services\Billing;

use App\Enums\PlatformFeeStatus;
use App\Models\Payment;
use App\Models\PlatformFee;
use App\Models\PlatformFeeRule;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for platform revenue.
 * New payments snapshot an additive fee at creation. Paid effects copy that snapshot into the ledger.
 * Payments created before the additive model still use the original deduction when they are marked paid.
 */
class PlatformFeeService
{
    public function __construct(private FeeRuleResolver $feeRuleResolver) {}

    /**
     * Record platform fee when payment has transitioned to paid.
     * Aborts if status is not paid or a ledger row already exists.
     * Snapshotted payments are not recalculated.
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

            if ($locked->customer_total !== null || $locked->platform_fee !== null) {
                $this->createLedgerFromPayment($locked);

                return;
            }

            $gross = (float) $locked->amount;
            $calculated = $this->calculateFromConfig($gross);

            $this->createLedger($locked, $gross, $calculated['fee_rate'], $calculated['fee_amount'], $calculated['net_amount']);

            $locked->platform_fee = $calculated['fee_amount'];
            $locked->net_amount = $calculated['net_amount'];
            $locked->save();
        });
    }

    /**
     * Quote an additive charge from the base transaction amount.
     * Platform fee uses the merchant rule, then the global rule. It is never calculated from the customer total.
     *
     * @return array{
     *     base_amount: float,
     *     platform_fee_rate: float,
     *     platform_fee: float,
     *     convenience_fee: float,
     *     customer_total: float,
     *     merchant_settlement: float
     * }
     */
    public function quote(float $baseAmount, int $merchantId, string $gatewayCode, ?DateTimeInterface $at = null): array
    {
        $baseAmount = round($baseAmount, 2);
        $calculated = $this->platformFeeFor($baseAmount, $merchantId, $gatewayCode, $at ?? now());
        $baseCents = $this->cents($baseAmount);
        $feeCents = $this->cents($calculated['fee_amount']);
        $convenienceCents = $this->cents($this->convenienceFeeAmount());

        return [
            'base_amount' => $this->fromCents($baseCents),
            'platform_fee_rate' => $calculated['fee_rate'],
            'platform_fee' => $this->fromCents($feeCents),
            'convenience_fee' => $this->fromCents($convenienceCents),
            'customer_total' => $this->fromCents($baseCents + $feeCents + $convenienceCents),
            'merchant_settlement' => $this->fromCents($baseCents),
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
     * Calculate the GatewayHub platform fee from the base amount.
     * Formula: fee = round(base × configured percentage / 100, 2).
     *
     * @return array{fee_rate: float, fee_amount: float, net_amount: float}
     */
    public function calculateFromConfig(float $grossAmount): array
    {
        $percentage = PlatformFeeRule::configuredPercentage();

        return $this->feeFromRate($grossAmount, ((float) $percentage) / 100);
    }

    /**
     * Calculate fee_amount and net_amount from gross and rule.
     * Percentage rules store fee_value as a rate, such as 0.0150 for 1.50%.
     *
     * @return array{fee_rate: float, fee_amount: float, net_amount: float}
     */
    public function calculateFromRule(float $grossAmount, PlatformFeeRule $rule): array
    {
        $feeValue = (float) $rule->fee_value;

        if ($rule->fee_type === 'percentage') {
            return $this->feeFromRate($grossAmount, $feeValue);
        }

        $feeCents = $this->cents($feeValue);
        $grossCents = $this->cents($grossAmount);

        return [
            'fee_rate' => 0.0,
            'fee_amount' => $this->fromCents($feeCents),
            'net_amount' => $this->fromCents($grossCents - $feeCents),
        ];
    }

    public function convenienceFeeAmount(): float
    {
        return round(max(0, (float) config('platform.fees.convenience_fee', 20)), 2);
    }

    /**
     * @return array{fee_rate: float, fee_amount: float, net_amount: float}
     */
    private function platformFeeFor(float $baseAmount, int $merchantId, string $gatewayCode, DateTimeInterface $at): array
    {
        $rule = $this->feeRuleResolver->resolve($merchantId, $gatewayCode, $at);

        if ($rule instanceof PlatformFeeRule && $rule->fee_type === 'percentage') {
            return $this->calculateFromRule($baseAmount, $rule);
        }

        return $this->calculateFromConfig($baseAmount);
    }

    /**
     * Legacy net_amount is base minus fee. Additive quotes do not use this net figure.
     *
     * @return array{fee_rate: float, fee_amount: float, net_amount: float}
     */
    private function feeFromRate(float $baseAmount, float $rate): array
    {
        $basisPoints = (int) round($rate * 10000);
        $baseCents = $this->cents($baseAmount);
        $feeCents = (int) round($baseCents * $basisPoints / 10000);

        return [
            'fee_rate' => $basisPoints / 10000,
            'fee_amount' => $this->fromCents($feeCents),
            'net_amount' => $this->fromCents($baseCents - $feeCents),
        ];
    }

    private function createLedgerFromPayment(Payment $payment): void
    {
        $gross = round((float) $payment->amount, 2);
        $fee = round((float) ($payment->platform_fee ?? 0), 2);
        $rate = $payment->platform_fee_rate !== null
            ? round((float) $payment->platform_fee_rate, 4)
            : ($gross > 0 ? round($fee / $gross, 4) : 0.0);
        $net = $payment->net_amount !== null
            ? round((float) $payment->net_amount, 2)
            : round($gross - $fee, 2);

        $this->createLedger($payment, $gross, $rate, $fee, $net);
    }

    private function createLedger(Payment $payment, float $gross, float $rate, float $fee, float $net): void
    {
        PlatformFee::query()->create([
            'payment_id' => $payment->id,
            'merchant_id' => $payment->merchant_id,
            'gateway_code' => $payment->gateway_code,
            'gross_amount' => $gross,
            'fee_rate' => $rate,
            'fee_amount' => $fee,
            'net_amount' => $net,
            'status' => PlatformFeeStatus::Posted,
        ]);
    }

    private function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function fromCents(int $cents): float
    {
        return $cents / 100;
    }
}
