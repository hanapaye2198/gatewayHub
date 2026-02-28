<?php

namespace App\Models;

use App\Services\Gateways\GatewayCapability;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Payment model. Uses UUID as primary key; UUID is auto-generated in boot() when creating.
 */
class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'gateway_code',
        'amount',
        'currency',
        'platform_fee',
        'net_amount',
        'reference_id',
        'provider_reference',
        'status',
        'raw_response',
        'paid_at',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Payment $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'raw_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Gateway, $this>
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class, 'gateway_code', 'code');
    }

    /**
     * @return HasOne<PlatformFee, $this>
     */
    public function platformFee(): HasOne
    {
        return $this->hasOne(PlatformFee::class);
    }

    /**
     * @return HasMany<WebhookEvent, $this>
     */
    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class)->orderBy('received_at');
    }

    /**
     * @return HasMany<WalletTransaction, $this>
     */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Whether this payment uses a QR-based gateway.
     */
    public function isQrBased(): bool
    {
        $gateway = $this->gateway ?? $this->gateway()->first();

        return $gateway !== null && $gateway->getCapability() === GatewayCapability::QR;
    }

    /**
     * Get expiration datetime for QR-based payments. Computed from raw_response or created_at.
     */
    public function getExpiresAt(): ?\Carbon\CarbonInterface
    {
        $raw = $this->raw_response;
        if (is_array($raw)) {
            $expiresAt = $raw['expires_at'] ?? $raw['data']['expires_at'] ?? null;
            if (is_string($expiresAt)) {
                try {
                    return \Illuminate\Support\Carbon::parse($expiresAt);
                } catch (\Throwable) {
                    //
                }
            }
        }

        if ($this->isQrBased()) {
            return $this->created_at->copy()->addSeconds(1800);
        }

        return null;
    }

    /**
     * Extract QR payload (string or image URL) from raw_response.
     * Returns ['type' => 'string'|'image', 'value' => string] or null.
     *
     * @return array{type: string, value: string}|null
     */
    public function getQrData(): ?array
    {
        $raw = $this->raw_response;
        if (! is_array($raw)) {
            return null;
        }

        $data = $raw['data'] ?? $raw;
        if (! is_array($data)) {
            return null;
        }

        $qrImage = $data['qrImage'] ?? $data['qr_image'] ?? $data['qrImageUrl'] ?? $data['imageUrl'] ?? null;
        if (is_string($qrImage) && $qrImage !== '') {
            return ['type' => 'image', 'value' => $qrImage];
        }

        $qrString = $data['qrCode'] ?? $data['qr_string'] ?? $data['qrString'] ?? $data['payload'] ?? null;
        if (is_string($qrString) && $qrString !== '') {
            return ['type' => 'string', 'value' => $qrString];
        }

        return null;
    }

    public function getRedirectUrl(): ?string
    {
        $raw = $this->raw_response;
        if (! is_array($raw)) {
            return null;
        }

        $redirectUrl = $raw['redirect_url'] ?? $raw['checkout_url'] ?? $raw['checkoutUrl'] ?? $raw['url'] ?? null;

        return is_string($redirectUrl) && $redirectUrl !== '' ? $redirectUrl : null;
    }
}
