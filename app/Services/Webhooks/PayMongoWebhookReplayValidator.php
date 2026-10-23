<?php

namespace App\Services\Webhooks;

use App\Services\Webhooks\Contracts\WebhookReplayValidatorInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayMongoWebhookReplayValidator implements WebhookReplayValidatorInterface
{
    public function isValid(Request $request, array $payload): bool
    {
        $header = $request->header('Paymongo-Signature');
        $timestamp = $this->extractTimestamp(is_string($header) ? $header : '');
        if ($timestamp === null) {
            Log::warning('PayMongo webhook: replay protection failed; timestamp missing or invalid', [
                'event_id' => data_get($payload, 'data.id'),
            ]);

            return false;
        }

        $maxAge = (int) config('paymongo.webhook.max_age', 300);
        if (abs((int) now()->timestamp - $timestamp) > $maxAge) {
            Log::warning('PayMongo webhook: replay protection failed; timestamp outside allowed window', [
                'event_id' => data_get($payload, 'data.id'),
                'payload_timestamp' => $timestamp,
                'server_time' => now()->timestamp,
                'max_age_seconds' => $maxAge,
            ]);

            return false;
        }

        return true;
    }

    private function extractTimestamp(string $signatureHeader): ?int
    {
        foreach (explode(',', $signatureHeader) as $component) {
            [$key, $value] = array_pad(explode('=', trim($component), 2), 2, '');
            if (strtolower(trim($key)) === 't' && ctype_digit(trim($value))) {
                return (int) trim($value);
            }
        }

        return null;
    }
}
