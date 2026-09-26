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
        'merchant_id',
        'gateway_code',
        'amount',
        'currency',
        'platform_fee',
        'platform_fee_rate',
        'net_amount',
        'convenience_fee',
        'customer_total',
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

        static::updating(function (Payment $model): void {
            if ($model->getOriginal('customer_total') === null) {
                return;
            }

            foreach (['amount', 'platform_fee', 'platform_fee_rate', 'convenience_fee', 'customer_total', 'net_amount', 'currency'] as $key) {
                if ($model->isDirty($key)) {
                    $model->setAttribute($key, $model->getOriginal($key));
                }
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
            'platform_fee_rate' => 'decimal:4',
            'net_amount' => 'decimal:2',
            'convenience_fee' => 'decimal:2',
            'customer_total' => 'decimal:2',
            'raw_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Return only GatewayHub-owned financial fields for API and webhook payloads.
     * Provider response fields such as conversion fees are intentionally excluded.
     *
     * @return array{
     *     gross_amount: float,
     *     gatewayhub_platform_fee_percent: float,
     *     gatewayhub_platform_fee: float|null,
     *     gatewayhub_net_amount: float|null,
     *     base_amount: float,
     *     platform_fee_rate: float,
     *     platform_fee: float|null,
     *     convenience_fee: float|null,
     *     customer_total: float|null
     * }
     */
    public function gatewayHubFeeData(): array
    {
        $ledger = $this->relationLoaded('platformFee') ? $this->getRelation('platformFee') : null;
        $hasLedger = $ledger instanceof PlatformFee;
        $percent = $this->resolvedPlatformFeePercent($hasLedger ? $ledger : null);
        $platformFee = $hasLedger
            ? (float) $ledger->fee_amount
            : ($this->platform_fee !== null ? (float) $this->platform_fee : null);
        $netAmount = $hasLedger
            ? (float) $ledger->net_amount
            : ($this->net_amount !== null ? (float) $this->net_amount : null);

        return [
            'gross_amount' => (float) $this->amount,
            'gatewayhub_platform_fee_percent' => $percent,
            'gatewayhub_platform_fee' => $platformFee,
            'gatewayhub_net_amount' => $netAmount,
            'base_amount' => (float) $this->amount,
            'platform_fee_rate' => $percent,
            'platform_fee' => $platformFee,
            'convenience_fee' => $this->convenience_fee !== null ? (float) $this->convenience_fee : null,
            'customer_total' => $this->customer_total !== null ? (float) $this->customer_total : null,
        ];
    }

    /**
     * Percentage points snapshotted for this payment, such as 3.00 for a 3% fee.
     */
    public function platformFeePercent(): ?float
    {
        if ($this->platform_fee_rate === null) {
            return null;
        }

        $basisPoints = (int) round(((float) $this->platform_fee_rate) * 10000);

        return $basisPoints / 100;
    }

    public function usesAdditivePricing(): bool
    {
        return $this->customer_total !== null;
    }

    private function resolvedPlatformFeePercent(?PlatformFee $ledger): float
    {
        if ($ledger instanceof PlatformFee) {
            $basisPoints = (int) round(((float) $ledger->fee_rate) * 10000);

            return $basisPoints / 100;
        }

        $snapshotted = $this->platformFeePercent();
        if ($snapshotted !== null) {
            return $snapshotted;
        }

        return PlatformFeeRule::configuredPercentage();
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
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

        return self::extractQrData($raw);
    }

    /**
     * Find a scannable QR payload anywhere in a provider response.
     * Coins has returned the code as qrCode, qrcode, and qr_string.
     *
     * @param  array<string, mixed>  $payload
     * @return array{type: string, value: string}|null
     */
    public static function extractQrData(array $payload): ?array
    {
        return self::findQrData($payload, 0);
    }

    /**
     * Keep a provider payload merge from dropping a QR string already stored on the payment.
     *
     * @param  array<string, mixed>  $incoming
     */
    public function mergeProviderResponse(array $incoming): void
    {
        $previous = $this->getQrData();
        $existing = is_array($this->raw_response) ? $this->raw_response : [];
        $merged = array_merge($existing, $incoming);

        if (is_array($existing['data'] ?? null) && is_array($incoming['data'] ?? null)) {
            $merged['data'] = array_merge($existing['data'], $incoming['data']);
        }

        $this->raw_response = $merged;

        if ($this->getQrData() !== null || $previous === null || $previous['type'] !== 'string') {
            return;
        }

        $this->rememberQrString($previous['value']);
    }

    public function rememberQrString(string $qrString): void
    {
        if ($qrString === '') {
            return;
        }

        $raw = is_array($this->raw_response) ? $this->raw_response : [];
        $raw['qr_string'] = $qrString;
        $this->raw_response = $raw;
    }

    /**
     * @param  array<mixed>  $payload
     * @return array{type: string, value: string}|null
     */
    private static function findQrData(array $payload, int $depth): ?array
    {
        if ($depth > 6) {
            return null;
        }

        foreach ($payload as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);
            if ($value === '' || ! is_string($key)) {
                continue;
            }

            $normalized = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key));
            if (str_contains($normalized, 'status') || str_contains($normalized, 'merchant')) {
                continue;
            }

            if (in_array($normalized, ['qrimage', 'qrimageurl', 'imageurl'], true)) {
                return ['type' => 'image', 'value' => $value];
            }

            if (in_array($normalized, ['qrcode', 'qrstring', 'qrdata'], true) || str_starts_with($value, '000201')) {
                return ['type' => 'string', 'value' => $value];
            }
        }

        foreach ($payload as $value) {
            if (! is_array($value)) {
                continue;
            }

            $nested = self::findQrData($value, $depth + 1);
            if ($nested !== null) {
                return $nested;
            }
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
