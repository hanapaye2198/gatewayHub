<?php

namespace App\Services\Webhooks\Contracts;

/**
 * Normalizes provider-specific webhook payloads into a unified internal format.
 *
 * @phpstan-type NormalizedWebhook array{
 *     provider: string,
 *     event_id: string|null,
 *     payment_reference: string|null,
 *     status: 'paid'|'failed'|'pending'|'refunded'|'failed_after_paid',
 *     amount: string|float|null,
 *     currency: string|null,
 *     paid_at?: int|null,
 *     raw_payload: array<string, mixed>
 * }
 */
interface WebhookNormalizerInterface
{
    /**
     * Convert provider webhook payload and headers into unified format.
     *
     * @param  array<string, mixed>  $payload  Raw webhook body (decoded JSON).
     * @param  array<string, mixed>  $headers  Request headers.
     * @return NormalizedWebhook
     */
    public function normalize(array $payload, array $headers): array;
}
