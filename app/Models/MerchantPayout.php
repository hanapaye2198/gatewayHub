<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MerchantPayout extends Model
{
    /** @use HasFactory<\Database\Factories\MerchantPayoutFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'merchant_id',
        'provider',
        'amount',
        'currency',
        'purpose',
        'description',
        'destination_account',
        'status',
        'reference_number',
        'idempotency_key',
        'request_fingerprint',
        'paymongo_batch_transfer_id',
        'paymongo_transfer_id',
        'provider_reference_number',
        'provider_fee',
        'failure_code',
        'failure_message',
        'submission_started_at',
        'completed_at',
        'funds_released_at',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (MerchantPayout $payout): void {
            if (empty($payout->{$payout->getKeyName()})) {
                $payout->{$payout->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'destination_account' => 'encrypted:array',
            'provider_fee' => 'decimal:2',
            'submission_started_at' => 'datetime',
            'completed_at' => 'datetime',
            'funds_released_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Merchant, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
