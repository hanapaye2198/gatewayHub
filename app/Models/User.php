<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'google_id',
        'api_key',
        'api_key_hash',
        'api_key_last_four',
        'api_key_generated_at',
        'api_secret',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'api_key_hash',
        'api_secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'api_key_generated_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
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
            ->withPivot(['is_enabled', 'config_json'])
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
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Regenerate API key. Old key is invalidated immediately.
     * Returns the new key once; caller must not log or persist it.
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

    /**
     * Store API tokens as hash + last-four only (never plaintext).
     */
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

    /**
     * Mask API key for display (last 4 characters visible).
     */
    public function getMaskedApiKeyAttribute(): ?string
    {
        $lastFour = $this->api_key_last_four;
        if (is_string($lastFour) && $lastFour !== '') {
            return '****'.$lastFour;
        }

        return null;
    }
}
