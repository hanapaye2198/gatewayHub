<?php

namespace App\Services\Gateways\Drivers;

use App\Services\Gateways\Contracts\GatewayInterface;
use App\Services\Gateways\Exceptions\GatewayException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MayaDriver implements GatewayInterface
{
    private const SANDBOX_BASE_URL = 'https://pg-sandbox.paymaya.com';

    private const PROD_BASE_URL = 'https://pg.paymaya.com';

    private const CHECKOUT_CREATE_PATH = '/checkout/v1/checkouts';

    private const PAYMENT_STATUS_PATH = '/payments/v1/payments/%s/status';

    /** TCP connect timeout for outbound Maya API requests, in seconds. */
    private const HTTP_CONNECT_TIMEOUT_SECONDS = 3;

    /** Total request timeout for outbound Maya API requests, in seconds. */
    private const HTTP_TIMEOUT_SECONDS = 10;

    public function __construct(
        protected array $config = []
    ) {}

    /**
     * Validation rules for platform gateway config_json.
     *
     * @return array<string, mixed>
     */
    public static function configValidationRules(): array
    {
        return [
            'provider_mode' => ['required', 'in:legacy,native_checkout'],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:255'],
            'api_base' => ['required_if:provider_mode,native_checkout', 'string', 'in:sandbox,prod'],
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
        return ['provider_mode', 'client_id', 'client_secret'];
    }

    public function createPayment(array $data): array
    {
        $mode = (string) ($this->config['provider_mode'] ?? 'legacy');
        if ($mode !== 'native_checkout') {
            throw new GatewayException('Maya is in legacy mode. Set provider_mode to native_checkout in Dashboard > Gateways.');
        }

        $clientId = (string) ($this->config['client_id'] ?? '');
        $clientSecret = (string) ($this->config['client_secret'] ?? '');
        $apiBase = (string) ($this->config['api_base'] ?? 'sandbox');
        if ($clientId === '' || $clientSecret === '') {
            throw new GatewayException('Maya credentials are missing. Configure client_id and client_secret.');
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        $currency = strtoupper((string) ($data['currency'] ?? 'PHP'));
        $reference = (string) ($data['reference'] ?? '');
        if ($amount <= 0 || $reference === '') {
            throw new GatewayException('Invalid Maya payment payload.');
        }

        $payload = [
            'totalAmount' => [
                'value' => $amount,
                'currency' => $currency,
            ],
            'buyer' => [
                'firstName' => 'Merchant',
                'lastName' => 'Customer',
                'contact' => [
                    'email' => 'merchant@example.com',
                ],
            ],
            'redirectUrl' => $this->buildRedirectUrls(),
            'requestReferenceNumber' => $reference,
        ];

        $url = $this->baseUrl($apiBase).self::CHECKOUT_CREATE_PATH;

        try {
            /** @var Response $response */
            $response = Http::withBasicAuth($clientId, $clientSecret)
                ->connectTimeout(self::HTTP_CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->acceptJson()
                ->post($url, $payload);
        } catch (HttpClientException $e) {
            throw new GatewayException('Maya API request failed: '.$e->getMessage(), 0, $e);
        }

        $body = $response->json();
        if (! is_array($body)) {
            $body = [];
        }

        if (! $response->successful()) {
            $message = $body['message'] ?? $body['error'] ?? $response->body();
            if (! is_string($message) || $message === '') {
                $message = 'Maya API returned an error.';
            }
            throw new GatewayException('Maya API error: '.$message);
        }

        $providerReference = $body['checkoutId'] ?? $body['id'] ?? $reference;
        $redirectUrl = $body['redirectUrl'] ?? $body['checkoutUrl'] ?? null;
        if (! is_string($redirectUrl) || $redirectUrl === '') {
            throw new GatewayException('Maya API error: checkout redirect URL missing.');
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
        $secret = (string) ($this->config['webhook_key'] ?? '');
        if ($secret === '') {
            return false;
        }

        $signature = $request->header('x-paymaya-signature')
            ?? $request->header('x-maya-signature')
            ?? $request->header('signature');
        if (! is_string($signature) || $signature === '') {
            return false;
        }

        $payload = (string) $request->getContent();
        $computed = hash_hmac('sha256', $payload, $secret);

        return hash_equals($computed, trim($signature));
    }

    public function getPaymentStatus(string $reference): array
    {
        $mode = (string) ($this->config['provider_mode'] ?? 'legacy');
        if ($mode !== 'native_checkout') {
            throw new GatewayException('Maya status check is unavailable in legacy mode.');
        }

        $clientId = (string) ($this->config['client_id'] ?? '');
        $clientSecret = (string) ($this->config['client_secret'] ?? '');
        $apiBase = (string) ($this->config['api_base'] ?? 'sandbox');
        if ($clientId === '' || $clientSecret === '') {
            throw new GatewayException('Maya credentials are missing.');
        }

        $url = $this->baseUrl($apiBase).sprintf(self::PAYMENT_STATUS_PATH, urlencode($reference));

        try {
            /** @var Response $response */
            $response = Http::withBasicAuth($clientId, $clientSecret)
                ->connectTimeout(self::HTTP_CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->acceptJson()
                ->get($url);
        } catch (HttpClientException $e) {
            throw new GatewayException('Maya status request failed: '.$e->getMessage(), 0, $e);
        }

        $body = $response->json();
        if (! is_array($body)) {
            $body = [];
        }
        if (! $response->successful()) {
            $message = $body['message'] ?? $body['error'] ?? $response->body();
            if (! is_string($message) || $message === '') {
                $message = 'Unable to fetch Maya payment status.';
            }
            throw new GatewayException('Maya API error: '.$message);
        }

        return $body;
    }

    /**
     * @return array{success: string, failure: string, cancel: string}
     */
    private function buildRedirectUrls(): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $fallback = $base.'/dashboard/payments';

        return [
            'success' => (string) ($this->config['redirect_success_url'] ?? $fallback),
            'failure' => (string) ($this->config['redirect_failure_url'] ?? $fallback),
            'cancel' => (string) ($this->config['redirect_cancel_url'] ?? $fallback),
        ];
    }

    private function baseUrl(string $apiBase): string
    {
        return $apiBase === 'prod' ? self::PROD_BASE_URL : self::SANDBOX_BASE_URL;
    }
}
