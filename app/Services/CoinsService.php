<?php

namespace App\Services;

use App\Models\Merchant;
use App\Services\Coins\CoinsApiErrorMessageResolver;
use App\Services\Coins\CoinsGenerateQrRequestExecutor;
use App\Services\Gateways\Exceptions\CoinsApiException;
use App\Services\Gateways\PlatformGatewayConfigService;

/**
 * Coins.ph Dynamic QR (Fiat) API client.
 *
 * Uses the centralized Coins gateway configuration first, with legacy COINS_* config
 * values as a backward-compatible override when all legacy values are explicitly set.
 * Signing strategy and timestamp behavior are controlled via config('coins.auth.generate_qr.*').
 */
class CoinsService
{
    private const SANDBOX_BASE_URL = 'https://api.9001.pl-qa.coinsxyz.me';

    private const PROD_BASE_URL = 'https://api.pro.coins.ph';

    private const GENERATE_QR_PATH = '/openapi/fiat/v1/generate_qr_code';

    private const DEFAULT_EXPIRATION_SECONDS = 1800;

    public const ERROR_CODE_IP_NOT_WHITELISTED = CoinsApiErrorMessageResolver::ERROR_CODE_IP_NOT_WHITELISTED;

    protected CoinsGenerateQrRequestExecutor $generateQrRequestExecutor;

    protected CoinsApiErrorMessageResolver $errorMessageResolver;

    protected PlatformGatewayConfigService $platformGatewayConfigService;

    public function __construct(
        ?CoinsGenerateQrRequestExecutor $generateQrRequestExecutor = null,
        ?CoinsApiErrorMessageResolver $errorMessageResolver = null,
        ?PlatformGatewayConfigService $platformGatewayConfigService = null
    ) {
        $this->generateQrRequestExecutor = $generateQrRequestExecutor ?? new CoinsGenerateQrRequestExecutor;
        $this->errorMessageResolver = $errorMessageResolver ?? new CoinsApiErrorMessageResolver;
        $this->platformGatewayConfigService = $platformGatewayConfigService ?? new PlatformGatewayConfigService;
    }

