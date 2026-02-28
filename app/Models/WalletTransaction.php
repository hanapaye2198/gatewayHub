<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WalletTransaction extends Model
{
    /** @use HasFactory<\Database\Factories\WalletTransactionFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    public const ENTRY_PAYMENT_RECEIVED_GROSS = 'payment_received_gross';

    public const ENTRY_TRANSFER_TO_SUREPAY_GROSS = 'transfer_to_surepay_gross';

    public const ENTRY_SUREPAY_TAX_COLLECTED = 'surepay_tax_collected';

    public const ENTRY_TUNNEL_NET_AVAILABLE = 'tunnel_net_available';

    public const ENTRY_TUNNEL_BATCH_SETTLEMENT_OUT = 'tunnel_batch_settlement_out';

    public const ENTRY_REAL_WALLET_NET_CREDIT = 'real_wallet_net_credit';

    public const ENTRY_REAL_WALLET_NET_CREDIT_DIRECT = 'real_wallet_net_credit_direct';

    public const ENTRY_TUNNEL_REVERSAL_DEBIT = 'tunnel_reversal_debit';

    public const ENTRY_TUNNEL_FAILURE = 'tunnel_wallet_failure';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'wallet_id',
        'payment_id',
        'direction',
        'entry_type',
        'amount',
        'currency',
        'metadata',
        'is_settled',
        'settled_at',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (WalletTransaction $transaction): void {
            if (empty($transaction->{$transaction->getKeyName()})) {
                $transaction->{$transaction->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'is_settled' => 'boolean',
            'settled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
