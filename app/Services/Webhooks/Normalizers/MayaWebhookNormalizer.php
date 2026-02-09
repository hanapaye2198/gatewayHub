<?php

namespace App\Services\Webhooks\Normalizers;

use App\Services\Webhooks\Contracts\WebhookNormalizerInterface;

/**
 * Normalizes Maya/PayMaya webhook payloads into unified format.
 *
 * Supports Maya Checkout (flat) and Pay with Maya (charge) structures.
 * Status: PAYMENT_SUCCESS, PAYMENT_FAILED, PAYMENT_EXPIRED, PAYMENT_CANCELLED.
 */
class MayaWebhookNormalizer implements WebhookNormalizerInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     * @return array{provider: string, event_id: string|null, payment_reference: string|null, status: 'paid'|'failed'|'pending', amount: string|float|null, currency: string|null, paid_at?: int|null, raw_payload: array<string, mixed>}
     */
    public function normalize(array $payload, array $headers): array
    {
        $status = $this->normalizeStatus($payload);
        $paidAt = null;
        if ($status === 'paid') {
            $paidAt = $this->extractPaidAt($payload);
        }

        return [
            'provider' => 'maya',
            'event_id' => $this->extractEventId($payload),
            'payment_reference' => $this->extractPaymentReference($payload),
            'status' => $status,
            'amount' => $this->extractAmount($payload),
            'currency' => $this->extractCurrency($payload),
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
        if (! is_string($id) || $id === '') {
            return null;
        }

        $paymentStatus = $payload['paymentStatus'] ?? $payload['status'] ?? '';
        $ts = $payload['updatedAt'] ?? $payload['createdAt'] ?? '';
        if (is_string($paymentStatus) && $paymentStatus !== '' && (is_string($ts) || is_numeric($ts)) && $ts !== '') {
            return $id.':'.$paymentStatus.':'.$ts;
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractPaymentReference(array $payload): ?string
    {
        $ref = $payload['requestReferenceNumber'] ?? null;
        if (is_string($ref) && $ref !== '') {
            return $ref;
        }

        $txnRef = $payload['transactionReferenceNumber'] ?? null;
        if (is_string($txnRef) && $txnRef !== '') {
            return $txnRef;
        }

        $id = $payload['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return 'paid'|'failed'|'pending'
     */
    private function normalizeStatus(array $payload): string
    {
        $status = $payload['paymentStatus'] ?? $payload['status'] ?? null;
        if (! is_string($status)) {
            return 'pending';
        }

        return match (strtoupper($status)) {
            'PAYMENT_SUCCESS' => 'paid',
            'PAYMENT_FAILED', 'PAYMENT_EXPIRED', 'PAYMENT_CANCELLED', 'EXPIRED' => 'failed',
            'COMPLETED' => strtoupper((string) ($payload['paymentStatus'] ?? '')) === 'PAYMENT_SUCCESS' ? 'paid' : 'pending',
            default => 'pending',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractAmount(array $payload): string|float|null
    {
        $amount = $payload['amount'] ?? null;
        if ($amount !== null && (is_numeric($amount) || is_string($amount))) {
            return is_numeric($amount) ? (float) $amount : $amount;
        }

        $total = $payload['totalAmount'] ?? [];
        $value = is_array($total) ? ($total['value'] ?? $total['amount'] ?? null) : null;

        return $value !== null && (is_numeric($value) || is_string($value)) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractCurrency(array $payload): ?string
    {
        $currency = $payload['currency'] ?? null;
        if (is_string($currency) && $currency !== '') {
            return $currency;
        }

        $total = $payload['totalAmount'] ?? [];
        $curr = is_array($total) ? ($total['currency'] ?? null) : null;

        return is_string($curr) && $curr !== '' ? $curr : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractPaidAt(array $payload): ?int
    {
        $paymentAt = $payload['paymentDetails']['paymentAt'] ?? null;
        if ($paymentAt !== null) {
            $ts = $this->parseTimestamp($paymentAt);
            if ($ts !== null) {
                return $ts;
            }
        }

        $updatedAt = $payload['updatedAt'] ?? $payload['createdAt'] ?? null;
        if ($updatedAt !== null) {
            $ts = $this->parseTimestamp($updatedAt);
            if ($ts !== null) {
                return $ts;
            }
        }

        return (int) time();
    }

    private function parseTimestamp(mixed $value): ?int
    {
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
