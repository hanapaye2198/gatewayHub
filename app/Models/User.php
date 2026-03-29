<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MERCHANT_USER = 'merchant_user';

    public const ROLE_STAFF = 'staff';

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
        'merchant_id',
        'onboarding_gateways_at',
        'onboarding_completed_at',
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
            'password' => 'hashed',
            'is_active' => 'boolean',
            'onboarding_gateways_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    public function isMerchantUser(): bool
    {
        return $this->role === self::ROLE_MERCHANT_USER;
    }

    /**
     * Web URL for the next merchant onboarding step, or the dashboard when onboarding is finished.
     */
    public function merchantOnboardingOrDashboardUrl(): string
    {
        if (! $this->isMerchantUser()) {
            return url('/dashboard');
        }

        if ($this->merchant_id === null) {
            return route('onboarding.business', absolute: false);
        }

        if ($this->onboarding_completed_at === null) {
            if ($this->onboarding_gateways_at === null) {
                return route('onboarding.gateways', absolute: false);
            }

            return route('onboarding.api-keys', absolute: false);
        }

        return url('/dashboard');
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Payments belonging to this user's merchant (same merchant_id on users and payments).
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'merchant_id', 'merchant_id');
    }

    /**
     * @return BelongsToMany<Gateway, $this>
     */
    public function gateways(): BelongsToMany
    {
        return $this->belongsToMany(Gateway::class, 'merchant_gateways', 'merchant_id', 'gateway_id', 'merchant_id', 'id')
            ->withPivot(['is_enabled', 'config_json', 'last_tested_at', 'last_test_status'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<MerchantGateway, $this>
     */
    public function merchantGateways(): HasMany
    {
        return $this->hasMany(MerchantGateway::class, 'merchant_id', 'merchant_id');
    }

    /**
     * @return HasMany<Wallet, $this>
     */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class, 'merchant_id', 'merchant_id');
    }

    /**
     * @return HasMany<MerchantWalletSetting, $this>
     */
    public function merchantWalletSettings(): HasMany
    {
        return $this->hasMany(MerchantWalletSetting::class, 'merchant_id', 'merchant_id');
    }

    /**
     * @return HasOne<MerchantWalletSetting, $this>
     */
    public function merchantWalletSetting(): HasOne
    {
        return $this->hasOne(MerchantWalletSetting::class, 'merchant_id', 'merchant_id');
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
     * Regenerate API key on the linked {@see Merchant} (not on the user row).
     */
    public function regenerateApiKey(): string
    {
        $m = $this->merchant;
        if ($m === null) {
            throw new \RuntimeException('User has no merchant; cannot regenerate API key.');
        }

        return $m->regenerateApiKey();
    }

    public function hasApiKey(): bool
    {
        return $this->merchant?->hasApiKey() ?? false;
    }

    public function getMaskedApiKeyAttribute(): ?string
    {
        return $this->merchant?->masked_api_key;
    }

    public function getApiKeyGeneratedAtAttribute(): mixed
    {
        return $this->merchant?->api_key_generated_at ?? $this->attributes['api_key_generated_at'] ?? null;
    }
}
