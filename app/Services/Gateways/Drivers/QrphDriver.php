<?php

namespace App\Services\Gateways\Drivers;

/**
 * Backward-compatible alias for older QR Ph payment records and integrations.
 */
class QrphDriver extends PayMongoDriver
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPayment(array $data): array
    {
        $data['gateway_code'] ??= 'qrph';

        return parent::createPayment($data);
    }
}
