<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Business entity (merchant client). API keys for integrations live here, not on {@see User}.
 */
class Merchant extends Model
{
    /** @use HasFactory<\Database\Factories\MerchantFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'api_key',
        'api_key_hash',
        'api_key_last_four',
        'api_key_generated_at',
        'api_secret',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'api_key_hash',
        'api_secret',
    ];

    protected function casts(): array
    {
        return [
            'api_key_generated_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Link a new merchant row to an existing user account (shared primary key id).
     */
    public static function provisionForUser(User $user): self
    {
        $merchant = new self([
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active ?? true,
        ]);
        $merchant->id = $user->id;
        $merchant->save();

        $user->forceFill(['merchant_id' => $merchant->id])->save();

        return $merchant;
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return BelongsToMany<Gateway, $this>
     */
    public function gateways(): BelongsToMany
    {
        return $this->belongsToMany(Gateway::class, 'merchant_gateways')
            ->withPivot(['is_enabled', 'config_json', 'last_tested_at', 'last_test_status'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<MerchantGateway, $this>
     */
    public function merchantGateways(): HasMany
    {
        return $this->hasMany(MerchantGateway::class);
    }

    /**
     * @return HasMany<Wallet, $this>
     */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    /**
     * @return HasMany<MerchantWalletSetting, $this>
     */
    public function merchantWalletSettings(): HasMany
    {
        return $this->hasMany(MerchantWalletSetting::class);
    }

    /**
     * @return HasOne<MerchantWalletSetting, $this>
     */
    public function merchantWalletSetting(): HasOne
    {
        return $this->hasOne(MerchantWalletSetting::class);
    }

    /**
     * Regenerate API key. Returns plaintext once; store only on {@see Merchant}.
     */
    public function regenerateApiKey(): string
    {
        $newKey = Str::random(64);
        $this->forceFill([
            'api_key' => null,
            'api_key_hash' => hash('sha256', $newKey),
            'api_key_last_four' => substr($newKey, -4),
            'api_key_generated_at' => now(),
        ])->save();

        return $newKey;
    }

    public function setApiKeyAttribute(?string $value): void
    {
        $apiKey = is_string($value) ? trim($value) : '';

        if ($apiKey === '') {
            $this->attributes['api_key'] = null;
            $this->attributes['api_key_hash'] = null;
            $this->attributes['api_key_last_four'] = null;

            return;
        }

        $this->attributes['api_key'] = null;
        $this->attributes['api_key_hash'] = hash('sha256', $apiKey);
        $this->attributes['api_key_last_four'] = substr($apiKey, -4);
    }

    public function hasApiKey(): bool
    {
        return is_string($this->api_key_hash) && $this->api_key_hash !== '';
    }

    public function getMaskedApiKeyAttribute(): ?string
    {
        $lastFour = $this->api_key_last_four;
        if (is_string($lastFour) && $lastFour !== '') {
            return '****'.$lastFour;
        }

        return null;
    }
}
