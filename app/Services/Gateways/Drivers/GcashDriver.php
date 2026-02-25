<?php

namespace App\Services\Gateways\Drivers;

use App\Services\Gateways\Contracts\GatewayInterface;
use Illuminate\Http\Request;

class GcashDriver implements GatewayInterface
{
    public function __construct(
        protected array $config = []
    ) {}

    /**
     * Validation rules for config_json (per merchant).
     *
     * @return array<string, mixed>
     */
    public static function configValidationRules(): array
    {
        return [
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:255'],
            'webhook_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Required config keys for payment creation.
     *
     * @return list<string>
     */
    public static function getRequiredConfigKeys(): array
    {
        return ['client_id', 'client_secret'];
    }

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
