<?php

namespace App\Services\Webhooks\Normalizers;

use App\Services\Webhooks\Contracts\WebhookNormalizerInterface;
use Illuminate\Support\Carbon;

class CoinsWebhookNormalizer implements WebhookNormalizerInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     * @return array{provider: string, event_id: string|null, payment_reference: string|null, status: 'paid'|'failed'|'pending'|'refunded', amount: string|float|null, currency: string|null, raw_payload: array<string, mixed>}
     */
    public function normalize(array $payload, array $headers): array
    {
        $data = $this->extractData($payload);
        $status = $this->normalizeStatus($payload, $data);
        $paidAt = $this->extractPaidAt($payload, $data, $status);

        return [
            'provider' => 'coins',
            'event_id' => $this->extractEventId($payload, $data, $status),
            'payment_reference' => $this->extractPaymentReference($payload, $data),
            'status' => $status,
            'amount' => $this->extractAmount($payload, $data),
            'currency' => $this->extractCurrency($payload, $data),
            'paid_at' => $paidAt,
            'raw_payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractEventId(array $payload, array $data, string $status): ?string
    {
        $candidates = ['eventId', 'event_id', 'webhookId', 'webhook_id', 'checkoutId', 'id'];
        foreach ($candidates as $key) {
            $value = $payload[$key] ?? $data[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
        }

        $reference = $this->extractPaymentReference($payload, $data);
        if ($reference !== null && $reference !== '') {
            $eventTimestamp = $this->extractEventTimestamp($payload, $data);
            if ($eventTimestamp !== null) {
                return $reference.':'.$status.':'.$eventTimestamp;
            }

            return $reference.':'.$status;
        }

        return null;
    }

    /**
     * Extract the best available Coins identifier for resolving the stored payment.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractPaymentReference(array $payload, array $data): ?string
    {
        $candidates = [
            $payload['requestId'] ?? null,
            $data['requestId'] ?? null,
            $payload['orderId'] ?? null,
            $data['orderId'] ?? null,
            $payload['referenceId'] ?? null,
            $data['referenceId'] ?? null,
            $payload['checkoutId'] ?? null,
            $data['checkoutId'] ?? null,
            $payload['internalOrderId'] ?? null,
            $data['internalOrderId'] ?? null,
            $payload['externalOrderId'] ?? null,
            $data['externalOrderId'] ?? null,
            $payload['id'] ?? null,
            $data['id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }

            if (is_int($candidate) || is_float($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return 'paid'|'failed'|'pending'|'refunded'
     */
    private function normalizeStatus(array $payload, array $data): string
    {
        $status = $this->extractStatusValue($payload, $data);
        if ($status === null) {
            return 'pending';
        }

        return match (strtoupper($status)) {
            'SUCCEEDED', 'SUCCESS', 'SUCCESSFUL', 'PAID', 'COMPLETED', 'SETTLED', 'DONE' => 'paid',
            'FAILED', 'EXPIRED', 'CANCEL', 'CANCELED', 'CANCELLED' => 'failed',
            'REFUNDED' => 'refunded',
            default => 'pending',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function extractData(array $payload): array
    {
        $data = $payload['data'] ?? null;

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractStatusValue(array $payload, array $data): ?string
    {
        $candidates = [
            $payload['qrcodeStatus'] ?? null,
            $data['qrcodeStatus'] ?? null,
            $payload['status'] ?? null,
            $data['status'] ?? null,
            $payload['requestStatus'] ?? null,
            $data['requestStatus'] ?? null,
            $payload['paymentStatus'] ?? null,
            $data['paymentStatus'] ?? null,
            $payload['transactionStatus'] ?? null,
            $data['transactionStatus'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractAmount(array $payload, array $data): string|float|null
    {
        $candidates = [
            $payload['amount'] ?? null,
            $data['amount'] ?? null,
            $payload['totalAmount'] ?? null,
            $data['totalAmount'] ?? null,
            $payload['fiatAmount'] ?? null,
            $data['fiatAmount'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) || is_float($candidate) || is_int($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractCurrency(array $payload, array $data): ?string
    {
        $candidates = [
            $payload['currency'] ?? null,
            $data['currency'] ?? null,
            $payload['fiatCurrency'] ?? null,
            $data['fiatCurrency'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return strtoupper(trim($candidate));
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractPaidAt(array $payload, array $data, string $status): ?int
    {
        if ($status !== 'paid') {
            return null;
        }

        $candidates = [
            $payload['settleDate'] ?? null,
            $data['settleDate'] ?? null,
            $payload['completionTime'] ?? null,
            $data['completionTime'] ?? null,
            $payload['completedAt'] ?? null,
            $data['completedAt'] ?? null,
            $payload['updatedTime'] ?? null,
            $data['updatedTime'] ?? null,
            $payload['timestamp'] ?? null,
            $data['timestamp'] ?? null,
            $payload['createdTime'] ?? null,
            $data['createdTime'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $timestamp = $this->parseTimestamp($candidate);
            if ($timestamp !== null) {
                return $timestamp;
            }
        }

        return (int) time();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractEventTimestamp(array $payload, array $data): ?string
    {
        $candidates = [
            $payload['timestamp'] ?? null,
            $data['timestamp'] ?? null,
            $payload['updatedTime'] ?? null,
            $data['updatedTime'] ?? null,
            $payload['completionTime'] ?? null,
            $data['completionTime'] ?? null,
            $payload['completedAt'] ?? null,
            $data['completedAt'] ?? null,
            $payload['settleDate'] ?? null,
            $data['settleDate'] ?? null,
            $payload['createdTime'] ?? null,
            $data['createdTime'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }

            if (is_int($candidate) || is_float($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    private function parseTimestamp(mixed $value): ?int
    {
        if (is_numeric($value)) {
            $numericValue = (float) $value;

            return $numericValue > 1e12
                ? (int) floor($numericValue / 1000)
                : (int) floor($numericValue);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $trimmedValue = trim($value);
        if (is_numeric($trimmedValue)) {
            $numericValue = (float) $trimmedValue;

            return $numericValue > 1e12
                ? (int) floor($numericValue / 1000)
                : (int) floor($numericValue);
        }

        try {
            return Carbon::parse($trimmedValue)->timestamp;
        } catch (\Throwable) {
            return null;
        }
    }
}
