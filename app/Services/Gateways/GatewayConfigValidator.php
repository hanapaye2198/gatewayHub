<?php

namespace App\Services\Gateways;

use App\Models\Gateway;
use Illuminate\Support\Facades\Validator;

class GatewayConfigValidator
{
    /**
     * Get validation rules for a gateway's config_json from its driver.
     *
     * @return array<string, mixed>
     */
    public static function rulesForGateway(Gateway $gateway): array
    {
        $driverClass = $gateway->driver_class;
        if (! is_string($driverClass) || ! method_exists($driverClass, 'configValidationRules')) {
            return [];
        }

        return $driverClass::configValidationRules();
    }

    /**
     * Validate config array for a gateway. Returns validator instance.
     *
     * @param  array<string, mixed>  $config
     */
    public static function validate(Gateway $gateway, array $config): \Illuminate\Validation\Validator
    {
        return Validator::make($config, self::rulesForGateway($gateway));
    }
}
