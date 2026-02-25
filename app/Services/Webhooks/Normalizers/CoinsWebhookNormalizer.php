<?php

namespace App\Services\Webhooks\Normalizers;

use App\Services\Webhooks\Contracts\WebhookNormalizerInterface;

class CoinsWebhookNormalizer implements WebhookNormalizerInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     * @return array{provider: string, event_id: string|null, payment_reference: string|null, status: 'paid'|'failed'|'pending', amount: string|float|null, currency: string|null, raw_payload: array<string, mixed>}
     */
    public function normalize(array $payload, array $headers): array
    {
        $status = $this->normalizeStatus($payload);
        $paidAt = null;
        if ($status === 'paid') {
            $settleDate = $payload['settleDate'] ?? null;
            $paidAt = is_numeric($settleDate) ? (int) floor((float) $settleDate / 1000) : (int) time();
        }

        return [
            'provider' => 'coins',
            'event_id' => $this->extractEventId($payload),
            'payment_reference' => $this->extractPaymentReference($payload),
            'status' => $status,
            'amount' => $payload['amount'] ?? null,
            'currency' => $payload['currency'] ?? null,
            'paid_at' => $paidAt,
            'raw_payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractEventId(array $payload): ?string
    {
        $candidates = ['eventId', 'event_id', 'webhookId', 'webhook_id', 'id'];
        foreach ($candidates as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
        }

        $ref = $payload['referenceId'] ?? null;
        $status = $payload['status'] ?? '';
        $ts = $payload['timestamp'] ?? '';
        if (is_string($ref) && $ref !== '' && is_string($status) && (is_string($ts) || is_numeric($ts)) && $ts !== '') {
            return $ref.':'.$status.':'.$ts;
        }

        return null;
    }

    /**
     * Extract external payment id (Coins orderId) for finding payment by provider_reference.
     * Tries orderId first (matches our stored provider_reference), then referenceId.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractPaymentReference(array $payload): ?string
    {
        $orderId = $payload['orderId'] ?? $payload['data']['orderId'] ?? null;
        if (is_string($orderId) && $orderId !== '') {
            return $orderId;
        }
        $value = $payload['referenceId'] ?? $payload['data']['referenceId'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return 'paid'|'failed'|'pending'
     */
    private function normalizeStatus(array $payload): string
    {
        $status = $payload['status'] ?? null;
        if (! is_string($status)) {
            return 'pending';
        }

        return match (strtoupper($status)) {
            'SUCCEEDED' => 'paid',
            'FAILED', 'EXPIRED' => 'failed',
            default => 'pending',
        };
    }
}
