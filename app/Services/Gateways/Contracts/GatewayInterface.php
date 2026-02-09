<?php

namespace App\Services\Gateways\Contracts;

use Illuminate\Http\Request;

interface GatewayInterface
{
    public function createPayment(array $data): array;

    public function verifyWebhook(Request $request): bool;

    public function getPaymentStatus(string $reference): array;
}
