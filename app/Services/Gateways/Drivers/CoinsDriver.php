<?php

namespace App\Services\Gateways\Drivers;

use App\Services\Coins\CoinsApiErrorMessageResolver;
use App\Services\Coins\CoinsGenerateQrRequestExecutor;
use App\Services\Coins\CoinsSignatureService;
use App\Services\Gateways\Contracts\GatewayInterface;
use App\Services\Gateways\Exceptions\CoinsApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

/**
 * Coins.ph gateway driver.
 *
 * Expected gateways.config_json structure for this driver (platform-managed):
 *
 * Credentials may use either naming convention (resolved with priority):
 * - client_id (preferred) or api_key
 * - client_secret (preferred) or api_secret
 *
 * {
 *   "client_id": "string",       // Or "api_key". Coins.ph OAuth2 / API client ID.
 *   "client_secret": "string",   // Or "api_secret". Coins.ph OAuth2 / API client secret.
 *   "api_base": "sandbox|prod",  // Required. Environment: "sandbox" or "prod".
 *   "webhook_secret": "string"   // Optional. For webhook signature verification (later phase).
 * }
 *
 * The same platform credentials are shared across merchants for this gateway.
 */
class CoinsDriver implements GatewayInterface
{
    /** Sandbox domain per Coins Partner Integration Guide v2.5 (Dynamic QRPH). */
    private const SANDBOX_BASE_URL = 'https://api.9001.pl-qa.coinsxyz.me';

    private const PROD_BASE_URL = 'https://api.pro.coins.ph';

    private const GENERATE_QR_PATH = '/openapi/fiat/v1/generate_qr_code';

    private const DEFAULT_EXPIRATION_SECONDS = 1800;

    private const SOURCE_IDENTIFIER = 'GATEWAYHUB';

    protected string $clientId = '';

    protected string $clientSecret = '';

    protected string $apiBase = '';

    protected string $webhookSecret = '';

    protected string $source = self::SOURCE_IDENTIFIER;

    protected bool $includeCanonicalForDebug = false;

    protected CoinsGenerateQrRequestExecutor $generateQrRequestExecutor;

    protected CoinsSignatureService $signatureService;

    protected CoinsApiErrorMessageResolver $errorMessageResolver;

    public function __construct(
        array $config = [],
        ?CoinsGenerateQrRequestExecutor $generateQrRequestExecutor = null,
        ?CoinsSignatureService $signatureService = null,
        ?CoinsApiErrorMessageResolver $errorMessageResolver = null
    ) {
        $this->clientId = trim((string) ($config['client_id'] ?? $config['api_key'] ?? ''));
        $this->clientSecret = trim((string) ($config['client_secret'] ?? $config['api_secret'] ?? ''));
        $this->apiBase = strtolower(trim((string) ($config['api_base'] ?? '')));
        $this->webhookSecret = trim((string) ($config['webhook_secret'] ?? ''));
        $this->source = trim((string) ($config['source'] ?? self::SOURCE_IDENTIFIER));
        if ($this->source === '') {
            $this->source = self::SOURCE_IDENTIFIER;
        }
        $this->includeCanonicalForDebug = (bool) ($config['includeCanonicalForDebug'] ?? false);
        $this->generateQrRequestExecutor = $generateQrRequestExecutor ?? new CoinsGenerateQrRequestExecutor;
        $this->signatureService = $signatureService ?? new CoinsSignatureService;
        $this->errorMessageResolver = $errorMessageResolver ?? new CoinsApiErrorMessageResolver;
    }

