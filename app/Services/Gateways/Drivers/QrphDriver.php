<?php

namespace App\Services\Gateways\Drivers;

use App\Services\Gateways\Contracts\GatewayInterface;
use Illuminate\Http\Request;

class QrphDriver implements GatewayInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected array $config = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function configValidationRules(): array
    {
        return CoinsDriver::configValidationRules();
    }

    /**
     * @return list<string>
     */
    public static function getRequiredConfigKeys(): array
    {
        return CoinsDriver::getRequiredConfigKeys();
    }

    public function createPayment(array $data): array
    {
        $coinsDriver = new CoinsDriver($this->config);

        return $coinsDriver->createPayment($data);
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function getPaymentStatus(string $reference): array
    {
        $coinsDriver = new CoinsDriver($this->config);

        return $coinsDriver->getPaymentStatus($reference);
    }
}
