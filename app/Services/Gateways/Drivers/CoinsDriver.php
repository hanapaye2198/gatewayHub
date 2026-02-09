<?php

namespace App\Services\Gateways\Drivers;

use App\Services\Coins\CoinsSignatureService;
use App\Services\Gateways\Contracts\GatewayInterface;
use App\Services\Gateways\Exceptions\CoinsApiException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Coins.ph gateway driver.
 *
 * Expected merchant_gateways.config_json structure for this driver (per merchant):
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
 * One merchant can have different credentials than another; config is stored per merchant_gateways row.
 */
class CoinsDriver implements GatewayInterface
{
    private const SANDBOX_BASE_URL = 'https://api.sandbox.coins.ph';

    private const PROD_BASE_URL = 'https://api.pro.coins.ph';

    private const GENERATE_QR_PATH = '/openapi/fiat/v1/generate_qr_code';

    private const DEFAULT_EXPIRATION_SECONDS = 1800;

    protected string $clientId = '';

    protected string $clientSecret = '';

    protected string $apiBase = '';

    protected string $webhookSecret = '';

    protected CoinsSignatureService $signatureService;

    public function __construct(array $config = [], ?CoinsSignatureService $signatureService = null)
    {
        $this->clientId = (string) ($config['client_id'] ?? $config['api_key'] ?? '');
        $this->clientSecret = (string) ($config['client_secret'] ?? $config['api_secret'] ?? '');
        $this->apiBase = (string) ($config['api_base'] ?? '');
        $this->webhookSecret = (string) ($config['webhook_secret'] ?? '');
        $this->signatureService = $signatureService ?? new CoinsSignatureService;
    }

    /**
     * Validation rules for Coins.ph config_json (use in Form Requests when saving merchant gateway config).
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
     * Create a Dynamic QR PH payment via Coins.ph API.
     *
     * @param  array{amount: float|int|string, currency: string, reference: string}  $data
     * @return array{provider_reference: string|null, qr_string?: string, qr_image?: string, raw_response: array, reference_id: string, status: string}
     *
     * @throws CoinsApiException
     */
    public function createPayment(array $data): array
    {
        $this->ensureConfigValid();

        $reference = (string) ($data['reference'] ?? '');
        $amount = $data['amount'] ?? 0;
        $currency = (string) ($data['currency'] ?? 'PHP');
        $expirationSeconds = self::DEFAULT_EXPIRATION_SECONDS;

        $params = [
            'requestId' => $reference,
            'amount' => (string) $amount,
            'currency' => $currency,
            'expiredSeconds' => (string) $expirationSeconds,
            'recvWindow' => '5000',
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];

        $signed = $this->signatureService->sign($params, $this->clientSecret);
        $params['signature'] = $signed['signature'];

        $baseUrl = $this->getBaseUrl();
        $queryString = $this->buildQueryString($params);
        $url = $baseUrl.self::GENERATE_QR_PATH.'?'.$queryString;

        try {
            /** @var Response $response */
            $response = Http::withHeaders([
                'X-COINS-APIKEY' => $this->clientId,
            ])->post($url);
        } catch (HttpClientException $e) {
            throw new CoinsApiException(
                'Coins.ph API request failed: '.$e->getMessage(),
                null,
                null,
                $e
            );
        }

        $body = $response->json();
        if (! is_array($body)) {
            $body = [];
        }

        if (! $response->successful()) {
            $message = $body['msg'] ?? $body['message'] ?? $response->body();
            if (is_array($message)) {
                $message = json_encode($message);
            }
            throw new CoinsApiException(
                'Coins.ph API error: '.(is_string($message) ? $message : 'Unknown error'),
                $response->status(),
                $body
            );
        }

        if (isset($body['code']) && (int) $body['code'] !== 0 && (int) $body['code'] > 0) {
            $message = $body['msg'] ?? $body['message'] ?? 'API returned error code '.$body['code'];
            throw new CoinsApiException(
                'Coins.ph API error: '.(is_string($message) ? $message : 'Unknown error'),
                $response->status(),
                $body
            );
        }

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

    /**
     * Build query string from params (sorted by key) for Coins API.
     *
     * @param  array<string, string>  $params
     */
    private function buildQueryString(array $params): string
    {
        ksort($params, SORT_STRING);

        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        return implode('&', $pairs);
    }

    private function ensureConfigValid(): void
    {
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new CoinsApiException('Coins.ph driver is missing client_id or client_secret in config.');
        }
        if ($this->apiBase === '') {
            throw new CoinsApiException('Coins.ph driver is missing api_base in config.');
        }
    }

    private function getBaseUrl(): string
    {
        return $this->apiBase === 'sandbox' ? self::SANDBOX_BASE_URL : self::PROD_BASE_URL;
    }

    /**
     * Normalize Coins API response to gateway format.
     *
     * @param  array<string, mixed>  $body
     * @return array{provider_reference: string|null, qr_string?: string, qr_image?: string, raw_response: array, reference_id: string, status: string}
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
        $providerRef = $providerRef === null ? $reference : (string) $providerRef;

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

        $normalized = [
            'provider_reference' => $providerRef,
            'raw_response' => $body,
            'reference_id' => $providerRef,
            'status' => 'pending',
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
