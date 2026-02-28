<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantWalletSetting extends Model
{
    /** @use HasFactory<\Database\Factories\MerchantWalletSettingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'tunnel_wallet_enabled',
        'auto_settle_to_real_wallet',
        'default_currency',
        'tunnel_client_id',
        'tunnel_client_secret',
        'tunnel_webhook_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tunnel_wallet_enabled' => 'boolean',
            'auto_settle_to_real_wallet' => 'boolean',
            'tunnel_client_secret' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
