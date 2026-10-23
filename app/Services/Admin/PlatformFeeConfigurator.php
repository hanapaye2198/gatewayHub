<?php

namespace App\Services\Admin;

use App\Models\Merchant;
use App\Models\PlatformAuditLog;
use App\Models\PlatformFeeRule;
use Illuminate\Support\Facades\DB;

/**
 * Updates the active global platform fee percentage.
 * Historical platform fee rows are not rewritten.
 */
final class PlatformFeeConfigurator
{
    public function __construct(private PlatformAuditService $audit) {}

    public function updatePercentage(float|string $percentage): PlatformFeeRule
    {
        return DB::transaction(function () use ($percentage): PlatformFeeRule {
            $next = number_format((float) $percentage, 2, '.', '');
            $rule = PlatformFeeRule::activeGlobalPercentageRule();
            $previous = $rule instanceof PlatformFeeRule
                ? $rule->percentage()
                : number_format(PlatformFeeRule::configuredPercentage(), 2, '.', '');

            if (! $rule instanceof PlatformFeeRule) {
                $rule = PlatformFeeRule::query()->create([
                    'scope_type' => 'global',
                    'scope_id' => null,
                    'fee_type' => 'percentage',
                    'fee_value' => PlatformFeeRule::rateForPercentage($next),
                    'is_active' => true,
                    'effective_from' => now(),
                    'effective_to' => null,
                ]);
            } elseif ($previous !== $next) {
                $rule->fee_value = PlatformFeeRule::rateForPercentage($next);
                $rule->save();
            }

            if ($previous === $next) {
                return $rule;
            }

            $this->audit->record(
                PlatformAuditLog::ACTION_PLATFORM_FEE_UPDATED,
                null,
                $rule,
                ['percentage' => $previous],
                ['percentage' => $next],
                'Updated the platform fee from '.$previous.'% to '.$next.'%.',
            );

            return $rule;
        });
    }

    /**
     * Set or clear one merchant's platform-fee override.
     * An empty percentage removes the override so later payments use the global rate.
     * Existing payment snapshots are not rewritten.
     */
    public function updateMerchantPercentage(Merchant $merchant, float|string|null $percentage): ?PlatformFeeRule
    {
        return DB::transaction(function () use ($merchant, $percentage): ?PlatformFeeRule {
            $cleared = $percentage === null || $percentage === '';
            $next = $cleared ? null : number_format((float) $percentage, 2, '.', '');
            $rule = PlatformFeeRule::activeMerchantPercentageRule((int) $merchant->id);
            $previous = $rule instanceof PlatformFeeRule ? $rule->percentage() : null;

            if ($cleared) {
                if (! $rule instanceof PlatformFeeRule) {
                    return null;
                }

                $rule->is_active = false;
                $rule->save();
                $this->audit->record(
                    PlatformAuditLog::ACTION_MERCHANT_PLATFORM_FEE_UPDATED,
                    $merchant,
                    $rule,
                    ['percentage' => $previous],
                    ['percentage' => null],
                    'Cleared the platform fee override for '.$merchant->name.'.',
                );

                return $rule;
            }

            if (! $rule instanceof PlatformFeeRule) {
                $rule = PlatformFeeRule::query()->create([
                    'scope_type' => 'merchant',
                    'scope_id' => $merchant->id,
                    'fee_type' => 'percentage',
                    'fee_value' => PlatformFeeRule::rateForPercentage($next),
                    'is_active' => true,
                    'effective_from' => now(),
                    'effective_to' => null,
                ]);
            } elseif ($previous !== $next) {
                $rule->fee_value = PlatformFeeRule::rateForPercentage($next);
                $rule->save();
            }

            PlatformFeeRule::query()
                ->where('scope_type', 'merchant')
                ->where('scope_id', $merchant->id)
                ->where('fee_type', 'percentage')
                ->where('id', '!=', $rule->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            if ($previous === $next) {
                return $rule;
            }

            $this->audit->record(
                PlatformAuditLog::ACTION_MERCHANT_PLATFORM_FEE_UPDATED,
                $merchant,
                $rule,
                ['percentage' => $previous],
                ['percentage' => $next],
                'Set the platform fee override for '.$merchant->name.' to '.$next.'%.',
            );

            return $rule;
        });
    }

    /**
     * Set or clear one merchant's convenience fee. Zero waives it; empty falls back to the platform default.
     * Existing payment snapshots are not rewritten.
     */
    public function updateMerchantConvenienceFee(Merchant $merchant, float|string|null $amount): void
    {
        DB::transaction(function () use ($merchant, $amount): void {
            $next = $amount === null || $amount === '' ? null : number_format((float) $amount, 2, '.', '');
            $current = $merchant->convenience_fee_override;
            $previous = $current === null ? null : number_format((float) $current, 2, '.', '');

            if ($previous === $next) {
                return;
            }

            $merchant->forceFill(['convenience_fee_override' => $next])->save();

            $description = match (true) {
                $next === null => 'Cleared the convenience fee override for '.$merchant->name.'.',
                (float) $next === 0.0 => 'Waived the convenience fee for '.$merchant->name.'.',
                default => 'Set the convenience fee for '.$merchant->name.' to '.$next.'.',
            };

            $this->audit->record(
                PlatformAuditLog::ACTION_MERCHANT_CONVENIENCE_FEE_UPDATED,
                $merchant,
                $merchant,
                ['convenience_fee' => $previous],
                ['convenience_fee' => $next],
                $description,
            );
        });
    }
}
