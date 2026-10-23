<?php

namespace App\Services\Gateways\Drivers;

use App\Services\Gateways\Contracts\GatewayInterface;
use App\Services\Gateways\Exceptions\GatewayException;
use App\Services\Webhooks\PayMongoWebhookSignatureVerifier;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PayMongoDriver implements GatewayInterface
{
    private const API_BASE_URL = 'https://api.paymongo.com';

    private const PAYMENT_INTENT_STATUS_API_BASE_URL = 'https://orders-api.paymongo.com';

    private const LEGACY_PAYMENT_INTENT_STATUS_API_BASE_URL = 'https://api.paymongo.com';

    private const PAYMENT_INTENTS_PATH = '/v1/payment_intents';

    private const PAYMENT_METHODS_PATH = '/v1/payment_methods';

    private const HTTP_CONNECT_TIMEOUT_SECONDS = 3;

    private const HTTP_TIMEOUT_SECONDS = 10;

    private const DEFAULT_QRPH_EXPIRY_SECONDS = 1800;

    private const PAYMENT_METHOD_TYPES = [
        'qrph' => 'qrph',
        'gcash' => 'gcash',
        'maya' => 'paymaya',
        'grab_pay' => 'grab_pay',
        'shopeepay' => 'shopeepay',
    ];

    private const PAYMENT_METHOD_EXPIRY_SECONDS = [
        'gcash' => 14400,
        'maya' => 1800,
        'grab_pay' => 900,
        'shopeepay' => 1200,
    ];

    public function __construct(
        protected array $config = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function configValidationRules(): array
    {
        return [
            'secret_key' => ['required', 'string', 'regex:/^sk_(test|live)_.+$/', 'max:255'],
            'public_key' => ['required', 'string', 'regex:/^pk_(test|live)_.+$/', 'max:255'],
            'webhook_secret' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function getRequiredConfigKeys(): array
    {
        return ['secret_key', 'public_key', 'webhook_secret'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPayment(array $data): array
    {
        $gatewayCode = trim((string) ($data['gateway_code'] ?? ''));
        $paymentMethodType = self::PAYMENT_METHOD_TYPES[$gatewayCode] ?? null;

        if ($paymentMethodType === null) {
            throw new GatewayException('Unsupported PayMongo payment method.');
        }

        $secretKey = $this->secretKey();
        $publicKey = $this->publicKey();
        $this->assertMatchingKeyModes($secretKey, $publicKey);
        $reference = trim((string) ($data['reference'] ?? ''));
        $currency = strtoupper(trim((string) ($data['currency'] ?? 'PHP')));

        if ($reference === '' || $currency !== 'PHP') {
            throw new GatewayException('PayMongo payment requires a reference and PHP currency.');
        }

        $amountInCentavos = $this->amountInCentavos($data['amount'] ?? 0);
        if ($amountInCentavos < 100) {
            throw new GatewayException('PayMongo payments must be at least PHP 1.00.');
        }

        if ($gatewayCode !== 'qrph' && $amountInCentavos > 10000000) {
            throw new GatewayException('PayMongo e-wallet payments cannot exceed PHP 100,000.00.');
        }

        $expirySeconds = $this->expirySeconds($gatewayCode, $data['qr_expiry_seconds'] ?? null);
        $intentResponse = $this->postJson(
            self::PAYMENT_INTENTS_PATH,
            [
                'data' => [
                    'attributes' => [
                        'amount' => $amountInCentavos,
                        'currency' => $currency,
                        'payment_method_allowed' => [$paymentMethodType],
                        'description' => 'KemoPay '.$reference,
                    ],
                ],
            ],
            $secretKey,
            $this->idempotencyKey($gatewayCode, $reference, 'payment-intent')
        );

        $intentId = data_get($intentResponse, 'data.id');
        $clientKey = data_get($intentResponse, 'data.attributes.client_key');
        if (! is_string($intentId) || $intentId === '' || ! is_string($clientKey) || $clientKey === '') {
            throw new GatewayException('PayMongo API error: payment intent response was incomplete.');
        }

        $paymentMethodAttributes = ['type' => $paymentMethodType];
        if ($gatewayCode === 'qrph') {
            $paymentMethodAttributes['expiry_seconds'] = $expirySeconds;
        }

        $paymentMethodResponse = $this->postJson(
            self::PAYMENT_METHODS_PATH,
            ['data' => ['attributes' => $paymentMethodAttributes]],
            $publicKey,
            $this->idempotencyKey($gatewayCode, $reference, 'payment-method')
        );

        $paymentMethodId = data_get($paymentMethodResponse, 'data.id');
        if (! is_string($paymentMethodId) || $paymentMethodId === '') {
            throw new GatewayException('PayMongo API error: payment method response was incomplete.');
        }

        $attachAttributes = [
            'payment_method' => $paymentMethodId,
            'client_key' => $clientKey,
        ];
        if ($gatewayCode !== 'qrph') {
            $attachAttributes['return_url'] = route('payment.default', ['transaction' => $data['payment_id']], absolute: true);
            if ($gatewayCode === 'shopeepay') {
                $attachAttributes['payment_method_options'] = [
                    'shopeepay' => ['expiry_seconds' => $expirySeconds],
                ];
            }
        }

        $attachedResponse = $this->postJson(
            self::PAYMENT_INTENTS_PATH.'/'.rawurlencode($intentId).'/attach',
            ['data' => ['attributes' => $attachAttributes]],
            $publicKey,
            $this->idempotencyKey($gatewayCode, $reference, 'attach-payment-method')
        );

        $attachedIntentId = data_get($attachedResponse, 'data.id');
        if (! is_string($attachedIntentId) || ! hash_equals($intentId, $attachedIntentId)) {
            throw new GatewayException('PayMongo API error: attached payment intent response did not match the request.');
        }

        $responseData = [
            'external_payment_id' => $intentId,
            'provider_reference' => $intentId,
            'expires_at' => now()->addSeconds($expirySeconds)->toIso8601String(),
            'raw' => [
                'payment_intent' => $intentResponse,
                'payment_method' => $paymentMethodResponse,
                'attached_intent' => $attachedResponse,
                'payment_method_code' => $gatewayCode,
            ],
        ];

        if ($gatewayCode === 'qrph') {
            $qrImage = data_get($attachedResponse, 'data.attributes.next_action.code.image_url');
            if (! is_string($qrImage) || $qrImage === '') {
                throw new GatewayException('PayMongo API error: QR Ph image missing from payment intent response.');
            }

            $responseData['qr_data'] = $qrImage;
            $responseData['qr_image'] = $qrImage;
            $responseData['raw']['qr_image'] = $qrImage;

            return $responseData;
        }

        $redirectUrl = data_get($attachedResponse, 'data.attributes.next_action.redirect.url');
        if (! is_string($redirectUrl) || $redirectUrl === '') {
            throw new GatewayException('PayMongo API error: e-wallet redirect URL missing from payment intent response.');
        }

        $responseData['redirect_url'] = $redirectUrl;
        $responseData['raw']['redirect_url'] = $redirectUrl;

        return $responseData;
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = trim((string) ($this->config['webhook_secret'] ?? ''));
        if ($secret === '') {
            return false;
        }

        $rawBody = (string) $request->getContent();
        $payload = json_decode($rawBody, true);
        $livemode = data_get($payload, 'data.attributes.livemode');
        if (! is_bool($livemode)) {
            return false;
        }

        return app(PayMongoWebhookSignatureVerifier::class)->verify(
            $rawBody,
            $request->header('Paymongo-Signature'),
            $secret,
            $livemode
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getPaymentStatus(string $reference): array
    {
        $secretKey = $this->secretKey();

        try {
            /** @var Response $response */
            $response = Http::withBasicAuth($secretKey, '')
                ->connectTimeout(self::HTTP_CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->acceptJson()
                ->get(self::PAYMENT_INTENT_STATUS_API_BASE_URL.self::PAYMENT_INTENTS_PATH.'/'.rawurlencode($reference));

            if ($this->isStatusRouteUnavailable($response)) {
                $response = Http::withBasicAuth($secretKey, '')
                    ->connectTimeout(self::HTTP_CONNECT_TIMEOUT_SECONDS)
                    ->timeout(self::HTTP_TIMEOUT_SECONDS)
                    ->acceptJson()
                    ->get(self::LEGACY_PAYMENT_INTENT_STATUS_API_BASE_URL.self::PAYMENT_INTENTS_PATH.'/'.rawurlencode($reference));
            }
        } catch (HttpClientException $e) {
            throw new GatewayException('PayMongo status request failed: '.$e->getMessage(), 0, $e);
        }

        return $this->responseBodyOrFail($response);
    }

    private function isStatusRouteUnavailable(Response $response): bool
    {
        if ($response->status() !== 404) {
            return false;
        }

        $message = $response->json('message') ?? $response->json('errors.0.detail');

        return is_string($message) && strtolower(trim($message)) === 'the requested route does not exist';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postJson(string $path, array $payload, string $secretKey, string $idempotencyKey): array
    {
        try {
            /** @var Response $response */
            $response = Http::withBasicAuth($secretKey, '')
                ->withHeader('Idempotency-Key', $idempotencyKey)
                ->connectTimeout(self::HTTP_CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->acceptJson()
                ->asJson()
                ->post(self::API_BASE_URL.$path, $payload);
        } catch (HttpClientException $e) {
            throw new GatewayException('PayMongo API request failed: '.$e->getMessage(), 0, $e);
        }

        return $this->responseBodyOrFail($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function responseBodyOrFail(Response $response): array
    {
        $body = $response->json();
        if (! is_array($body)) {
            $body = [];
        }

        if (! $response->successful()) {
            if (data_get($body, 'errors.0.code') === 'payment_method_not_allowed') {
                throw new GatewayException(
                    "This payment method is not enabled for KemoPay's PayMongo account. Activate it in PayMongo Settings > Payment Methods."
                );
            }

            $message = data_get($body, 'errors.0.detail')
                ?? data_get($body, 'errors.0.code')
                ?? data_get($body, 'message')
                ?? 'PayMongo API returned an error.';

            throw new GatewayException('PayMongo API error: '.(is_string($message) ? $message : 'Request failed.'));
        }

        return $body;
    }

    private function secretKey(): string
    {
        $secretKey = trim((string) ($this->config['secret_key'] ?? ''));
        if ($secretKey === '') {
            throw new GatewayException('PayMongo credentials are incomplete. Configure the secret_key.');
        }

        return $secretKey;
    }

    private function publicKey(): string
    {
        $publicKey = trim((string) ($this->config['public_key'] ?? ''));
        if ($publicKey === '') {
            throw new GatewayException('PayMongo credentials are incomplete. Configure the public_key.');
        }

        return $publicKey;
    }

    private function assertMatchingKeyModes(string $secretKey, string $publicKey): void
    {
        preg_match('/^sk_(test|live)_/', $secretKey, $secretMatches);
        preg_match('/^pk_(test|live)_/', $publicKey, $publicMatches);

        if (($secretMatches[1] ?? null) === null || ($publicMatches[1] ?? null) === null) {
            throw new GatewayException('PayMongo API keys must use valid test or live key prefixes.');
        }

        if ($secretMatches[1] !== $publicMatches[1]) {
            throw new GatewayException('PayMongo secret and public API keys must use the same mode.');
        }
    }

    private function amountInCentavos(mixed $amount): int
    {
        $amountString = match (true) {
            is_string($amount) => trim($amount),
            is_int($amount) => (string) $amount,
            is_float($amount) => json_encode($amount, JSON_PRESERVE_ZERO_FRACTION) ?: '',
            default => '',
        };

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amountString, $matches)) {
            throw new GatewayException('PayMongo amounts must be PHP values with no more than two decimal places.');
        }

        $wholeAmount = ltrim($matches[1], '0');
        $wholeAmount = $wholeAmount === '' ? '0' : $wholeAmount;
        $fractionalAmount = str_pad($matches[2] ?? '', 2, '0');
        $centavos = $wholeAmount.$fractionalAmount;

        if (strlen($centavos) > strlen((string) PHP_INT_MAX)
            || (strlen($centavos) === strlen((string) PHP_INT_MAX) && strcmp($centavos, (string) PHP_INT_MAX) > 0)) {
            throw new GatewayException('PayMongo amount is outside the supported range.');
        }

        return (int) $centavos;
    }

    private function expirySeconds(string $gatewayCode, $requestedQrExpirySeconds = null): int
    {
        if ($gatewayCode === 'qrph') {
            $expirySeconds = $requestedQrExpirySeconds !== null
                ? (int) $requestedQrExpirySeconds
                : (int) ($this->config['expiry_seconds'] ?? self::DEFAULT_QRPH_EXPIRY_SECONDS);
            if ($expirySeconds < 60 || $expirySeconds > 9000) {
                throw new GatewayException('PayMongo QR Ph expiry_seconds must be between 60 and 9000.');
            }

            return $expirySeconds;
        }

        $expirySeconds = $gatewayCode === 'shopeepay'
            ? (int) ($this->config['shopeepay_expiry_seconds'] ?? self::PAYMENT_METHOD_EXPIRY_SECONDS[$gatewayCode])
            : self::PAYMENT_METHOD_EXPIRY_SECONDS[$gatewayCode];

        if ($gatewayCode === 'shopeepay' && ($expirySeconds < 1 || $expirySeconds > 3600)) {
            throw new GatewayException('PayMongo ShopeePay expiry_seconds must be between 1 and 3600.');
        }

        return $expirySeconds;
    }

    private function idempotencyKey(string $gatewayCode, string $reference, string $operation): string
    {
        return hash('sha256', 'kemopay:paymongo:'.$gatewayCode.':'.$reference.':'.$operation);
    }
}
