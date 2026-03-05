<?php

namespace App\Services\Gateways;

use App\Models\Gateway;

class PlatformGatewayConfigService
{
    /**
     * @return array<string, mixed>
     */
    public function forGatewayCode(string $code): array
    {
        $defaults = $this->defaultsForGatewayCode($code);
        $gateway = Gateway::query()->where('code', $code)->first();
        if (! $gateway instanceof Gateway) {
            return $defaults;
        }

        $stored = $gateway->config_json;
        if (! is_array($stored)) {
            return $defaults;
        }

        $overrides = [];
        foreach ($stored as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            $overrides[$key] = is_string($value) ? trim($value) : $value;
        }

        if (in_array($code, ['qrph', 'payqrph'], true) && $overrides === []) {
            return $this->forGatewayCode('coins');
        }

        return array_merge($defaults, $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultsForGatewayCode(string $code): array
    {
        return match ($code) {
            'coins', 'qrph', 'payqrph' => [
                'client_id' => (string) config('coins.gateway.client_id', config('coins.api_key', '')),
                'client_secret' => (string) config('coins.gateway.client_secret', config('coins.secret_key', '')),
                'api_base' => (string) config('coins.gateway.api_base', 'sandbox'),
                'webhook_secret' => (string) config('coins.webhook.secret', ''),
                'source' => (string) config('coins.gateway.source', config('coins.source', 'GATEWAYHUB')),
            ],
            'gcash' => [
                'provider_mode' => (string) config('gcash.gateway.provider_mode', 'native_direct'),
                'client_id' => (string) config('gcash.gateway.client_id', ''),
                'client_secret' => (string) config('gcash.gateway.client_secret', ''),
                'api_base_url' => (string) config('gcash.gateway.api_base_url', ''),
                'merchant_id' => (string) config('gcash.gateway.merchant_id', ''),
                'redirect_success_url' => (string) config('gcash.gateway.redirect_success_url', ''),
                'redirect_failure_url' => (string) config('gcash.gateway.redirect_failure_url', ''),
                'redirect_cancel_url' => (string) config('gcash.gateway.redirect_cancel_url', ''),
                'webhook_key' => $this->fallbackString(
                    config('gcash.gateway.webhook_key', ''),
                    config('gcash.webhook.secret', '')
                ),
            ],
            'maya' => [
                'provider_mode' => (string) config('maya.gateway.provider_mode', 'native_checkout'),
                'client_id' => (string) config('maya.gateway.client_id', ''),
                'client_secret' => (string) config('maya.gateway.client_secret', ''),
                'api_base' => (string) config('maya.gateway.api_base', 'sandbox'),
                'redirect_success_url' => (string) config('maya.gateway.redirect_success_url', ''),
                'redirect_failure_url' => (string) config('maya.gateway.redirect_failure_url', ''),
                'redirect_cancel_url' => (string) config('maya.gateway.redirect_cancel_url', ''),
                'webhook_key' => $this->fallbackString(
                    config('maya.gateway.webhook_key', ''),
                    config('maya.webhook.secret', '')
                ),
            ],
            'paypal' => [
                'client_id' => (string) config('paypal.gateway.client_id', config('paypal.webhook.client_id', '')),
                'client_secret' => (string) config('paypal.gateway.client_secret', config('paypal.webhook.client_secret', '')),
                'api_base' => (string) config('paypal.gateway.mode', config('paypal.webhook.mode', 'sandbox')),
                'webhook_id' => (string) config('paypal.gateway.webhook_id', config('paypal.webhook.webhook_id', '')),
                'redirect_success_url' => (string) config('paypal.gateway.redirect_success_url', ''),
                'redirect_failure_url' => (string) config('paypal.gateway.redirect_failure_url', ''),
                'redirect_cancel_url' => (string) config('paypal.gateway.redirect_cancel_url', ''),
            ],
            default => [],
        };
    }

    private function fallbackString(mixed $preferred, mixed $fallback): string
    {
        $preferredValue = is_string($preferred) ? trim($preferred) : '';
        if ($preferredValue !== '') {
            return $preferredValue;
        }

        return is_string($fallback) ? trim($fallback) : '';
    }
}
