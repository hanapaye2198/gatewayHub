<?php

namespace App\Services\Gateways;

use App\Models\Gateway;
use App\Services\Gateways\Contracts\GatewayInterface;
use App\Services\Gateways\Exceptions\GatewayException;
use Illuminate\Contracts\Container\Container;

class PaymentGatewayManager
{
    public function __construct(
        protected Container $container,
        protected Gateway $gateway
    ) {}

    /**
     * Resolve and return the driver instance for the given gateway code.
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
}
