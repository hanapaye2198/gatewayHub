<?php

namespace App\Services\Gateways\Drivers;

use App\Services\Gateways\Contracts\GatewayInterface;
use Illuminate\Http\Request;

class PaypalDriver implements GatewayInterface
{
    public function __construct(
        protected array $config = []
    ) {}

    public function createPayment(array $data): array
    {
        return [];
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function getPaymentStatus(string $reference): array
    {
        return [];
    }
}
