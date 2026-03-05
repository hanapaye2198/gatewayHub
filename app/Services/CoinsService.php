<?php

namespace App\Services;

use App\Services\Coins\CoinsGenerateQrRequestExecutor;
use App\Services\Gateways\Exceptions\CoinsApiException;

/**
 * Coins.ph Dynamic QR (Fiat) API client.
 *
 * Uses config only (no env() in service): config('coins.base_url'), config('coins.api_key'),
 * config('coins.secret_key'), config('coins.source').
 * Signing strategy and timestamp behavior are controlled via config('coins.auth.generate_qr.*').
 */
class CoinsService
{
    private const GENERATE_QR_PATH = '/openapi/fiat/v1/generate_qr_code';

    private const DEFAULT_EXPIRATION_SECONDS = 1800;

    public const ERROR_CODE_IP_NOT_WHITELISTED = 1006;

    protected CoinsGenerateQrRequestExecutor $generateQrRequestExecutor;

    public function __construct(?CoinsGenerateQrRequestExecutor $generateQrRequestExecutor = null)
    {
        $this->generateQrRequestExecutor = $generateQrRequestExecutor ?? new CoinsGenerateQrRequestExecutor;
    }

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

        $baseUrl = rtrim((string) config('coins.base_url', ''), '/');
        $apiKey = trim((string) config('coins.api_key', ''));
        $secretKey = trim((string) config('coins.secret_key', ''));
        $source = (string) config('coins.source', 'GATEWAYHUB');

        if ($baseUrl === '') {
            throw new CoinsApiException('Coins API is not configured: set COINS_BASE_URL.');
        }

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
    private function extractResponseMessage(
        array $body,
        \Illuminate\Http\Client\Response $response,
        mixed $status = null
    ): string {
        $data = $body['data'] ?? null;
        $candidates = [
            $body['msg'] ?? null,
            $body['message'] ?? null,
            $body['error'] ?? null,
            $body['errorMsg'] ?? null,
            is_array($data) ? ($data['errorMsg'] ?? null) : null,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                $candidate = json_encode($candidate);
            }

            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }
        if ($status !== null) {
            return 'API returned error status '.$status;
        }

        $responseBody = $response->body();

        return $responseBody !== '' ? $responseBody : 'Unknown error';
    }
}
