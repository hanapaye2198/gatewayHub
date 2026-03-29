<?php

namespace App\Models;

use App\Enums\PlatformFeeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Platform fee ledger record. One per payment (unique payment_id).
 * Financial fields are immutable; only status may change (posted → reversed).
 */
class PlatformFee extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'payment_id',
        'merchant_id',
        'gateway_code',
        'gross_amount',
        'fee_rate',
        'fee_amount',
        'net_amount',
        'status',
        'reversal_reason',
        'reversed_at',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (PlatformFee $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });

        static::saving(function (PlatformFee $model): void {
            if (! $model->exists) {
                return;
            }

            foreach (['gross_amount', 'fee_rate', 'fee_amount', 'net_amount'] as $key) {
                if ($model->isDirty($key)) {
                    $model->setAttribute($key, $model->getOriginal($key));
                }
            }

            if ($model->isDirty('status')) {
                $original = $model->getOriginal('status');
                $new = $model->status instanceof PlatformFeeStatus
                    ? $model->status
                    : PlatformFeeStatus::tryFrom((string) $model->status);
                $originalEnum = $original instanceof PlatformFeeStatus
                    ? $original
                    : PlatformFeeStatus::tryFrom((string) $original);
                if ($originalEnum !== PlatformFeeStatus::Posted || $new !== PlatformFeeStatus::Reversed) {
                    $model->setAttribute('status', $original);
                }
            }
        });
    }

    /**
     * @return array<string, string|\BackedEnum>
     */
    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'fee_rate' => 'decimal:4',
            'fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'status' => PlatformFeeStatus::class,
            'reversed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }
}
