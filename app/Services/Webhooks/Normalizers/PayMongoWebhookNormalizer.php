<?php

namespace App\Services\Webhooks\Normalizers;

use App\Services\Webhooks\Contracts\WebhookNormalizerInterface;

class PayMongoWebhookNormalizer implements WebhookNormalizerInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     * @return array{provider: string, event_id: string|null, payment_reference: string|null, status: 'paid'|'failed'|'pending'|'refunded', amount: string|float|int|null, currency: string|null, raw_payload: array<string, mixed>, paid_at: int|null}
     */
    public function normalize(array $payload, array $headers): array
    {
        $eventData = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $eventAttributes = is_array($eventData['attributes'] ?? null) ? $eventData['attributes'] : [];
        $resource = is_array($eventAttributes['data'] ?? null) ? $eventAttributes['data'] : [];
        $attributes = is_array($resource['attributes'] ?? null) ? $resource['attributes'] : [];
        $eventType = strtolower((string) ($eventAttributes['type'] ?? ''));

        return [
            'provider' => 'paymongo',
            'event_id' => $this->firstString([$eventData['id'] ?? null, $payload['id'] ?? null]),
            'payment_reference' => $this->extractPaymentIntentId($eventType, $resource, $attributes),
            'status' => $this->normalizeStatus($eventType),
            'amount' => $this->firstNumeric([$attributes['amount'] ?? null]),
            'currency' => $this->firstString([$attributes['currency'] ?? null]),
            'paid_at' => $this->extractPaidAt($attributes, $eventAttributes, $eventType),
            'raw_payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $resource
     * @param  array<string, mixed>  $attributes
     */
    private function extractPaymentIntentId(string $eventType, array $resource, array $attributes): ?string
    {
        $intentId = $this->firstString([
            $attributes['payment_intent_id'] ?? null,
            $attributes['payment_intent']['id'] ?? null,
            $resource['payment_intent_id'] ?? null,
        ]);

        if ($intentId !== null) {
            return $intentId;
        }

        if (str_starts_with($eventType, 'payment_intent.') && str_starts_with((string) ($resource['id'] ?? ''), 'pi_')) {
            return (string) $resource['id'];
        }

        return null;
    }

    /**
     * @return 'paid'|'failed'|'pending'|'refunded'
     */
    private function normalizeStatus(string $eventType): string
    {
        return match ($eventType) {
            'payment.paid', 'payment_intent.succeeded' => 'paid',
            'payment.failed', 'qrph.expired' => 'failed',
            'payment.refunded' => 'refunded',
            default => 'pending',
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $eventAttributes
     */
    private function extractPaidAt(array $attributes, array $eventAttributes, string $eventType): ?int
    {
        if (! in_array($eventType, ['payment.paid', 'payment_intent.succeeded'], true)) {
            return null;
        }

        foreach ([$attributes['paid_at'] ?? null, $attributes['created_at'] ?? null, $eventAttributes['created_at'] ?? null] as $value) {
            if (is_numeric($value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $values
     */
    private function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $values
     */
    private function firstNumeric(array $values): string|float|int|null
    {
        foreach ($values as $value) {
            if (is_string($value) || is_float($value) || is_int($value)) {
                return $value;
            }
        }

        return null;
    }
}
