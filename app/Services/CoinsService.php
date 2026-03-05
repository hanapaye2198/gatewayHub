<?php

namespace App\Services;

use App\Services\Coins\CoinsSignatureService;
use App\Services\Gateways\Exceptions\CoinsApiException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;

/**
 * Coins.ph Dynamic QR (Fiat) API client.
 *
 * Uses config only (no env() in service): config('coins.base_url'), config('coins.api_key'),
 * config('coins.secret_key'), config('coins.source'). Sends POST with JSON body and
 * header-based HMAC-SHA256 signature (params sorted alphabetically, query string format).
 */
class CoinsService
{
    private const GENERATE_QR_PATH = '/openapi/fiat/v1/generate_qr_code';

    private const DEFAULT_EXPIRATION_SECONDS = 1800;

    private const SIGNATURE_INVALID_CODE = -1022;

    public const ERROR_CODE_IP_NOT_WHITELISTED = 1006;

    public function __construct(
        protected CoinsSignatureService $signatureService
    ) {}

    /**
     * Call Coins generate_qr_code API and return decoded response.
     *
     * @param  array{amount: float|int|string, requestId: string, currency?: string}  $params
     * @return array{success: bool, qr_code_string: string|null, reference_id: string|null, raw: array<string, mixed>}
     *
     * @throws CoinsApiException On request failure or API error (non-2xx or status !== 0).
     */
    public function generateDynamicQr(array $params): array
    {
        $requestId = (string) ($params['requestId'] ?? '');
        $amount = $params['amount'] ?? 0;
        $currency = (string) ($params['currency'] ?? 'PHP');

        $baseUrl = config('coins.base_url');
        $apiKey = config('coins.api_key');
        $secretKey = config('coins.secret_key');
        $source = config('coins.source', 'GATEWAYHUB');

        if ($apiKey === '' || $secretKey === '') {
            throw new CoinsApiException('Coins API is not configured: set COINS_API_KEY and COINS_SECRET_KEY.');
        }

        $bodyParams = [
            'requestId' => $requestId,
            'amount' => (string) $amount,
            'currency' => $currency,
            'expiredSeconds' => (string) self::DEFAULT_EXPIRATION_SECONDS,
            'source' => $source,
        ];

        [$response, $body] = $this->sendGenerateQrRequest($baseUrl, $apiKey, $secretKey, $bodyParams, true);

        if ($this->isSignatureInvalidResponse($response, $body)) {
            [$response, $body] = $this->sendGenerateQrRequest($baseUrl, $apiKey, $secretKey, $bodyParams, false);
        }

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
     * @param  array<string, string>  $bodyParams
     * @return array{0: \Illuminate\Http\Client\Response, 1: array<string, mixed>}
     */
    private function sendGenerateQrRequest(
        string $baseUrl,
        string $apiKey,
        string $secretKey,
        array $bodyParams,
        bool $sortKeys
    ): array {
        $timestampMs = (string) (int) (microtime(true) * 1000);
        $signed = $this->signatureService->signForFiatRequest(
            $bodyParams,
            $timestampMs,
            $secretKey,
            false,
            $sortKeys
        );

        $headers = [
            'X-COINS-APIKEY' => $apiKey,
            'Timestamp' => $timestampMs,
            'Signature' => $signed['signature'],
            'Content-Type' => 'application/json',
        ];

        $url = $baseUrl.self::GENERATE_QR_PATH;

        try {
            $response = Http::withHeaders($headers)->post($url, $bodyParams);
        } catch (HttpClientException $e) {
            throw new CoinsApiException(
                'Coins API request failed: '.$e->getMessage(),
                null,
                null,
                $e
            );
        }

        $body = $response->json();
        if (! is_array($body)) {
            $body = [];
        }

        return [$response, $body];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function throwIfResponseHasError(\Illuminate\Http\Client\Response $response, array $body): void
    {
        if (! $response->successful()) {
            throw new CoinsApiException(
                'Coins API error: '.$this->extractResponseMessage($body, $response),
                $response->status(),
                $body
            );
        }

        $status = $body['status'] ?? $body['code'] ?? null;
        if ($status !== null && (int) $status !== 0) {
            if ((int) $status === self::ERROR_CODE_IP_NOT_WHITELISTED) {
                throw new CoinsApiException(
                    'IP not whitelisted. Please contact Coins to whitelist server IP.',
                    $response->status(),
                    $body
                );
            }

            throw new CoinsApiException(
                'Coins API error: '.$this->extractResponseMessage($body, $response, $status),
                $response->status(),
                $body
            );
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function isSignatureInvalidResponse(\Illuminate\Http\Client\Response $response, array $body): bool
    {
        if ($response->successful()) {
            $status = $body['status'] ?? $body['code'] ?? null;
            if ($status !== null && (int) $status === self::SIGNATURE_INVALID_CODE) {
                return true;
            }
        }

        $message = strtolower($this->extractResponseMessage($body, $response));

        return str_contains($message, 'signature') && str_contains($message, 'not valid');
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractResponseMessage(
        array $body,
        \Illuminate\Http\Client\Response $response,
        mixed $status = null
    ): string {
        $message = $body['msg'] ?? $body['message'] ?? null;
        if (is_array($message)) {
            $message = json_encode($message);
        }
        if (is_string($message) && $message !== '') {
            return $message;
        }
        if ($status !== null) {
            return 'API returned error status '.$status;
        }

        $responseBody = $response->body();

        return $responseBody !== '' ? $responseBody : 'Unknown error';
    }
}
