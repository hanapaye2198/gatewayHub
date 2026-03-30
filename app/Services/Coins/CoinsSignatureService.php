<?php

namespace App\Services\Coins;

use App\Services\Gateways\Exceptions\CoinsApiException;

/**
 * Generates and verifies HMAC-SHA256 request signatures for Coins.ph OpenAPI.
 *
 * Follows Coins Partner Integration Guide v2.5:
 * 1. Collect all request parameters (query, body, and required timestamp).
 * 2. Exclude headers from signing except timestamp when passed as a parameter.
 * 3. Sort parameters by key in lexicographical (ASCII) order.
 * 4. Build canonical string: key1=value1&key2=value2&key3=value3 (all values stringified).
 * 5. Compute HMAC-SHA256 with API secret; output lowercase hexadecimal.
 *
 * For Fiat API (generate_qr_code): use signForFiatRequest() with body params + timestamp;
 * signature is over body canonical string with timestamp appended (not in query string).
 *
 * Use for API request signing and webhook signature verification.
 * Do not log apiSecret or expose canonical_string outside debug mode.
 */
class CoinsSignatureService
{
    /**
     * Sign body for Coins Fiat API (POST JSON body, signature in header).
     *
     * Canonical string = body params sorted by key, then "&timestamp={timestamp}".
     * Signature = HMAC-SHA256(canonical_string, clientSecret) as lowercase hex.
     *
     * @param  array<string, mixed>  $bodyParams  Request body parameters only (no timestamp, no signature).
     * @param  string  $timestampMs  Timestamp in milliseconds (string).
     * @param  string  $clientSecret  Coins API client secret.
     * @param  bool  $includeCanonicalForDebug  When true, return canonical_string. Do not use in production logs.
     * @param  bool  $sortKeys  When true, sort body keys lexicographically before signing.
     * @return array{signature: string, canonical_string?: string}
     *
     * @throws CoinsApiException When clientSecret is empty.
     */
    public function signForFiatRequest(
        array $bodyParams,
        string $timestampMs,
        string $clientSecret,
        bool $includeCanonicalForDebug = false,
        bool $sortKeys = true
    ): array {
        if ($clientSecret === '') {
            throw new CoinsApiException('Coins signature requires a non-empty API secret.');
        }

        $normalized = $this->normalizeParams($bodyParams);
        $canonicalBody = $this->buildCanonicalString($normalized, $sortKeys);
        $canonical = $canonicalBody === ''
            ? 'timestamp='.$timestampMs
            : $canonicalBody.'&timestamp='.$timestampMs;

        $signature = hash_hmac('sha256', $canonical, $clientSecret);

        $result = ['signature' => $signature];

        if ($includeCanonicalForDebug) {
            $result['canonical_string'] = $canonical;
        }

        return $result;
    }

    /**
     * Sign parameters for Coins.ph API payloads that require a timestamp.
     *
     * Auto-injects timestamp (milliseconds since epoch) if not present.
     * Timestamp can be injected for deterministic testing.
     *
     * @param  array<string, mixed>  $params  All parameters to sign (query + body). Values are stringified.
     * @param  string  $apiSecret  Coins API/webhook secret. Must not be empty.
     * @param  int|null  $timestamp  Optional fixed timestamp (ms) for testing. Omit to use current time.
     * @param  bool  $includeCanonicalForDebug  When true, return canonical_string. Do not use in production logs.
     * @return array{signature: string, timestamp: string, canonical_string?: string}
     *
     * @throws CoinsApiException When apiSecret is empty or params are empty after normalization.
     */
    public function sign(
        array $params,
        string $apiSecret,
        ?int $timestamp = null,
        bool $includeCanonicalForDebug = false
    ): array {
        if ($apiSecret === '') {
            throw new CoinsApiException('Coins signature requires a non-empty API secret.');
        }

        $normalized = $this->normalizeParams($params);

        if (! array_key_exists('timestamp', $normalized)) {
            $ts = $timestamp ?? (int) (microtime(true) * 1000);
            $normalized['timestamp'] = (string) $ts;
        }

        if ($normalized === []) {
            throw new CoinsApiException('Coins signature requires at least one parameter (e.g. timestamp).');
        }

        $canonical = $this->buildCanonicalString($normalized);
        $signature = hash_hmac('sha256', $canonical, $apiSecret);

        $result = [
            'signature' => $signature,
            'timestamp' => $normalized['timestamp'],
        ];

        if ($includeCanonicalForDebug) {
            $result['canonical_string'] = $canonical;
        }

        return $result;
    }

