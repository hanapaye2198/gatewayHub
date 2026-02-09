<?php

namespace App\Services\Webhooks;

use App\Services\Webhooks\Contracts\WebhookReplayValidatorInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Validates GCash/PayMongo webhook timestamps for replay protection.
 *
 * Extracts timestamp from data.attributes.created_at (PayMongo format).
 */
class GcashWebhookReplayValidator implements WebhookReplayValidatorInterface
{
    public function isValid(Request $request, array $payload): bool
    {
        $timestamp = $this->extractTimestamp($payload);
        if ($timestamp === null) {
            Log::warning('GCash webhook: replay protection failed — timestamp missing or invalid', [
                'event_id' => $payload['data']['id'] ?? null,
            ]);

            return false;
        }

        $maxAge = config('gcash.webhook.max_age', 300);
        $now = (int) floor(microtime(true));
        $diff = abs($now - $timestamp);

        if ($diff > $maxAge) {
            Log::warning('GCash webhook: replay protection failed — timestamp outside allowed window', [
                'event_id' => $payload['data']['id'] ?? null,
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
        $attrs = $payload['data']['attributes'] ?? [];
        $value = $attrs['created_at'] ?? $attrs['updated_at'] ?? null;

        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        $ts = (int) $value;

        return $ts > 0 ? $ts : null;
    }
}
