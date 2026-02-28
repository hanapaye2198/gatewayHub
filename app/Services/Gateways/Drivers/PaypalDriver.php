<?php

namespace App\Services\Gateways\Drivers;

use App\Services\Gateways\Contracts\GatewayInterface;
use App\Services\Gateways\Exceptions\GatewayException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaypalDriver implements GatewayInterface
{
    private const HTTP_CONNECT_TIMEOUT_SECONDS = 2;

    private const HTTP_TIMEOUT_SECONDS = 6;

    private const SANDBOX_BASE_URL = 'https://api-m.sandbox.paypal.com';

    private const LIVE_BASE_URL = 'https://api-m.paypal.com';

    private const ACCESS_TOKEN_PATH = '/v1/oauth2/token';

    private const CREATE_ORDER_PATH = '/v2/checkout/orders';

    private const ORDER_STATUS_PATH = '/v2/checkout/orders/%s';

    private const ORDER_CAPTURE_PATH = '/v2/checkout/orders/%s/capture';

    public function __construct(
        protected array $config = []
    ) {}

    private function httpClient(): PendingRequest
    {
        return Http::acceptJson()
            ->connectTimeout(self::HTTP_CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::HTTP_TIMEOUT_SECONDS);
    }

    /**
     * Validation rules for platform gateway config_json.
     *
     * @return array<string, mixed>
     */
    public static function configValidationRules(): array
    {
        return [
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:255'],
            'api_base' => ['nullable', 'string', 'in:sandbox,live'],
            'webhook_id' => ['nullable', 'string', 'max:255'],
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
        $clientId = (string) ($this->config['client_id'] ?? '');
        $clientSecret = (string) ($this->config['client_secret'] ?? '');
        if ($clientId === '' || $clientSecret === '') {
            throw new GatewayException('PayPal credentials are missing. Configure client_id and client_secret.');
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        $currency = strtoupper((string) ($data['currency'] ?? 'PHP'));
        $reference = (string) ($data['reference'] ?? '');
        if ($amount <= 0 || $reference === '') {
            throw new GatewayException('Invalid PayPal payment payload.');
        }

        $accessToken = $this->getAccessToken($clientId, $clientSecret);
        $returnUrl = $this->buildRedirectUrl('success');
        $cancelUrl = $this->buildRedirectUrl('cancel');
        $payload = [
            'intent' => 'CAPTURE',
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'user_action' => 'PAY_NOW',
                        'return_url' => $returnUrl,
                        'cancel_url' => $cancelUrl,
                    ],
                ],
            ],
            'purchase_units' => [
                [
                    'reference_id' => $reference,
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                ],
            ],
        ];

        try {
            /** @var Response $response */
            $response = $this->httpClient()
                ->withToken($accessToken)
                ->post($this->baseUrl().self::CREATE_ORDER_PATH, $payload);
        } catch (HttpClientException $e) {
            throw new GatewayException('PayPal API request failed: '.$e->getMessage(), 0, $e);
        }

        $body = $response->json();
        if (! is_array($body)) {
            $body = [];
        }

        if (! $response->successful()) {
            $message = $body['message'] ?? $body['error_description'] ?? $body['error'] ?? $response->body();
            if (! is_string($message) || $message === '') {
                $message = 'PayPal API returned an error.';
            }
            throw new GatewayException('PayPal API error: '.$message);
        }

        $providerReference = $body['id'] ?? $reference;
        $redirectUrl = $this->extractApproveLink($body['links'] ?? []);
        if ($redirectUrl === null) {
            throw new GatewayException('PayPal API error: checkout redirect URL missing.');
        }

        return [
            'external_payment_id' => (string) $providerReference,
            'provider_reference' => (string) $providerReference,
            'redirect_url' => $redirectUrl,
            'raw' => $body,
        ];
    }

    public function verifyWebhook(Request $request): bool
    {
        $transmissionId = $request->header('PAYPAL-TRANSMISSION-ID');
        $transmissionTime = $request->header('PAYPAL-TRANSMISSION-TIME');
        $certUrl = $request->header('PAYPAL-CERT-URL');
        $authAlgo = $request->header('PAYPAL-AUTH-ALGO');
        $transmissionSig = $request->header('PAYPAL-TRANSMISSION-SIG');

        if (! is_string($transmissionId) || $transmissionId === ''
            || ! is_string($transmissionTime) || $transmissionTime === ''
            || ! is_string($certUrl) || $certUrl === ''
            || ! is_string($authAlgo) || $authAlgo === ''
            || ! is_string($transmissionSig) || $transmissionSig === '') {
            return false;
        }

        $webhookId = (string) ($this->config['webhook_id'] ?? config('paypal.webhook.webhook_id', ''));
        $clientId = (string) ($this->config['client_id'] ?? config('paypal.webhook.client_id', ''));
        $clientSecret = (string) ($this->config['client_secret'] ?? config('paypal.webhook.client_secret', ''));

        if ($webhookId === '' || $clientId === '' || $clientSecret === '') {
            return false;
        }

        $accessToken = $this->getAccessToken($clientId, $clientSecret);
        $payload = $request->json()->all();
        if (! is_array($payload)) {
            $payload = [];
        }

        try {
            /** @var Response $response */
            $response = $this->httpClient()
                ->withToken($accessToken)
                ->post($this->baseUrl().'/v1/notifications/verify-webhook-signature', [
                    'transmission_id' => $transmissionId,
                    'transmission_time' => $transmissionTime,
                    'cert_url' => $certUrl,
                    'auth_algo' => $authAlgo,
                    'transmission_sig' => $transmissionSig,
                    'webhook_id' => $webhookId,
                    'webhook_event' => $payload,
                ]);
        } catch (HttpClientException) {
            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        return $response->json('verification_status') === 'SUCCESS';
    }

    public function getPaymentStatus(string $reference): array
    {
        $clientId = (string) ($this->config['client_id'] ?? '');
        $clientSecret = (string) ($this->config['client_secret'] ?? '');
        if ($clientId === '' || $clientSecret === '') {
            throw new GatewayException('PayPal credentials are missing.');
        }

        $accessToken = $this->getAccessToken($clientId, $clientSecret);

        try {
            /** @var Response $response */
            $response = $this->httpClient()
                ->withToken($accessToken)
                ->get($this->baseUrl().sprintf(self::ORDER_STATUS_PATH, urlencode($reference)));
        } catch (HttpClientException $e) {
            throw new GatewayException('PayPal status request failed: '.$e->getMessage(), 0, $e);
        }

        $body = $response->json();
        if (! is_array($body)) {
            $body = [];
        }

        if (! $response->successful()) {
            $message = $body['message'] ?? $body['error_description'] ?? $body['error'] ?? $response->body();
            if (! is_string($message) || $message === '') {
                $message = 'Unable to fetch PayPal payment status.';
            }
            throw new GatewayException('PayPal API error: '.$message);
        }

        return $body;
    }

    public function capturePayment(string $reference): array
    {
        $clientId = (string) ($this->config['client_id'] ?? '');
        $clientSecret = (string) ($this->config['client_secret'] ?? '');
        if ($clientId === '' || $clientSecret === '') {
            throw new GatewayException('PayPal credentials are missing.');
        }

        $accessToken = $this->getAccessToken($clientId, $clientSecret);

        try {
            /** @var Response $response */
            $response = $this->httpClient()
                ->withToken($accessToken)
                ->withBody('{}', 'application/json')
                ->post($this->baseUrl().sprintf(self::ORDER_CAPTURE_PATH, urlencode($reference)));
        } catch (HttpClientException $e) {
            throw new GatewayException('PayPal capture request failed: '.$e->getMessage(), 0, $e);
        }

        $body = $response->json();
        if (! is_array($body)) {
            $body = [];
        }

        if (! $response->successful()) {
            $message = $body['message'] ?? $body['error_description'] ?? $body['error'] ?? $response->body();
            if (! is_string($message) || $message === '') {
                $message = 'Unable to capture PayPal order.';
            }
            throw new GatewayException('PayPal API error: '.$message);
        }

        return $body;
    }

    private function baseUrl(): string
    {
        $apiBase = (string) ($this->config['api_base'] ?? config('paypal.webhook.mode', 'sandbox'));

        return $apiBase === 'live'
            ? self::LIVE_BASE_URL
            : self::SANDBOX_BASE_URL;
    }

    private function getAccessToken(string $clientId, string $clientSecret): string
    {
        try {
            /** @var Response $tokenResponse */
            $tokenResponse = $this->httpClient()
                ->withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->post($this->baseUrl().self::ACCESS_TOKEN_PATH, [
                    'grant_type' => 'client_credentials',
                ]);
        } catch (HttpClientException $e) {
            throw new GatewayException('PayPal OAuth request failed: '.$e->getMessage(), 0, $e);
        }

        $tokenBody = $tokenResponse->json();
        if (! is_array($tokenBody)) {
            $tokenBody = [];
        }
        if (! $tokenResponse->successful()) {
            $message = $tokenBody['error_description'] ?? $tokenBody['error'] ?? $tokenResponse->body();
            if (! is_string($message) || $message === '') {
                $message = 'PayPal OAuth token request failed.';
            }
            throw new GatewayException('PayPal API error: '.$message);
        }

        $accessToken = $tokenBody['access_token'] ?? null;
        if (! is_string($accessToken) || $accessToken === '') {
            throw new GatewayException('PayPal API error: access token missing.');
        }

        return $accessToken;
    }

    private function buildRedirectUrl(string $type): string
    {
        $fallback = url('/dashboard/payments');

        $configured = match ($type) {
            'success' => $this->config['redirect_success_url'] ?? null,
            'failure' => $this->config['redirect_failure_url'] ?? null,
            default => $this->config['redirect_cancel_url'] ?? null,
        };

        if (! is_string($configured)) {
            return $fallback;
        }

        $configured = trim($configured);
        if ($configured === '') {
            return $fallback;
        }

        $isAbsolute = filter_var($configured, FILTER_VALIDATE_URL) !== false;

        return $isAbsolute ? $configured : $fallback;
    }

    private function extractApproveLink(mixed $links): ?string
    {
        if (! is_array($links)) {
            return null;
        }

        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }
            $rel = $link['rel'] ?? null;
            $href = $link['href'] ?? null;
            if (
                is_string($rel)
                && in_array(strtolower($rel), ['approve', 'payer-action'], true)
                && is_string($href)
                && $href !== ''
            ) {
                return $href;
            }
        }

        return null;
    }
}
