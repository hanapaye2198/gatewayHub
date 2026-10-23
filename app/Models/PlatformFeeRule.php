<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable platform fee rule with scope and effective period.
 * scope_type: global | merchant | gateway | merchant_gateway
 * fee_type: percentage | flat
 */
class PlatformFeeRule extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'scope_type',
        'scope_id',
        'fee_type',
        'fee_value',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fee_value' => 'decimal:4',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    /**
     * Active platform-wide percentage rule. Merchant-specific rules are not used here.
     */
    public static function activeGlobalPercentageRule(): ?self
    {
        return static::query()
            ->where('scope_type', 'global')
            ->whereNull('scope_id')
            ->where('fee_type', 'percentage')
            ->where('is_active', true)
            ->where('effective_from', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Active merchant percentage override, when one is in effect.
     */
    public static function activeMerchantPercentageRule(int $merchantId): ?self
    {
        return static::query()
            ->where('scope_type', 'merchant')
            ->where('scope_id', $merchantId)
            ->where('fee_type', 'percentage')
            ->where('is_active', true)
            ->where('effective_from', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Percentage points used by {@see \App\Services\Billing\PlatformFeeService::calculateFromConfig()}.
     * The global rule is authoritative when present; config is only the fallback.
     */
    public static function configuredPercentage(): float
    {
        if (! Schema::hasTable('platform_fee_rules')) {
            return (float) config('platform.fees.percentage', 1.5);
        }

        $rule = static::activeGlobalPercentageRule();

        if (! $rule instanceof self) {
            return (float) config('platform.fees.percentage', 1.5);
        }

        return (float) $rule->percentage();
    }

    /**
     * Display percentage with two decimal places. 0.0150 is 1.50.
     */
    public function percentage(): string
    {
        $basisPoints = (int) round(((float) $this->fee_value) * 10000);

        return number_format($basisPoints / 100, 2, '.', '');
    }

    /**
     * Store a percentage such as 1.50 as the rate 0.0150.
     */
    public static function rateForPercentage(float|string $percentage): string
    {
        $basisPoints = (int) round(((float) $percentage) * 100);

        return number_format($basisPoints / 10000, 4, '.', '');
    }
}
