<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Business entity (merchant client). API keys for integrations live here, not on {@see User}.
 */
class Merchant extends Model
{
    public const DEFAULT_THEME_COLOR = '#1D4ED8';

    public const DEFAULT_DISPLAY_NAME = 'GatewayHub Merchant';

    public const QR_MERCHANT_NAME_MAX_LENGTH = 64;

    /** @use HasFactory<\Database\Factories\MerchantFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'logo_path',
        'theme_color',
        'qr_display_name',
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
     * Customer-facing business name for QR branding (stored in the `name` column).
     *
     * @return Attribute<string|null, never>
     */
    protected function businessName(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $name = trim((string) ($this->attributes['name'] ?? ''));

                return $name === '' ? null : $name;
            }
        );
    }

    /**
     * Human-facing name for UI, QR labels, and checkout (not necessarily the Coins API string length).
     */
    public function getDisplayName(): string
    {
        $qrOverride = $this->attributes['qr_display_name'] ?? null;
        if (is_string($qrOverride)) {
            $trimmed = trim($qrOverride);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        $business = $this->business_name;
        if ($business !== null) {
            return $business;
        }

        return self::DEFAULT_DISPLAY_NAME;
    }

    /**
     * Normalized name for Coins `qrCodeMerchantName` (trim, max 64 chars, safe fallback).
     */
    public function getQrMerchantName(): string
    {
        $name = trim($this->getDisplayName());
        if ($name === '') {
            $name = self::DEFAULT_DISPLAY_NAME;
        }

        return mb_substr($name, 0, self::QR_MERCHANT_NAME_MAX_LENGTH);
    }

    public function getLogoUrl(): string
    {
        $path = $this->attributes['logo_path'] ?? null;
        if (is_string($path) && trim($path) !== '' && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return asset('images/default-logo.svg');
    }

    public function getThemeColor(): string
    {
        $raw = isset($this->attributes['theme_color']) ? trim((string) $this->attributes['theme_color']) : '';
        if ($raw !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $raw) === 1) {
            return $raw;
        }

        return self::DEFAULT_THEME_COLOR;
    }

    /**
     * Branding payload for API responses and shared views.
     *
     * @return array{name: string, logo: string, theme_color: string}
     */
    public function brandingForApi(): array
    {
        return [
            'name' => $this->getDisplayName(),
            'logo' => $this->getLogoUrl(),
            'theme_color' => $this->getThemeColor(),
        ];
    }

    /**
     * @deprecated Use {@see getQrMerchantName()} instead.
     */
    public function qrCodeMerchantDisplayName(): string
    {
        return $this->getQrMerchantName();
    }

    /**
     * @param  string|null  $businessName  Trimmed merchant business name, or null when empty
     */
    public static function normalizeQrCodeMerchantName(?string $businessName): string
    {
        if (is_string($businessName)) {
            $trimmed = trim($businessName);
            if ($trimmed !== '') {
                return mb_substr($trimmed, 0, self::QR_MERCHANT_NAME_MAX_LENGTH);
            }
        }

        return self::DEFAULT_DISPLAY_NAME;
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
