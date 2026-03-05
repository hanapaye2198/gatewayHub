<?php

namespace App\Services\Coins;

use App\Services\Gateways\Exceptions\CoinsApiException;

class CoinsGenerateQrSigner
{
    public const STRATEGY_RAW_JSON = 'raw_json';

    public const STRATEGY_KV_SORTED_WITH_TIMESTAMP = 'kv_sorted_with_timestamp';

    public const STRATEGY_KV_INPUT_ORDER_WITH_TIMESTAMP = 'kv_input_order_with_timestamp';

    /**
     * @param  array<string, mixed>  $body
     * @return array{signature: string, canonical: string}
     */
    public function sign(
        array $body,
        string $secret,
        string $timestamp,
        string $strategy,
        ?string $jsonBody = null
    ): array {
        if ($secret === '') {
            throw new CoinsApiException('Coins signature requires a non-empty API secret.');
        }

        if ($timestamp === '') {
            throw new CoinsApiException('Coins signature requires a non-empty timestamp.');
        }

        $canonical = match ($strategy) {
            self::STRATEGY_RAW_JSON => $jsonBody ?? $this->encodeJsonBody($body),
            self::STRATEGY_KV_SORTED_WITH_TIMESTAMP => $this->buildCanonicalKeyValuePayload($body, $timestamp, true),
            self::STRATEGY_KV_INPUT_ORDER_WITH_TIMESTAMP => $this->buildCanonicalKeyValuePayload($body, $timestamp, false),
            default => throw new CoinsApiException('Unsupported Coins signature strategy: '.$strategy),
        };

        return [
            'signature' => hash_hmac('sha256', $canonical, $secret),
            'canonical' => $canonical,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function encodeJsonBody(array $body): string
    {
        $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES);
        if (! is_string($jsonBody)) {
            throw new CoinsApiException('Coins request body encoding failed.');
        }

        return $jsonBody;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function buildCanonicalKeyValuePayload(array $body, string $timestamp, bool $sortKeys): string
    {
        $normalized = [];
        foreach ($body as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            $normalized[$key] = $this->stringifyValue($value);
        }

        if ($sortKeys) {
            ksort($normalized, SORT_STRING);
        }

        $pairs = [];
        foreach ($normalized as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        $canonicalBody = implode('&', $pairs);

        return $canonicalBody === ''
            ? 'timestamp='.$timestamp
            : $canonicalBody.'&timestamp='.$timestamp;
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
            $json = json_encode($value, JSON_UNESCAPED_SLASHES);

            return is_string($json) ? $json : (string) $value;
        }

        return (string) $value;
    }
}
