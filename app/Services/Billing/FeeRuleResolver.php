<?php

namespace App\Services\Billing;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\PlatformFeeRule;
use DateTimeInterface;

/**
 * Resolves the applicable platform fee rule by priority.
 * Deterministic: same inputs (merchant_id, gateway_code, payment date) always yield the same rule or null.
 */
class FeeRuleResolver
{
    private const PRIORITY = ['merchant_gateway', 'merchant', 'gateway', 'global'];

    /**
     * Resolve the first matching rule in order: merchant_gateway → merchant → gateway → global.
     * Rule must be active and effective at the given payment date.
     *
     * @return PlatformFeeRule|null Percentage or flat rule, or null if none found
     */
    public function resolve(int $merchantId, string $gatewayCode, DateTimeInterface $paymentDate): ?PlatformFeeRule
    {
        $gateway = Gateway::query()->where('code', $gatewayCode)->first();
        $merchantGateway = $gateway instanceof Gateway
            ? MerchantGateway::query()
                ->where('merchant_id', $merchantId)
                ->where('gateway_id', $gateway->id)
                ->first()
            : null;

        foreach (self::PRIORITY as $scopeType) {
            $scopeId = match ($scopeType) {
                'merchant_gateway' => $merchantGateway?->id,
                'merchant' => $merchantId,
                'gateway' => $gateway?->id,
                'global' => null,
                default => null,
            };

            $rule = $this->queryApplicableRule($paymentDate, $scopeType, $scopeId);
            if ($rule instanceof PlatformFeeRule) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @param  int|string|null  $scopeId
     */
    private function queryApplicableRule(DateTimeInterface $date, string $scopeType, $scopeId): ?PlatformFeeRule
    {
        $query = PlatformFeeRule::query()
            ->where('is_active', true)
            ->where('scope_type', $scopeType)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date): void {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            });

        if ($scopeId === null) {
            $query->whereNull('scope_id');
        } else {
            $query->where('scope_id', $scopeId);
        }

        return $query->first();
    }
}
