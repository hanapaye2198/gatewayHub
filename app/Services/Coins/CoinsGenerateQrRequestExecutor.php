<?php

namespace App\Services\Coins;

use App\Services\Gateways\Exceptions\CoinsApiException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CoinsGenerateQrRequestExecutor
{
    private const SIGNATURE_INVALID_CODE = -1022;

    private const STRATEGY_AUTO = 'auto';

    public function __construct(
        private readonly ?CoinsGenerateQrSigner $signer = null
    ) {}

    /**
     * @param  array<string, mixed>  $bodyParams
     * @param  array{api_base?: string, endpoint?: string, request_id?: string, include_canonical_for_debug?: bool, strategy?: string, timestamp_unit?: string, signature_encoding?: string, max_attempts?: int}  $context
     * @return array{response: Response, body: array<string, mixed>, winning_strategy: string|null, winning_timestamp_unit: string|null, attempt_count: int}
     */
    public function execute(
        string $url,
        string $apiKey,
        string $apiSecret,
        array $bodyParams,
        array $context = []
    ): array {
        $signer = $this->signer ?? new CoinsGenerateQrSigner;
        $jsonBody = $signer->encodeJsonBody($bodyParams);
        $includeCanonicalForDebug = (bool) ($context['include_canonical_for_debug'] ?? false);

        $strategy = $this->normalizeStrategy(
            (string) ($context['strategy'] ?? config('coins.auth.generate_qr.strategy', self::STRATEGY_AUTO))
        );
        $timestampUnit = $this->normalizeTimestampUnit(
            (string) ($context['timestamp_unit'] ?? config('coins.auth.generate_qr.timestamp_unit', 'milliseconds'))
        );
        $signatureEncoding = $this->normalizeSignatureEncoding(
            (string) ($context['signature_encoding'] ?? config('coins.auth.generate_qr.signature_encoding', 'hex_lower'))
        );
        $maxAttempts = $this->normalizeMaxAttempts(
            (int) ($context['max_attempts'] ?? config('coins.auth.generate_qr.max_attempts', 4))
        );
        $profiles = $this->buildAttemptProfiles($strategy, $timestampUnit, $maxAttempts);

        $baseInstantMs = (string) (int) floor(microtime(true) * 1000);
        $baseInstantSeconds = (string) (int) floor(((int) $baseInstantMs) / 1000);
        $apiBase = (string) ($context['api_base'] ?? '');
        $endpoint = (string) ($context['endpoint'] ?? 'generate_qr_code');
        $requestId = (string) ($context['request_id'] ?? ($bodyParams['requestId'] ?? ''));

        $lastResponse = null;
        $lastBody = [];
        $attemptCount = 0;

        foreach ($profiles as $profile) {
            $attemptCount++;
            $attemptStrategy = $profile['strategy'];
            $attemptTimestampUnit = $profile['timestamp_unit'];
            $timestamp = $attemptTimestampUnit === 'seconds' ? $baseInstantSeconds : $baseInstantMs;

            $signed = $signer->sign($bodyParams, $apiSecret, $timestamp, $attemptStrategy, $jsonBody);

            $headers = [
                'X-COINS-APIKEY' => $apiKey,
                'Timestamp' => $timestamp,
                'Signature' => $signed['signature'],
                'Content-Type' => 'application/json',
            ];

            if ($includeCanonicalForDebug) {
                $headers['X-COINS-DEBUG-CANONICAL'] = $signed['canonical'];
            }

            try {
                $response = Http::withHeaders($headers)
                    ->withBody($jsonBody, 'application/json')
                    ->post($url);
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

            $responseCode = $body['status'] ?? $body['code'] ?? null;
            $responseMessage = $this->extractResponseMessage($body, $response);

            Log::info('Coins generate_qr auth attempt', [
                'endpoint' => $endpoint,
                'strategy' => $attemptStrategy,
                'attempt' => $attemptCount,
                'timestamp_unit' => $attemptTimestampUnit,
                'timestamp_digits' => strlen($timestamp),
                'api_base' => $apiBase,
                'response_status_http' => $response->status(),
                'response_code' => $responseCode,
                'response_msg' => $responseMessage,
                'request_id' => $requestId,
                'canonical_hash' => hash('sha256', $signed['canonical']),
                'signature_encoding' => $signatureEncoding,
            ]);

            $lastResponse = $response;
            $lastBody = $body;

            if (! $this->isSignatureInvalidResponse($response, $body, $responseMessage)) {
                $status = $body['status'] ?? $body['code'] ?? null;
                if ($response->successful() && ($status === null || (int) $status === 0)) {
                    Log::info('Coins generate_qr auth success', [
                        'endpoint' => $endpoint,
                        'winning_strategy' => $attemptStrategy,
                        'winning_timestamp_unit' => $attemptTimestampUnit,
                        'attempt_count' => $attemptCount,
                        'request_id' => $requestId,
                    ]);
                }

                return [
                    'response' => $response,
                    'body' => $body,
                    'winning_strategy' => $attemptStrategy,
                    'winning_timestamp_unit' => $attemptTimestampUnit,
                    'attempt_count' => $attemptCount,
                ];
            }
        }

        if (! $lastResponse instanceof Response) {
            throw new CoinsApiException('Coins API request failed before receiving any response.');
        }

        return [
            'response' => $lastResponse,
            'body' => $lastBody,
            'winning_strategy' => null,
            'winning_timestamp_unit' => null,
            'attempt_count' => $attemptCount,
        ];
    }

    private function normalizeStrategy(string $strategy): string
    {
        $normalized = strtolower(trim($strategy));
        $supported = [
            self::STRATEGY_AUTO,
            CoinsGenerateQrSigner::STRATEGY_RAW_JSON,
            CoinsGenerateQrSigner::STRATEGY_KV_SORTED_WITH_TIMESTAMP,
            CoinsGenerateQrSigner::STRATEGY_KV_INPUT_ORDER_WITH_TIMESTAMP,
        ];

        return in_array($normalized, $supported, true) ? $normalized : self::STRATEGY_AUTO;
    }

    private function normalizeTimestampUnit(string $timestampUnit): string
    {
        $normalized = strtolower(trim($timestampUnit));

        return in_array($normalized, ['milliseconds', 'seconds'], true) ? $normalized : 'milliseconds';
    }

    private function normalizeSignatureEncoding(string $signatureEncoding): string
    {
        $normalized = strtolower(trim($signatureEncoding));

        return $normalized === 'hex_lower' ? $normalized : 'hex_lower';
    }

    private function normalizeMaxAttempts(int $maxAttempts): int
    {
        return max(1, min(4, $maxAttempts));
    }

    /**
     * @return list<array{strategy: string, timestamp_unit: string}>
     */
    private function buildAttemptProfiles(string $strategy, string $timestampUnit, int $maxAttempts): array
    {
        if ($strategy !== self::STRATEGY_AUTO) {
            return [[
                'strategy' => $strategy,
                'timestamp_unit' => $timestampUnit,
            ]];
        }

        $profiles = [
            [
                'strategy' => CoinsGenerateQrSigner::STRATEGY_RAW_JSON,
                'timestamp_unit' => 'milliseconds',
            ],
            [
                'strategy' => CoinsGenerateQrSigner::STRATEGY_KV_SORTED_WITH_TIMESTAMP,
                'timestamp_unit' => 'milliseconds',
            ],
            [
                'strategy' => CoinsGenerateQrSigner::STRATEGY_KV_INPUT_ORDER_WITH_TIMESTAMP,
                'timestamp_unit' => 'milliseconds',
            ],
            [
                'strategy' => CoinsGenerateQrSigner::STRATEGY_RAW_JSON,
                'timestamp_unit' => 'seconds',
            ],
        ];

        return array_slice($profiles, 0, $maxAttempts);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function isSignatureInvalidResponse(Response $response, array $body, string $responseMessage): bool
    {
        $status = $body['status'] ?? $body['code'] ?? null;
        if ($status !== null && (int) $status === self::SIGNATURE_INVALID_CODE) {
            return true;
        }

        $message = strtolower($responseMessage);

        return str_contains($message, 'signature') && str_contains($message, 'not valid');
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractResponseMessage(array $body, Response $response): string
    {
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

        $responseBody = $response->body();

        return $responseBody !== '' ? $responseBody : 'Unknown error';
    }
}
