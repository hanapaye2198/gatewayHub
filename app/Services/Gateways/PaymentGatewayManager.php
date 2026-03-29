<?php

namespace App\Services\Gateways;

use App\Models\Gateway;
use App\Models\Merchant;
use App\Services\Gateways\Contracts\GatewayInterface;
use App\Services\Gateways\Exceptions\GatewayException;
use Illuminate\Contracts\Container\Container;

class PaymentGatewayManager
{
    /**
     * Payment options that are orchestrated through Coins Dynamic QR.
     *
     * @var list<string>
     */
    private const COINS_ORCHESTRATED_GATEWAYS = ['coins', 'gcash', 'maya', 'paypal', 'qrph', 'payqrph'];

    public function __construct(
        protected Container $container,
        protected Gateway $gateway,
        protected PlatformGatewayConfigService $platformGatewayConfigService
    ) {}

    /**
     * Resolve driver for a merchant and gateway. Verifies global and merchant-level
     * enablement, loads platform-level gateway credentials, and returns configured driver.
     *
     * @throws GatewayException
     */
    public function resolve(Merchant $merchant, string $gatewayCode): GatewayInterface
    {
        $gateway = $this->gateway->newQuery()->where('code', $gatewayCode)->first();

        if ($gateway === null) {
            throw new GatewayException("Gateway not found: {$gatewayCode}.");
        }

        if (! $gateway->is_global_enabled) {
            throw new GatewayException('Gateway is not available.');
        }

        $merchantGateway = $gateway->merchantGateways()
            ->where('merchant_id', $merchant->id)
            ->where('is_enabled', true)
            ->first();

        if ($merchantGateway === null) {
            throw new GatewayException('Gateway is not enabled for this merchant.');
        }

        $processingGatewayCode = $this->processingGatewayCode($gatewayCode);
        $processingGateway = $this->gateway->newQuery()->where('code', $processingGatewayCode)->first();
        if (! $processingGateway instanceof Gateway) {
            throw new GatewayException("Processing gateway not found: {$processingGatewayCode}.");
        }

        if (! $processingGateway->is_global_enabled) {
            throw new GatewayException("Processing gateway is not enabled: {$processingGatewayCode}.");
        }

        $config = $this->platformGatewayConfigService->forGatewayCode($processingGatewayCode);
        $missing = $this->missingRequiredConfig($processingGateway->driver_class, $config);
        if ($missing !== null) {
            throw new GatewayException(sprintf(
                'Gateway "%s" is missing required platform credentials: %s. Configure them in SurePay admin settings.',
                $processingGateway->name,
                implode(', ', $missing)
            ));
        }

        return $this->getDriver($processingGatewayCode, $config);
    }

    /**
     * Resolve and return the driver instance for the given gateway code and config.
     * Used internally by resolve(); controllers must not call this directly.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws GatewayException
     */
    public function getDriver(string $code, array $config = []): GatewayInterface
    {
        $gateway = $this->gateway->newQuery()->where('code', $code)->first();

        if ($gateway === null) {
            throw new GatewayException("Gateway not found: {$code}.");
        }

        if (! $gateway->is_global_enabled) {
            throw new GatewayException("Gateway is not enabled: {$code}.");
        }

        $driver = $this->container->make($gateway->driver_class, [
            'config' => $config,
        ]);

        if (! $driver instanceof GatewayInterface) {
            throw new GatewayException("Gateway driver does not implement GatewayInterface: {$code}.");
        }

        return $driver;
    }

    /**
     * Get capability (QR / REDIRECT / API_ONLY) for a gateway by code.
     *
     * @throws GatewayException
     */
    public function getCapability(string $code): GatewayCapability
    {
        $gateway = $this->gateway->newQuery()->where('code', $code)->first();

        if ($gateway === null) {
            throw new GatewayException("Gateway not found: {$code}.");
        }

        return $gateway->getCapability();
    }

    /**
     * Return list of required config keys that are missing or empty, or null if all present.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>|null
     */
    private function missingRequiredConfig(string $driverClass, array $config): ?array
    {
        if (! method_exists($driverClass, 'getRequiredConfigKeys')) {
            return null;
        }

        $required = $driverClass::getRequiredConfigKeys();
        $missing = [];
        foreach ($required as $key) {
            $value = $config[$key] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $missing[] = $key;
            }
        }

        return $missing === [] ? null : $missing;
    }

    private function processingGatewayCode(string $selectedGatewayCode): string
    {
        if (in_array($selectedGatewayCode, self::COINS_ORCHESTRATED_GATEWAYS, true)) {
            return 'coins';
        }

        return $selectedGatewayCode;
    }
}
