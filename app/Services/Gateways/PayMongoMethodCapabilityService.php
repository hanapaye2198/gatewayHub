<?php

namespace App\Services\Gateways;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class PayMongoMethodCapabilityService
{
    private const API_URL = 'https://api.paymongo.com/v1/merchants/capabilities/payment_methods';

    private const VERIFIED_CACHE_SECONDS = 120;

    private const UNVERIFIED_CACHE_SECONDS = 15;

    /**
     * KemoPay gateway codes mapped to PayMongo's payment method identifiers.
     *
     * @var array<string, list<string>>
     */
    private const PAYMENT_METHOD_TYPES = [
        'qrph' => ['qrph'],
        'gcash' => ['gcash'],
        'maya' => ['paymaya', 'maya'],
        'grab_pay' => ['grab_pay'],
        'shopeepay' => ['shopeepay'],
    ];

    public function __construct(
        protected PlatformGatewayConfigService $platformGatewayConfigService
    ) {}

    public function isPayMongoMethod(string $gatewayCode): bool
    {
        return array_key_exists($gatewayCode, self::PAYMENT_METHOD_TYPES);
    }

    /**
     * @return list<string>
     */
    public static function gatewayCodes(): array
    {
        return array_keys(self::PAYMENT_METHOD_TYPES);
    }

    /**
     * Return active method codes, or null if PayMongo's account capabilities could not be verified.
     *
     * @return list<string>|null
     */
    public function availableGatewayCodes(): ?array
    {
        $paymentMethodTypes = $this->paymentMethodTypes();
        if ($paymentMethodTypes === null) {
            return null;
        }

        $gatewayCodes = [];
        foreach (self::PAYMENT_METHOD_TYPES as $gatewayCode => $providerTypes) {
            if (array_intersect($providerTypes, $paymentMethodTypes) !== []) {
                $gatewayCodes[] = $gatewayCode;
            }
        }

        return $gatewayCodes;
    }

    /**
     * Return the provider-side status for one KemoPay gateway code.
     *
     * @return 'available'|'unavailable'|'unverified'|'not_applicable'
     */
    public function statusForGateway(string $gatewayCode): string
    {
        if (! $this->isPayMongoMethod($gatewayCode)) {
            return 'not_applicable';
        }

        $availableGatewayCodes = $this->availableGatewayCodes();
        if ($availableGatewayCodes === null) {
            return 'unverified';
        }

        return in_array($gatewayCode, $availableGatewayCodes, true)
            ? 'available'
            : 'unavailable';
    }

    /**
     * Return a merchant-safe capability summary without exposing PayMongo credentials.
     *
     * @return array{status: 'verified'|'unverified', available_methods: list<string>, methods: array<string, 'available'|'unavailable'|'unverified'>}
     */
    public function summary(): array
    {
        $availableGatewayCodes = $this->availableGatewayCodes();
        $verified = $availableGatewayCodes !== null;
        $methods = [];

        foreach (self::PAYMENT_METHOD_TYPES as $gatewayCode => $providerTypes) {
            $methods[$gatewayCode] = ! $verified
                ? 'unverified'
                : (in_array($gatewayCode, $availableGatewayCodes, true) ? 'available' : 'unavailable');
        }

        return [
            'status' => $verified ? 'verified' : 'unverified',
            'available_methods' => $availableGatewayCodes ?? [],
            'methods' => $methods,
        ];
    }

    public function forgetCurrentAccountCache(): void
    {
        $config = $this->platformGatewayConfigService->forGatewayCode('paymongo');
        $secretKey = trim((string) ($config['secret_key'] ?? ''));
        if ($secretKey !== '') {
            Cache::forget($this->cacheKey($secretKey));
        }
    }

    /**
     * @return list<string>|null
     */
    private function paymentMethodTypes(): ?array
    {
        $config = $this->platformGatewayConfigService->forGatewayCode('paymongo');
        $secretKey = trim((string) ($config['secret_key'] ?? ''));
        if ($secretKey === '') {
            return null;
        }

        $cacheKey = $this->cacheKey($secretKey);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && array_key_exists('verified', $cached) && is_array($cached['methods'] ?? null)) {
            return $cached['verified'] === true ? $cached['methods'] : null;
        }

        $paymentMethodTypes = $this->fetchPaymentMethodTypes($secretKey);
        Cache::put($cacheKey, [
            'verified' => $paymentMethodTypes !== null,
            'methods' => $paymentMethodTypes ?? [],
        ], now()->addSeconds(
            $paymentMethodTypes === null
                ? self::UNVERIFIED_CACHE_SECONDS
                : self::VERIFIED_CACHE_SECONDS
        ));

        return $paymentMethodTypes;
    }

    /**
     * @return list<string>|null
     */
    private function fetchPaymentMethodTypes(string $secretKey): ?array
    {
        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->connectTimeout(3)
                ->timeout(5)
                ->acceptJson()
                ->get(self::API_URL);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->json();
        if (! is_array($body)) {
            return null;
        }

        foreach ([
            'data.attributes.payment_methods',
            'data.attributes.payment_method_types',
            'data.attributes.available_payment_methods',
            'data.payment_methods',
            'payment_methods',
        ] as $path) {
            $methods = data_get($body, $path);
            if (is_array($methods)) {
                return $this->normalizePaymentMethodTypes($methods);
            }
        }

        $data = $body['data'] ?? null;
        if (is_array($data) && array_is_list($data)) {
            return $this->normalizePaymentMethodTypes($data);
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $methods
     * @return list<string>
     */
    private function normalizePaymentMethodTypes(array $methods): array
    {
        $normalized = [];
        foreach ($methods as $key => $method) {
            if (is_string($method)) {
                $normalized[] = strtolower(trim($method));

                continue;
            }

            if (is_bool($method) && $method && is_string($key)) {
                $normalized[] = strtolower(trim($key));

                continue;
            }

            if (! is_array($method)) {
                continue;
            }

            foreach (['type', 'code', 'id', 'payment_method'] as $attribute) {
                $value = $method[$attribute] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $normalized[] = strtolower(trim($value));

                    break;
                }
            }
        }

        return array_values(array_unique(array_filter($normalized, static fn (string $type): bool => $type !== '')));
    }

    private function cacheKey(string $secretKey): string
    {
        return 'paymongo.payment_method_capabilities.'.hash('sha256', $secretKey);
    }
}
