<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurepayBatchSetting extends Model
{
    public const INTERVAL_UNIT_SECONDS = 'seconds';

    public const INTERVAL_UNIT_MINUTES = 'minutes';

    public const INTERVAL_UNIT_DAYS = 'days';

    public const INTERVAL_UNIT_WEEKS = 'weeks';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'batch_interval_minutes',
        'batch_interval_seconds',
        'tax_percentage',
        'tax_absolute_value',
        'last_batch_settled_at',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'batch_interval_minutes' => 'integer',
            'batch_interval_seconds' => 'integer',
            'tax_percentage' => 'decimal:2',
            'tax_absolute_value' => 'decimal:2',
            'last_batch_settled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function intervalUnit(): string
    {
        $seconds = $this->intervalSeconds();
        if ($seconds % 604800 === 0) {
            return self::INTERVAL_UNIT_WEEKS;
        }

        if ($seconds % 86400 === 0) {
            return self::INTERVAL_UNIT_DAYS;
        }

        if ($seconds % 60 === 0) {
            return self::INTERVAL_UNIT_MINUTES;
        }

        return self::INTERVAL_UNIT_SECONDS;
    }

    public function intervalSeconds(): int
    {
        $seconds = (int) ($this->batch_interval_seconds ?? 0);
        if ($seconds > 0) {
            return $seconds;
        }

        return max(1, (int) $this->batch_interval_minutes * 60);
    }

    public function intervalValue(): int
    {
        return match ($this->intervalUnit()) {
            self::INTERVAL_UNIT_WEEKS => (int) ($this->intervalSeconds() / 604800),
            self::INTERVAL_UNIT_DAYS => (int) ($this->intervalSeconds() / 86400),
            self::INTERVAL_UNIT_MINUTES => (int) ($this->intervalSeconds() / 60),
            default => $this->intervalSeconds(),
        };
    }

    public function intervalLabel(): string
    {
        $value = $this->intervalValue();
        $unit = $this->intervalUnit();

        if ($value === 1) {
            return match ($unit) {
                self::INTERVAL_UNIT_WEEKS => '1 week',
                self::INTERVAL_UNIT_DAYS => '1 day',
                self::INTERVAL_UNIT_SECONDS => '1 second',
                default => '1 minute',
            };
        }

        return "{$value} {$unit}";
    }
}
