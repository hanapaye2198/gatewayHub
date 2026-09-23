<?php

namespace App\Services\Admin;

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
}
