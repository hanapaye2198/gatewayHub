<?php

namespace App\Services\Coins;

use App\Services\Webhooks\Contracts\WebhookReplayValidatorInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Validates webhook timestamps to prevent replay attacks.
 *
 * Rejects webhooks whose timestamp is outside the configured time window
 * (too old or too far in the future).
 */
class CoinsWebhookReplayValidator implements WebhookReplayValidatorInterface
{
    /**
     * Validate that the webhook timestamp is within the allowed age window.
     * Returns false when the request should be rejected (caller should respond 401).
     *
     * @param  array<string, mixed>  $payload  Webhook JSON payload.
     */
    public function isValid(Request $request, array $payload): bool
    {
        $timestamp = $this->extractTimestamp($request, $payload);
        if ($timestamp === null) {
            Log::warning('Coins webhook: replay protection failed — timestamp missing or invalid', [
                'referenceId' => $payload['referenceId'] ?? null,
            ]);

            return false;
        }

        $maxAge = config('coins.webhook.max_age', 300);
        $now = (int) floor(microtime(true));
        $diff = abs($now - $timestamp);

        if ($diff > $maxAge) {
            Log::warning('Coins webhook: replay protection failed — timestamp outside allowed window', [
                'referenceId' => $payload['referenceId'] ?? null,
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
     * Extract Unix timestamp (seconds) from payload or configurable header.
     * Coins.ph uses milliseconds in payload; supports both ms and seconds.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractTimestamp(Request $request, array $payload): ?int
    {
        $value = $payload['timestamp'] ?? null;
        if ($value === null) {
            $header = config('coins.webhook.timestamp_header');
            $value = is_string($header) && $header !== '' ? $request->header($header) : null;
        }

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