    /**
     * Validation rules for Coins.ph platform gateway config_json.
     *
     * @return array<string, mixed>
     */
    public static function configValidationRules(): array
    {
        return [
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:255'],
            'api_base' => ['required', 'string', 'in:sandbox,prod'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Required config keys for payment creation. Gateway must have these configured.
     *
     * @return list<string>
     */
    public static function getRequiredConfigKeys(): array
    {
        return ['client_id', 'client_secret', 'api_base'];
    }

    /**
     * Create a Dynamic QR PH payment via Coins.ph Fiat API (POST JSON body, header-based signing).
     *
     * Follows Coins Partner Integration Guide v2.5: no signature or timestamp in URL;
     * JSON body only; X-COINS-APIKEY, Timestamp, and Signature in headers.
     *
     * @param  array{amount: float|int|string, currency: string, reference: string}  $data
     * @return array{external_payment_id: string, qr_data: string|null, raw: array, provider_reference?: string, qr_string?: string, qr_image?: string}
     *
     * @throws CoinsApiException On non-2xx HTTP status or response status !== 0. No payment record created on error.
     */
    public function createPayment(array $data): array
    {
        $this->ensureConfigValid();

        $reference = (string) ($data['reference'] ?? '');
        $amount = $data['amount'] ?? 0;
        $currency = (string) ($data['currency'] ?? 'PHP');
        $expirationSeconds = self::DEFAULT_EXPIRATION_SECONDS;

        $bodyParams = [
            'requestId' => $reference,
            'amount' => (string) $amount,
            'currency' => $currency,
            'expiredSeconds' => (string) $expirationSeconds,
            'source' => $this->source,
        ];

        $execution = $this->generateQrRequestExecutor->execute(
            $this->getBaseUrl().self::GENERATE_QR_PATH,
            $this->clientId,
            $this->clientSecret,
            $bodyParams,
            [
                'api_base' => $this->apiBase,
                'endpoint' => 'generate_qr_code',
                'request_id' => $reference,
                'include_canonical_for_debug' => $this->includeCanonicalForDebug,
            ]
        );

        $response = $execution['response'];
        $body = $execution['body'];

        $this->throwIfResponseHasError($response, $body);

        return $this->normalizeResponse($body, $reference);
    }

    /**
     * Verify webhook signature using CoinsSignatureService (no API call).
     * Returns false if webhook_secret is not configured or request has no signature to verify.
     */
    public function verifyWebhook(Request $request): bool
    {
        if ($this->webhookSecret === '') {
            return false;
        }

        $signature = $request->header('X-COINS-SIGNATURE') ?? $request->input('signature');
        if (! is_string($signature) || $signature === '') {
            return false;
        }

        $payload = array_merge($request->query(), $request->all());
        if (! array_key_exists('timestamp', $payload)) {
            return false;
        }

        try {
            return $this->signatureService->verify($payload, $this->webhookSecret, $signature);
        } catch (CoinsApiException) {
            return false;
        }
    }

    public function getPaymentStatus(string $reference): array
    {
        return [];
    }

    private function ensureConfigValid(): void
    {
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new CoinsApiException('Coins.ph driver is missing client_id or client_secret in config.');
        }
        if (! in_array($this->apiBase, ['sandbox', 'prod'], true)) {
            throw new CoinsApiException('Coins.ph driver is missing api_base in config.');
        }
    }

    private function getBaseUrl(): string
    {
        return $this->apiBase === 'sandbox' ? self::SANDBOX_BASE_URL : self::PROD_BASE_URL;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function throwIfResponseHasError(Response $response, array $body): void
    {
        if (! $response->successful()) {
            throw new CoinsApiException(
                'Coins.ph API error: '.$this->errorMessageResolver->resolve($body, $response),
                $response->status(),
                $body
            );
        }

        $status = $body['status'] ?? $body['code'] ?? null;
        if ($status !== null && (int) $status !== 0) {
            throw new CoinsApiException(
                'Coins.ph API error: '.$this->errorMessageResolver->resolve($body, $response, $status),
                $response->status(),
                $body
            );
        }
    }

    /**
     * Normalize Coins API response to structured array: external_payment_id, qr_data, raw.
     * Also includes backward-compatible keys (provider_reference, qr_string, qr_image).
     *
     * @param  array<string, mixed>  $body
     * @return array{external_payment_id: string, qr_data: string|null, raw: array, provider_reference: string, qr_string?: string, qr_image?: string}
     */
    private function normalizeResponse(array $body, string $reference): array
    {
        $providerRef = null;
        if (isset($body['data']) && is_array($body['data'])) {
            $data = $body['data'];
            $providerRef = $data['orderId'] ?? $data['internalOrderId'] ?? $data['id'] ?? $data['requestId'] ?? null;
        }
        $providerRef = $providerRef ?? $body['orderId'] ?? $body['internalOrderId'] ?? $body['id'] ?? $body['requestId'] ?? $reference;

        if (is_array($providerRef)) {
            $providerRef = $reference;
        }
        $externalPaymentId = $providerRef === null ? $reference : (string) $providerRef;

        $qrString = null;
        $qrImage = null;
        $data = $body['data'] ?? $body;
        if (is_array($data)) {
            $qrString = $data['qrCode'] ?? $data['qr_string'] ?? $data['qrString'] ?? $data['payload'] ?? null;
            $qrImage = $data['qrImage'] ?? $data['qr_image'] ?? $data['qrImageUrl'] ?? $data['imageUrl'] ?? null;
        }
        if ($qrString !== null && ! is_string($qrString)) {
            $qrString = null;
        }
        if ($qrImage !== null && ! is_string($qrImage)) {
            $qrImage = null;
        }

        $qrData = $qrString ?? $qrImage;
        $expiresAt = now()->addSeconds(self::DEFAULT_EXPIRATION_SECONDS)->format('c');

        $normalized = [
            'external_payment_id' => $externalPaymentId,
            'qr_data' => $qrData,
            'expires_at' => $expiresAt,
            'raw' => $body,
            'provider_reference' => $externalPaymentId,
        ];
        if ($qrString !== null) {
            $normalized['qr_string'] = $qrString;
        }
        if ($qrImage !== null) {
            $normalized['qr_image'] = $qrImage;
        }

        return $normalized;
    }
}
