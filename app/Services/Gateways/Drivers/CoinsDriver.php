<?php

namespace App\Services\Gateways\Drivers;

use App\Models\Merchant;
use App\Services\Coins\CoinsApiErrorMessageResolver;
use App\Services\Coins\CoinsGenerateQrRequestExecutor;
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
 *   "webhook_secret": "string"   // Required. Dedicated secret for webhook signature
 *                                // verification. Must NEVER fall back to
 *                                // client_secret / api_secret — using the API
 *                                // signing key to verify webhooks lets any API
 *                                // consumer forge callbacks.
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

    private const GET_QR_STATUS_PATH = '/openapi/fiat/v1/get_qr_code';

    private const DEFAULT_EXPIRATION_SECONDS = 1800;

    private const DEFAULT_CHECKOUT_PRODUCT_NAME = 'Service Payment';

    private const SOURCE_IDENTIFIER = 'GATEWAYHUB';

    /** TCP connect timeout for outbound Coins API requests, in seconds. */
    private const HTTP_CONNECT_TIMEOUT_SECONDS = 3;

    /** Total request timeout for outbound Coins API requests, in seconds. */
    private const HTTP_TIMEOUT_SECONDS = 10;

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
            'webhook_secret' => ['required', 'string', 'max:255'],
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
     * @param  array{amount: float|int|string, currency: string, reference: string, qr_code_merchant_name?: string|null}  $data
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
            'qrCodeMerchantName' => Merchant::normalizeQrCodeMerchantName(
                isset($data['qr_code_merchant_name']) ? (string) $data['qr_code_merchant_name'] : null
            ),
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
     * Create a hosted checkout session (Coins Checkout API). Uses the same header signing as Dynamic QR.
     *
     * @param  array{
     *     reference: string,
     *     amount: float|int|string,
     *     currency: string,
     *     merchant_name: string,
     *     redirect_urls: array{success: string, failure: string, cancel: string, default: string},
     *     product_name?: string
     * }  $data
     * @return array{external_payment_id: string, redirect_url: string, raw: array<string, mixed>}
     *
     * @throws CoinsApiException
     */
    public function createCheckoutSession(array $data): array
    {
        $this->ensureConfigValid();

        $reference = trim((string) ($data['reference'] ?? ''));
        if ($reference === '') {
            throw new CoinsApiException('Coins.ph checkout requires a non-empty reference.');
        }

        $currency = strtoupper(trim((string) ($data['currency'] ?? 'PHP')));
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new CoinsApiException('Coins.ph checkout requires a positive amount.');
        }

        $amountStr = number_format($amount, 2, '.', '');
        $merchantName = trim((string) ($data['merchant_name'] ?? ''));
        if ($merchantName === '') {
            $merchantName = Merchant::DEFAULT_DISPLAY_NAME;
        }

        $redirects = $data['redirect_urls'] ?? [];
        if (! is_array($redirects)) {
            throw new CoinsApiException('Coins.ph checkout requires redirect_urls.');
        }

        foreach (['success', 'failure', 'cancel', 'default'] as $key) {
            if (! isset($redirects[$key]) || ! is_string($redirects[$key]) || trim($redirects[$key]) === '') {
                throw new CoinsApiException(
                    'Coins.ph checkout requires redirect_urls (success, failure, cancel, default).'
                );
            }
        }

        $productName = trim((string) ($data['product_name'] ?? self::DEFAULT_CHECKOUT_PRODUCT_NAME));
        if ($productName === '') {
            $productName = self::DEFAULT_CHECKOUT_PRODUCT_NAME;
        }

        $checkoutPath = $this->checkoutApiPath();
        $bodyParams = [
            'requestId' => $reference,
            'totalAmount' => $amountStr,
            'amount' => $amountStr,
            'currency' => $currency,
            'merchantName' => $merchantName,
            'redirectUrl' => [
                'success' => $redirects['success'],
                'failure' => $redirects['failure'],
                'cancel' => $redirects['cancel'],
                'defaultUrl' => $redirects['default'],
            ],
            'productDetails' => [
                [
                    'name' => $productName,
                    'type' => 'others',
                    'amount' => $amountStr,
                ],
            ],
            'source' => $this->source,
        ];

        $execution = $this->generateQrRequestExecutor->execute(
            $this->getBaseUrl().$checkoutPath,
            $this->clientId,
            $this->clientSecret,
            $bodyParams,
            [
                'api_base' => $this->apiBase,
                'endpoint' => 'create_checkout',
                'request_id' => $reference,
                'include_canonical_for_debug' => $this->includeCanonicalForDebug,
            ]
        );

        $response = $execution['response'];
        $body = $execution['body'];

        $this->throwIfResponseHasError($response, $body);

        return $this->normalizeCheckoutResponse($body, $reference);
    }

    private function checkoutApiPath(): string
    {
        $path = trim((string) config('coins.checkout.path', '/openapi/fiat/v1/create_checkout'));
        if ($path === '') {
            $path = '/openapi/fiat/v1/create_checkout';
        }

        return str_starts_with($path, '/') ? $path : '/'.$path;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{external_payment_id: string, redirect_url: string, raw: array<string, mixed>}
     */
    private function normalizeCheckoutResponse(array $body, string $reference): array
    {
        $data = $body['data'] ?? $body;
        $checkoutUrl = null;
        if (is_array($data)) {
            $checkoutUrl = $data['checkoutUrl'] ?? $data['checkoutURL'] ?? $data['redirectUrl'] ?? $data['url'] ?? null;
        }
        if (! is_string($checkoutUrl) || trim($checkoutUrl) === '') {
            $checkoutUrl = $body['checkoutUrl'] ?? $body['redirectUrl'] ?? null;
        }
        if (! is_string($checkoutUrl) || trim($checkoutUrl) === '') {
            throw new CoinsApiException('Coins.ph checkout response missing checkout URL.');
        }

        $providerRef = null;
        if (is_array($data)) {
            $providerRef = $data['orderId'] ?? $data['checkoutId'] ?? $data['internalOrderId'] ?? $data['requestId'] ?? $data['id'] ?? null;
        }
        $providerRef = $providerRef ?? $body['orderId'] ?? $body['checkoutId'] ?? $reference;

        if (is_array($providerRef)) {
            $providerRef = $reference;
        }

        $externalId = $providerRef === null ? $reference : (string) $providerRef;

        return [
            'external_payment_id' => $externalId,
            'redirect_url' => $checkoutUrl,
            'raw' => $body,
        ];
    }

    /**
     * Verify webhook signature using CoinsSignatureService (no API call).
     *
     * Behaviour:
     *  - Returns true  when the request signature matches `webhook_secret`.
     *  - Returns false when a signature is present but does not match, or when
     *    the request carries no signature at all.
     *  - Throws {@see CoinsApiException} when `webhook_secret` is not
     *    configured. This is intentional: silently returning false on a
     *    misconfigured gateway would hide the fact that callbacks cannot be
     *    authenticated, and we refuse to fall back to `client_secret` /
     *    `api_secret` because that would let any API consumer forge webhooks.
     *
     * @throws CoinsApiException
     */
    public function verifyWebhook(Request $request): bool
    {
        if ($this->webhookSecret === '') {
            throw new CoinsApiException(
                'Coins.ph webhook_secret is not configured. Set a dedicated webhook_secret on the platform gateway — never reuse client_secret.'
            );
        }

        $signature = $request->header('X-COINS-SIGNATURE')
            ?? $request->header('Signature')
            ?? $request->header('signature')
            ?? $request->input('signature');
        if (! is_string($signature) || $signature === '') {
            return false;
        }

        $payload = $request->json()->all();
        if (! is_array($payload) || $payload === []) {
            $payload = array_merge($request->query(), $request->all());
        }

        try {
            return $this->signatureService->verifyWebhook(
                $payload,
                $this->webhookSecret,
                $signature,
                (string) $request->getContent()
            );
        } catch (CoinsApiException) {
            return false;
        }
    }

    public function getPaymentStatus(string $reference): array
    {
        $this->ensureConfigValid();

        $requestId = trim($reference);
        if ($requestId === '') {
            throw new CoinsApiException('Coins.ph status request requires a non-empty requestId.');
        }

        $query = ['requestId' => $requestId];
        $signed = $this->signatureService->signWebhook($query, $this->clientSecret);
        $query['signature'] = $signed['signature'];

        try {
            /** @var Response $response */
            $response = Http::withHeaders([
                'X-COINS-APIKEY' => $this->clientId,
                'Content-Type' => 'application/json; charset=utf-8',
            ])
                ->connectTimeout(self::HTTP_CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->acceptJson()
                ->get($this->getBaseUrl().self::GET_QR_STATUS_PATH, $query);
        } catch (HttpClientException $e) {
            throw new CoinsApiException('Coins.ph status request failed: '.$e->getMessage(), null, null, $e);
        }

        $body = $response->json();
        if (! is_array($body)) {
            $body = [];
        }

        $this->throwIfResponseHasError($response, $body);

        return $body;
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
        $normalizedBody = $this->augmentErrorBody($response, $body);

        if (! $response->successful()) {
            throw new CoinsApiException(
                'Coins.ph API error: '.$this->errorMessageResolver->resolve($normalizedBody, $response),
                $response->status(),
                $normalizedBody
            );
        }

        $status = $this->errorMessageResolver->extractStatusCode($normalizedBody, null, $response);
        if ($status !== null && $status !== 0) {
            throw new CoinsApiException(
                'Coins.ph API error: '.$this->errorMessageResolver->resolve($normalizedBody, $response, $status),
                $response->status(),
                $normalizedBody
            );
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function augmentErrorBody(Response $response, array $body): array
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
