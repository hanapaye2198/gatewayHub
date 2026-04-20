<?php

namespace App\Services\Gateways\Drivers;

use App\Services\Gateways\Contracts\GatewayInterface;
use App\Services\Gateways\Exceptions\GatewayException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GcashDriver implements GatewayInterface
{
    private const CREATE_PAYMENT_PATH = '/v1/payments';

    private const PAYMENT_STATUS_PATH = '/v1/payments/%s';

    /** TCP connect timeout for outbound GCash API requests, in seconds. */
    private const HTTP_CONNECT_TIMEOUT_SECONDS = 3;

    /** Total request timeout for outbound GCash API requests, in seconds. */
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
            'provider_mode' => ['required', 'in:legacy,native_direct'],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:255'],
            'api_base_url' => ['required_if:provider_mode,native_direct', 'string', 'max:255'],
            'merchant_id' => ['required_if:provider_mode,native_direct', 'string', 'max:255'],
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
        return ['provider_mode', 'client_id', 'client_secret', 'api_base_url', 'merchant_id'];
    }

    public function createPayment(array $data): array
    {
        $mode = (string) ($this->config['provider_mode'] ?? 'legacy');
        if ($mode !== 'native_direct') {
            throw new GatewayException('GCash is in legacy mode. Set provider_mode to native_direct in Dashboard > Gateways.');
        }

        $clientId = (string) ($this->config['client_id'] ?? '');
        $clientSecret = (string) ($this->config['client_secret'] ?? '');
        $apiBaseUrl = rtrim((string) ($this->config['api_base_url'] ?? ''), '/');
        $merchantId = (string) ($this->config['merchant_id'] ?? '');
        if ($clientId === '' || $clientSecret === '' || $apiBaseUrl === '' || $merchantId === '') {
            throw new GatewayException('GCash native credentials are incomplete. Configure client_id, client_secret, api_base_url, and merchant_id.');
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        $currency = strtoupper((string) ($data['currency'] ?? 'PHP'));
        $reference = (string) ($data['reference'] ?? '');
        if ($amount <= 0 || $reference === '') {
            throw new GatewayException('Invalid GCash payment payload.');
        }

        $payload = [
            'requestReferenceNumber' => $reference,
            'merchantId' => $merchantId,
            'amount' => [
                'currency' => $currency,
                'value' => $amount,
            ],
            'redirectUrl' => $this->buildRedirectUrls(),
        ];

        try {
            /** @var Response $response */
            $response = Http::withBasicAuth($clientId, $clientSecret)
                ->connectTimeout(self::HTTP_CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->acceptJson()
                ->post($apiBaseUrl.self::CREATE_PAYMENT_PATH, $payload);
        } catch (HttpClientException $e) {
            throw new GatewayException('GCash API request failed: '.$e->getMessage(), 0, $e);
        }

        $body = $response->json();
        if (! is_array($body)) {
            $body = [];
        }
        if (! $response->successful()) {
            $message = $body['message'] ?? $body['error'] ?? $response->body();
            if (! is_string($message) || $message === '') {
                $message = 'GCash API returned an error.';
            }
            throw new GatewayException('GCash API error: '.$message);
        }

        $providerReference = $body['paymentId'] ?? $body['id'] ?? $reference;
        $redirectUrl = $body['redirectUrl'] ?? $body['checkoutUrl'] ?? $body['paymentUrl'] ?? null;
        if (! is_string($redirectUrl) || $redirectUrl === '') {
            throw new GatewayException('GCash API error: checkout redirect URL missing.');
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

        $signature = $request->header('x-gcash-signature')
            ?? $request->header('x-signature')
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
        if ($mode !== 'native_direct') {
            throw new GatewayException('GCash status check is unavailable in legacy mode.');
        }

        $clientId = (string) ($this->config['client_id'] ?? '');
        $clientSecret = (string) ($this->config['client_secret'] ?? '');
        $apiBaseUrl = rtrim((string) ($this->config['api_base_url'] ?? ''), '/');
        if ($clientId === '' || $clientSecret === '' || $apiBaseUrl === '') {
            throw new GatewayException('GCash native credentials are incomplete.');
        }

        try {
            /** @var Response $response */
            $response = Http::withBasicAuth($clientId, $clientSecret)
                ->connectTimeout(self::HTTP_CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->acceptJson()
                ->get($apiBaseUrl.sprintf(self::PAYMENT_STATUS_PATH, urlencode($reference)));
        } catch (HttpClientException $e) {
            throw new GatewayException('GCash status request failed: '.$e->getMessage(), 0, $e);
        }

        $body = $response->json();
        if (! is_array($body)) {
            $body = [];
        }
        if (! $response->successful()) {
            $message = $body['message'] ?? $body['error'] ?? $response->body();
            if (! is_string($message) || $message === '') {
                $message = 'Unable to fetch GCash payment status.';
            }
            throw new GatewayException('GCash API error: '.$message);
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
}
