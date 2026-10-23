<?php

namespace App\Models;

use App\Casts\PartiallyEncryptedJsonCast;
use App\Services\Gateways\GatewayCapability;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gateway extends Model
{
    /**
     * @var list<string>
     */
    private const PROVIDER_ONLY_CODES = ['paymongo'];

    /**
     * @var list<string>
     */
    private const RETIRED_CODES = ['coins', 'paypal'];

    /** @use HasFactory<\Database\Factories\GatewayFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'driver_class',
        'config_json',
        'is_global_enabled',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_global_enabled' => 'boolean',
            'config_json' => PartiallyEncryptedJsonCast::class,
        ];
    }

    /**
     * @return HasMany<MerchantGateway, $this>
     */
    public function merchantGateways(): HasMany
    {
        return $this->hasMany(MerchantGateway::class);
    }

    /**
     * Gateway capability (QR / REDIRECT / API_ONLY). Resolved from config by driver_class.
     */
    public function getCapability(): GatewayCapability
    {
        $capabilityByCode = config('gateways.capabilities_by_code', []);
        $key = $capabilityByCode[$this->code] ?? config('gateways.capabilities', [])[$this->driver_class] ?? 'api_only';

        return GatewayCapability::tryFrom($key) ?? GatewayCapability::API_ONLY;
    }

    public function isProviderOnly(): bool
    {
        return in_array($this->code, self::PROVIDER_ONLY_CODES, true);
    }

    public function isRetired(): bool
    {
        return in_array($this->code, self::RETIRED_CODES, true);
    }
}
