<?php

namespace App\Services\Webhooks\Normalizers;

use App\Services\Webhooks\Contracts\WebhookNormalizerInterface;

/**
 * Normalizes PayPal webhook payloads into unified format.
 *
 * Supports PAYMENT.CAPTURE.COMPLETED, PAYMENT.SALE.COMPLETED, and related events.
 * Resource may contain amount, invoice_id, supplementary_data.
 */
class PayPalWebhookNormalizer implements WebhookNormalizerInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     * @return array{provider: string, event_id: string|null, payment_reference: string|null, status: 'paid'|'failed'|'pending'|'refunded', amount: string|float|null, currency: string|null, paid_at?: int|null, raw_payload: array<string, mixed>}
     */
    public function normalize(array $payload, array $headers): array
    {
        $resource = $payload['resource'] ?? [];
        $status = $this->normalizeStatus($payload);
        $paidAt = null;
        if ($status === 'paid') {
            $paidAt = $this->extractPaidAt($payload);
        }

        return [
            'provider' => 'paypal',
            'event_id' => $this->extractEventId($payload),
            'payment_reference' => $this->extractPaymentReference($payload, $resource),
            'status' => $status,
            'amount' => $this->extractAmount($resource),
            'currency' => $this->extractCurrency($resource),
            'paid_at' => $paidAt,
            'raw_payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractEventId(array $payload): ?string
    {
        $id = $payload['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function extractPaymentReference(array $payload, array $resource): ?string
    {
        $invoiceId = $resource['invoice_id'] ?? null;
        if (is_string($invoiceId) && $invoiceId !== '') {
            return $invoiceId;
        }

        $supp = $resource['supplementary_data'] ?? $resource['supplemental_data'] ?? [];
        $related = is_array($supp) ? ($supp['related_ids'] ?? []) : [];
        $orderId = is_array($related) ? ($related['order_id'] ?? null) : null;
        if (is_string($orderId) && $orderId !== '') {
            return $orderId;
        }

        $purchaseUnits = $resource['purchase_units'] ?? [];
        $first = is_array($purchaseUnits) ? ($purchaseUnits[0] ?? []) : [];
        $customId = is_array($first) ? ($first['custom_id'] ?? $first['invoice_id'] ?? null) : null;
        if (is_string($customId) && $customId !== '') {
            return $customId;
        }

        $resourceId = $resource['id'] ?? null;

        return is_string($resourceId) && $resourceId !== '' ? $resourceId : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return 'paid'|'failed'|'pending'|'refunded'
     */
    private function normalizeStatus(array $payload): string
    {
        $eventType = $payload['event_type'] ?? '';
        if (! is_string($eventType)) {
            return 'pending';
        }

        return match (strtoupper($eventType)) {
            'PAYMENT.CAPTURE.COMPLETED', 'PAYMENT.SALE.COMPLETED' => 'paid',
            'PAYMENT.CAPTURE.REFUNDED' => 'refunded',
            'PAYMENT.CAPTURE.DENIED', 'PAYMENT.AUTHORIZATION.VOIDED' => 'failed',
            default => 'pending',
        };
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function extractAmount(array $resource): string|float|null
    {
        $amount = $resource['amount'] ?? [];
        if (! is_array($amount)) {
            return null;
        }

        $value = $amount['total'] ?? $amount['value'] ?? null;
        if ($value !== null && (is_numeric($value) || is_string($value))) {
            return is_numeric($value) ? (float) $value : $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function extractCurrency(array $resource): ?string
    {
        $amount = $resource['amount'] ?? [];
        if (! is_array($amount)) {
            return null;
        }

        $currency = $amount['currency_code'] ?? $amount['currency'] ?? null;

        return is_string($currency) && $currency !== '' ? $currency : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractPaidAt(array $payload): ?int
    {
        $createTime = $payload['create_time'] ?? null;
        if ($createTime === null) {
            return (int) time();
        }

        if (is_numeric($createTime)) {
            $ts = (int) $createTime;

            return $ts > 0 ? $ts : null;
        }
        if (is_string($createTime)) {
            $ts = strtotime($createTime);

            return $ts !== false ? $ts : null;
        }

        return (int) time();
    }
}
