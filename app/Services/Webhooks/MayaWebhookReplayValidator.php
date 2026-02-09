<?php

namespace App\Services\Webhooks;

use App\Services\Webhooks\Contracts\WebhookReplayValidatorInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Validates Maya webhook timestamps for replay protection.
 *
 * Extracts from createdAt or updatedAt (ISO 8601 or Unix timestamp).
 */
class MayaWebhookReplayValidator implements WebhookReplayValidatorInterface
{
    public function isValid(Request $request, array $payload): bool
    {
        $timestamp = $this->extractTimestamp($payload);
        if ($timestamp === null) {
            Log::warning('Maya webhook: replay protection failed — timestamp missing or invalid', [
                'id' => $payload['id'] ?? null,
            ]);

            return false;
        }

        $maxAge = config('maya.webhook.max_age', 300);
        $now = (int) floor(microtime(true));
        $diff = abs($now - $timestamp);

        if ($diff > $maxAge) {
            Log::warning('Maya webhook: replay protection failed — timestamp outside allowed window', [
                'id' => $payload['id'] ?? null,
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
     * @param  array<string, mixed>  $payload
     */
    private function extractTimestamp(array $payload): ?int
    {
        $value = $payload['updatedAt'] ?? $payload['createdAt'] ?? null;
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            $ts = (int) $value;
            return $ts > 0 ? $ts : null;
        }
        if (is_string($value)) {
            $ts = strtotime($value);
            return $ts !== false ? $ts : null;
        }

        return null;
    }
}