    /**
     * Verify a timestamped Coins payload signature.
     *
     * Computes expected signature from payload and compares with received value using timing-safe comparison.
     *
     * @param  array<string, mixed>  $payload  Request body/query parameters (must include timestamp).
     * @param  string  $apiSecret  Webhook or API secret.
     * @param  string  $receivedSignature  Signature from header or payload.
     * @return bool True if signature is valid.
     *
     * @throws CoinsApiException When apiSecret is empty, payload is empty, or signature does not match.
     */
    public function verify(
        array $payload,
        string $apiSecret,
        string $receivedSignature
    ): bool {
        if ($apiSecret === '') {
            throw new CoinsApiException('Coins signature verification requires a non-empty API secret.');
        }

        $normalized = $this->normalizeParams($payload);
        if ($normalized === []) {
            throw new CoinsApiException('Coins signature verification requires a non-empty payload.');
        }

        if (! array_key_exists('timestamp', $normalized)) {
            throw new CoinsApiException('Coins signature verification requires timestamp in payload.');
        }

        $canonical = $this->buildCanonicalString($normalized);
        $expectedSignature = hash_hmac('sha256', $canonical, $apiSecret);

        if (! $this->signaturesMatch($expectedSignature, trim($receivedSignature))) {
            throw new CoinsApiException('Coins signature verification failed: signature mismatch.');
        }

        return true;
    }

    /**
     * Sign a Coins webhook payload exactly as documented in the partner guide.
     *
     * Webhook payloads are signed over the JSON body parameters only, sorted
     * lexicographically, without injecting a timestamp parameter.
     *
     * @param  array<string, mixed>  $payload
     * @return array{signature: string, canonical_string?: string}
     */
    public function signWebhook(
        array $payload,
        string $apiSecret,
        bool $includeCanonicalForDebug = false,
        bool $sortKeys = true
    ): array {
        if ($apiSecret === '') {
            throw new CoinsApiException('Coins signature requires a non-empty API secret.');
        }

        $normalized = $this->normalizeParams($payload);
        if ($normalized === []) {
            throw new CoinsApiException('Coins webhook signature requires a non-empty payload.');
        }

        $canonical = $this->buildCanonicalString($normalized, $sortKeys);
        $result = [
            'signature' => hash_hmac('sha256', $canonical, $apiSecret),
        ];

        if ($includeCanonicalForDebug) {
            $result['canonical_string'] = $canonical;
        }

        return $result;
    }

    /**
     * Sign a raw webhook payload body exactly as transmitted.
     *
     * @return array{signature: string, canonical_string?: string}
     */
    public function signRawPayload(
        string $rawPayload,
        string $apiSecret,
        bool $includeCanonicalForDebug = false
    ): array {
        if ($apiSecret === '') {
            throw new CoinsApiException('Coins signature requires a non-empty API secret.');
        }

        if ($rawPayload === '') {
            throw new CoinsApiException('Coins raw payload signature requires a non-empty payload.');
        }

        $result = [
            'signature' => hash_hmac('sha256', $rawPayload, $apiSecret),
        ];

        if ($includeCanonicalForDebug) {
            $result['canonical_string'] = $rawPayload;
        }

        return $result;
    }

    /**
     * Verify a Coins webhook payload signature.
     *
     * The guide documents webhook signing over the callback body parameters
     * themselves, sorted lexicographically, without a required timestamp field.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyWebhook(
        array $payload,
        string $apiSecret,
        string $receivedSignature,
        ?string $rawPayload = null
    ): bool {
        if ($apiSecret === '') {
            throw new CoinsApiException('Coins signature verification requires a non-empty API secret.');
        }

        $normalized = $this->normalizeParams($payload);
        if ($normalized === []) {
            throw new CoinsApiException('Coins signature verification requires a non-empty payload.');
        }

        $receivedSignature = trim($receivedSignature);
        foreach ($this->webhookCanonicalCandidates($normalized, $rawPayload) as $canonical) {
            $expectedSignature = hash_hmac('sha256', $canonical, $apiSecret);
            if ($this->signaturesMatch($expectedSignature, $receivedSignature)) {
                return true;
            }
        }

        throw new CoinsApiException('Coins signature verification failed: signature mismatch.');
    }

    /**
     * Normalize parameters: string keys and string values only, no headers.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, string>
     */
    private function normalizeParams(array $params): array
    {
        $out = [];
        foreach ($params as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            $out[$key] = $this->stringifyValue($value);
        }

        return $out;
    }

    private function stringifyValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Build canonical string per Coins Partner Integration Guide: keys sorted lexicographically, key=value joined by &.
     *
     * @param  array<string, string>  $params
     */
    private function buildCanonicalString(array $params, bool $sortKeys = true): string
    {
        if ($sortKeys) {
            ksort($params, SORT_STRING);
        }

        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        return implode('&', $pairs);
    }

    /**
     * @param  array<string, string>  $normalized
     * @return list<string>
     */
    private function webhookCanonicalCandidates(array $normalized, ?string $rawPayload): array
    {
        $candidates = [
            $this->buildCanonicalString($normalized, true),
            $this->buildCanonicalString($normalized, false),
        ];

        if (is_string($rawPayload) && $rawPayload !== '') {
            $candidates[] = $rawPayload;
        }

        return array_values(array_unique($candidates));
    }

    private function signaturesMatch(string $expectedSignature, string $receivedSignature): bool
    {
        if ($this->looksLikeHexDigest($expectedSignature) && $this->looksLikeHexDigest($receivedSignature)) {
            return hash_equals(strtolower($expectedSignature), strtolower($receivedSignature));
        }

        return hash_equals($expectedSignature, $receivedSignature);
    }

    private function looksLikeHexDigest(string $signature): bool
    {
        return preg_match('/\A[0-9a-fA-F]{64}\z/', $signature) === 1;
    }
}
