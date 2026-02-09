<?php

namespace App\Services\Webhooks\Normalizers;

use App\Services\Webhooks\Contracts\WebhookNormalizerInterface;

/**
 * Normalizes GCash/PayMongo-style webhook payloads into unified format.
 *
 * Supports PayMongo event structure: data.id (evt_xxx), data.attributes.data (payment/source/link).
 */
class GcashWebhookNormalizer implements WebhookNormalizerInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     * @return array{provider: string, event_id: string|null, payment_reference: string|null, status: 'paid'|'failed'|'pending', amount: string|float|null, currency: string|null, paid_at?: int|null, raw_payload: array<string, mixed>}
     */
    public function normalize(array $payload, array $headers): array
    {
        $data = $payload['data'] ?? [];
        $attrs = is_array($data) ? ($data['attributes'] ?? []) : [];
        $inner = is_array($attrs) ? ($attrs['data'] ?? []) : [];
        $innerAttrs = is_array($inner) ? ($inner['attributes'] ?? $inner) : [];

        return [
            'provider' => 'gcash',
            'event_id' => $this->extractEventId($payload),
            'payment_reference' => $this->extractPaymentReference($payload, $data, $inner, $innerAttrs),
            'status' => $this->normalizeStatus($payload, $innerAttrs),
            'amount' => $this->extractAmount($payload, $innerAttrs),
            'currency' => $this->extractCurrency($payload, $innerAttrs),
            'paid_at' => $this->extractPaidAt($innerAttrs),
            'raw_payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractEventId(array $payload): ?string
    {
        $data = $payload['data'] ?? [];
        $id = $data['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $inner
     * @param  array<string, mixed>  $innerAttrs
     */
    private function extractPaymentReference(array $payload, array $data, array $inner, array $innerAttrs): ?string
    {
        $ref = $innerAttrs['external_reference_number'] ?? $innerAttrs['reference_number'] ?? null;
        if (is_string($ref) && $ref !== '') {
            return $ref;
        }

        $innerId = $inner['id'] ?? $data['id'] ?? null;

        return is_string($innerId) && $innerId !== '' ? $innerId : null;
    }

    /**
     * @param  array<string, mixed>  $innerAttrs
     * @return 'paid'|'failed'|'pending'
     */
    private function normalizeStatus(array $payload, array $innerAttrs): string
    {
        $eventType = $payload['data']['attributes']['type'] ?? null;
        if (is_string($eventType)) {
            if (str_contains($eventType, 'payment.paid') || str_contains($eventType, 'paid')) {
                return 'paid';
            }
            if (str_contains($eventType, 'payment.failed') || str_contains($eventType, 'failed')) {
                return 'failed';
            }
        }

        $status = $innerAttrs['status'] ?? null;
        if (! is_string($status)) {
            return 'pending';
        }

        return match (strtolower($status)) {
            'paid' => 'paid',
            'failed', 'expired' => 'failed',
            default => 'pending',
        };
    }

    /**
     * @param  array<string, mixed>  $innerAttrs
     */
    private function extractAmount(array $payload, array $innerAttrs): string|float|null
    {
        $amount = $innerAttrs['amount'] ?? null;
        if ($amount !== null && (is_numeric($amount) || is_string($amount))) {
            return is_numeric($amount) ? (float) $amount : $amount;
        }

        $lineItems = $payload['data']['attributes']['data']['attributes']['line_items'] ?? [];
        $first = is_array($lineItems) ? ($lineItems[0] ?? null) : null;
        $amt = is_array($first) ? ($first['amount'] ?? null) : null;

        return $amt !== null && (is_numeric($amt) || is_string($amt)) ? $amt : null;
    }

    /**
     * @param  array<string, mixed>  $innerAttrs
     */
    private function extractCurrency(array $payload, array $innerAttrs): ?string
    {
        $currency = $innerAttrs['currency'] ?? null;

        if (is_string($currency) && $currency !== '') {
            return $currency;
        }

        $lineItems = $payload['data']['attributes']['data']['attributes']['line_items'] ?? [];
        $first = is_array($lineItems) ? ($lineItems[0] ?? null) : null;
        $curr = is_array($first) ? ($first['currency'] ?? null) : null;

        return is_string($curr) && $curr !== '' ? $curr : null;
    }

    /**
     * @param  array<string, mixed>  $innerAttrs
     */
    private function extractPaidAt(array $innerAttrs): ?int
    {
        $paidAt = $innerAttrs['paid_at'] ?? $innerAttrs['updated_at'] ?? $innerAttrs['created_at'] ?? null;
        if ($paidAt === null || ! is_numeric($paidAt)) {
            return null;
        }

        $ts = (int) $paidAt;

        return $ts > 0 ? $ts : null;
    }
}
