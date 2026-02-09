<?php

namespace App\Models;

use App\Services\Gateways\GatewayCapability;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
     * Whether this payment uses a QR-based gateway.
     */
    public function isQrBased(): bool
    {
        $gateway = $this->gateway ?? $this->gateway()->first();

        return $gateway !== null && $gateway->getCapability() === GatewayCapability::QR;
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
}
