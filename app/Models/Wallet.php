<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Wallet extends Model
{
    /** @use HasFactory<\Database\Factories\WalletFactory> */
    use HasFactory;

    public const TYPE_MERCHANT_REAL = 'merchant_real';

    public const TYPE_MERCHANT_CLEARING = 'merchant_clearing';

    public const TYPE_SYSTEM_TAX = 'system_tax';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'merchant_id',
        'wallet_type',
        'currency',
        'balance',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Wallet $wallet): void {
            if (empty($wallet->{$wallet->getKeyName()})) {
                $wallet->{$wallet->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return HasMany<WalletTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
