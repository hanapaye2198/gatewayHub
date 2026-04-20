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
        $resolved = $this->forGatewayCodeWithMeta($code);

        return $resolved['config'];
    }

    /**
     * @return array{config: array<string, mixed>, credential_source: string}
     */
    public function forGatewayCodeWithMeta(string $code): array
    {
        $defaults = $this->normalizeConfig($this->defaultsForGatewayCode($code));
        $gateway = Gateway::query()->where('code', $code)->first();
        if (! $gateway instanceof Gateway) {
            return [
                'config' => $defaults,
                'credential_source' => 'env',
            ];
        }

        $stored = $gateway->config_json;
        if (! is_array($stored)) {
            return [
                'config' => $defaults,
                'credential_source' => 'env',
            ];
        }

        $overrides = $this->normalizeConfig($stored);

        if ($code === 'qrph' && $overrides === []) {
            return $this->forGatewayCodeWithMeta('coins');
        }

        $resolvedConfig = array_merge($defaults, $overrides);

        return [
            'config' => $resolvedConfig,
            'credential_source' => $overrides === [] ? 'env' : 'db',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultsForGatewayCode(string $code): array
    {
        return match ($code) {
            'coins', 'qrph' => [
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

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalizeConfig(array $config): array
    {
        $normalized = [];
        foreach ($config as $key => $value) {
            if (! is_string($key) || $key === '' || $value === null) {
                continue;
            }

            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed === '' || $this->isPlaceholderCredentialValue($trimmed)) {
                    continue;
                }
                $normalized[$key] = $trimmed;

                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function isPlaceholderCredentialValue(string $value): bool
    {
        $normalized = strtolower(trim($value));
        $placeholders = [
            'your_real_client_id',
            'your_real_client_secret',
            'your_real_webhook_secret',
            'your_client_id',
            'your_client_secret',
            'your_webhook_secret',
            'your_api_key',
            'your_api_secret',
            'change_me',
            'replace_me',
        ];

        return in_array($normalized, $placeholders, true);
    }
}