    /**
     * Call Coins generate_qr_code API and return decoded response.
     *
     * @param  array{amount: float|int|string, requestId: string, currency?: string, qr_code_merchant_name?: string|null}  $params
     * @return array{success: bool, qr_code_string: string|null, reference_id: string|null, raw: array<string, mixed>}
     *
     * @throws CoinsApiException On request failure or API error (non-2xx or status !== 0).
     */
    public function generateDynamicQr(array $params): array
    {
        $requestId = (string) ($params['requestId'] ?? '');
        $amount = $params['amount'] ?? 0;
        $currency = (string) ($params['currency'] ?? 'PHP');

        $runtimeConfig = $this->resolveRuntimeConfig();
        $baseUrl = $runtimeConfig['base_url'];
        $apiKey = $runtimeConfig['api_key'];
        $secretKey = $runtimeConfig['secret_key'];
        $source = $runtimeConfig['source'];

        if ($baseUrl === '') {
            throw new CoinsApiException(
                'Coins API is not configured: configure Coins platform credentials in SurePay admin settings or set legacy COINS_BASE_URL.'
            );
        }

        if ($apiKey === '' || $secretKey === '') {
            throw new CoinsApiException(
                'Coins API is not configured: configure Coins platform credentials in SurePay admin settings or set legacy COINS_API_KEY and COINS_SECRET_KEY.'
            );
        }

        $bodyParams = [
            'requestId' => $requestId,
            'amount' => (string) $amount,
            'currency' => $currency,
            'expiredSeconds' => (string) self::DEFAULT_EXPIRATION_SECONDS,
            'source' => $source,
            'qrCodeMerchantName' => Merchant::normalizeQrCodeMerchantName(
                isset($params['qr_code_merchant_name']) ? (string) $params['qr_code_merchant_name'] : null
            ),
        ];

        $url = str_ends_with($baseUrl, self::GENERATE_QR_PATH)
            ? $baseUrl
            : $baseUrl.self::GENERATE_QR_PATH;

        $execution = $this->generateQrRequestExecutor->execute(
            $url,
            $apiKey,
            $secretKey,
            $bodyParams,
            [
                'api_base' => $baseUrl,
                'endpoint' => 'generate_qr_code',
                'request_id' => $requestId,
                'include_canonical_for_debug' => false,
            ]
        );

        $response = $execution['response'];
        $body = $execution['body'];

        $this->throwIfResponseHasError($response, $body);

        $data = $body['data'] ?? $body;
        $qrCodeString = null;
        $referenceId = $requestId;
        if (is_array($data)) {
            $qrCodeString = $data['qrCode'] ?? $data['qr_string'] ?? $data['qrString'] ?? $data['payload'] ?? null;
            if (! is_string($qrCodeString)) {
                $qrCodeString = null;
            }
            $referenceId = $data['orderId'] ?? $data['internalOrderId'] ?? $data['requestId'] ?? $requestId;
            if (is_array($referenceId)) {
                $referenceId = $requestId;
            }
            $referenceId = (string) $referenceId;
        }

        return [
            'success' => true,
            'qr_code_string' => $qrCodeString,
            'reference_id' => $referenceId,
            'raw' => $body,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function throwIfResponseHasError(\Illuminate\Http\Client\Response $response, array $body): void
    {
        $normalizedBody = $this->augmentErrorBody($response, $body);

        if (! $response->successful()) {
            throw new CoinsApiException(
                'Coins API error: '.$this->errorMessageResolver->resolve($normalizedBody, $response),
                $response->status(),
                $normalizedBody
            );
        }

        $status = $this->errorMessageResolver->extractStatusCode($normalizedBody, null, $response);
        if ($status !== null && $status !== 0) {
            if ($status === self::ERROR_CODE_IP_NOT_WHITELISTED) {
                throw new CoinsApiException(
                    'IP not whitelisted. Please contact Coins to whitelist server IP.',
                    $response->status(),
                    $normalizedBody
                );
            }

            throw new CoinsApiException(
                'Coins API error: '.$this->errorMessageResolver->resolve($normalizedBody, $response, $status),
                $response->status(),
                $normalizedBody
            );
        }
    }

    /**
     * @return array{base_url: string, api_key: string, secret_key: string, source: string}
     */
    private function resolveRuntimeConfig(): array
    {
        $legacyBaseUrl = rtrim((string) config('coins.base_url', ''), '/');
        $legacyApiKey = trim((string) config('coins.api_key', ''));
        $legacySecretKey = trim((string) config('coins.secret_key', ''));
        $legacySource = trim((string) config('coins.source', 'GATEWAYHUB'));

        if ($legacyBaseUrl !== '' && $legacyApiKey !== '' && $legacySecretKey !== '') {
            return [
                'base_url' => $legacyBaseUrl,
                'api_key' => $legacyApiKey,
                'secret_key' => $legacySecretKey,
                'source' => $legacySource !== '' ? $legacySource : 'GATEWAYHUB',
            ];
        }

        $platformConfig = $this->platformGatewayConfigService->forGatewayCode('coins');
        $platformApiBase = strtolower(trim((string) ($platformConfig['api_base'] ?? config('coins.gateway.api_base', 'sandbox'))));
        $platformSource = trim((string) ($platformConfig['source'] ?? config('coins.gateway.source', config('coins.source', 'GATEWAYHUB'))));

        return [
            'base_url' => $this->baseUrlForApiBase($platformApiBase),
            'api_key' => trim((string) ($platformConfig['client_id'] ?? $platformConfig['api_key'] ?? '')),
            'secret_key' => trim((string) ($platformConfig['client_secret'] ?? $platformConfig['api_secret'] ?? '')),
            'source' => $platformSource !== '' ? $platformSource : 'GATEWAYHUB',
        ];
    }

    private function baseUrlForApiBase(string $apiBase): string
    {
        return match ($apiBase) {
            'sandbox' => self::SANDBOX_BASE_URL,
            'prod' => self::PROD_BASE_URL,
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function augmentErrorBody(\Illuminate\Http\Client\Response $response, array $body): array
    {
        if ($body !== []) {
            return $body;
        }

        $status = $this->errorMessageResolver->extractStatusCode($body, null, $response);
        if ($status === null) {
            return $body;
        }

        return [
            'status' => $status,
            'message' => trim($response->body()),
        ];
    }
}
