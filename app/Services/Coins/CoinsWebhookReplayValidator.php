<?php

namespace App\Services\Coins;

use App\Services\Webhooks\Contracts\WebhookReplayValidatorInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Validates webhook timestamps to prevent replay attacks when a request
 * timestamp is actually provided by Coins.
 */
class CoinsWebhookReplayValidator implements WebhookReplayValidatorInterface
{
    /**
     * Validate that the webhook timestamp is within the allowed age window.
     * Returns false when the request should be rejected (caller should respond 401).
     *
     * The Coins partner guide does not require a timestamp field on webhook
     * callbacks, so missing timestamps are accepted. If Coins does send a
     * timestamp via payload or configured header, the replay window is enforced.
     *
     * @param  array<string, mixed>  $payload  Webhook JSON payload.
     */
    public function isValid(Request $request, array $payload): bool
    {
        $rawTimestamp = $this->extractTimestampValue($request, $payload);
        if ($rawTimestamp === null || $rawTimestamp === '') {
            return true;
        }

        $timestamp = $this->normalizeTimestamp($rawTimestamp);
        if ($timestamp === null) {
            Log::warning('Coins webhook: replay protection failed - timestamp invalid', [
                'referenceId' => $payload['referenceId'] ?? null,
                'requestId' => $payload['requestId'] ?? null,
            ]);

            return false;
        }

        $maxAge = config('coins.webhook.max_age', 300);
        $now = (int) floor(microtime(true));
        $diff = abs($now - $timestamp);

        if ($diff > $maxAge) {
            Log::warning('Coins webhook: replay protection failed - timestamp outside allowed window', [
                'referenceId' => $payload['referenceId'] ?? null,
                'requestId' => $payload['requestId'] ?? null,
                'payloadTimestamp' => $timestamp,
                'serverTime' => $now,
                'differenceSeconds' => $diff,
                'maxAgeSeconds' => $maxAge,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Extract raw timestamp from payload or configurable header.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractTimestampValue(Request $request, array $payload): mixed
    {
        $value = $payload['timestamp'] ?? null;
        if ($value === null) {
            $header = config('coins.webhook.timestamp_header');
            $value = is_string($header) && $header !== '' ? $request->header($header) : null;
        }

        return $value;
    }

    private function normalizeTimestamp(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $num = is_numeric($value) ? (float) $value : null;
        if ($num === null || $num <= 0) {
            return null;
        }

        if ($num > 1e12) {
            return (int) floor($num / 1000);
        }

        return (int) floor($num);
    }
}
