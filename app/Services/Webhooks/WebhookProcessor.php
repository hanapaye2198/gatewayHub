<?php

namespace App\Services\Webhooks;

use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Webhooks\Contracts\WebhookNormalizerInterface;
use App\Services\Webhooks\Contracts\WebhookReplayValidatorInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookProcessor
{
    /**
     * Process webhook: replay validation, normalization, idempotency, payment update.
     * Caller must verify signature before invoking.
     *
     * @param  array<string, mixed>  $payload  Decoded JSON payload (already signature-verified).
     */
    public function process(
        Request $request,
        array $payload,
        WebhookReplayValidatorInterface $replayValidator,
        WebhookNormalizerInterface $normalizer,
        string $providerName
    ): JsonResponse {
        if (! $replayValidator->isValid($request, $payload)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $headers = $this->captureHeaders($request);
        $normalized = $normalizer->normalize($payload, $headers);

        if ($normalized['event_id'] === null) {
            return response()->json(['received' => true], 200);
        }

        $eventId = $normalized['event_id'];
        $paymentReference = $normalized['payment_reference'];

        return DB::transaction(function () use ($request, $normalized, $eventId, $paymentReference, $providerName): JsonResponse {
            $event = WebhookEvent::firstOrCreate(
                ['provider' => $normalized['provider'], 'event_id' => $eventId],
                [
                    'received_at' => now(),
                    'payload' => $normalized['raw_payload'],
                    'headers' => $this->captureHeaders($request),
                    'status' => 'received',
                ]
            );

            if (! $event->wasRecentlyCreated) {
                Log::info("{$providerName} webhook: duplicate event ignored (idempotent)", [
                    'event_id' => $eventId,
                ]);

                return response()->json(['received' => true], 200);
            }

            try {
                if ($paymentReference === null || $paymentReference === '') {
                    $event->update(['status' => 'processed', 'processed_at' => now()]);

                    return response()->json(['received' => true], 200);
                }

                $payment = Payment::query()->where('provider_reference', $paymentReference)->first();
                if ($payment === null) {
                    $payment = Payment::query()->where('reference_id', $paymentReference)->first();
                }
                if ($payment === null) {
                    $event->update(['status' => 'processed', 'processed_at' => now()]);

                    return response()->json(['received' => true], 200);
                }

                $event->update(['payment_id' => $payment->id]);

                if ($payment->status === 'paid') {
                    $event->update(['status' => 'processed', 'processed_at' => now()]);

                    return response()->json(['received' => true], 200);
                }

                $this->applyStatusFromNormalized($payment, $normalized);
                $this->mergeRawResponse($payment, $normalized['raw_payload']);
                $payment->save(); // PaymentObserver records platform fee when status transitions to paid

                $event->update(['status' => 'processed', 'processed_at' => now()]);

                return response()->json(['received' => true], 200);
            } catch (\Throwable $e) {
                $event->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);

                Log::warning("{$providerName} webhook: processing failed", [
                    'event_id' => $eventId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json(['received' => true], 200);
            }
        });
    }

    /**
     * Update payment from normalized webhook data.
     *
     * @param  array{status: 'paid'|'failed'|'pending', paid_at?: int|null}  $normalized
     */
    private function applyStatusFromNormalized(Payment $payment, array $normalized): void
    {
        $status = $normalized['status'];

        if ($status === 'paid') {
            $payment->status = 'paid';
            $paidAt = $normalized['paid_at'] ?? null;
            $payment->paid_at = $paidAt !== null ? Carbon::createFromTimestamp($paidAt) : now();

            return;
        }

        if ($status === 'failed') {
            $payment->status = 'failed';

            return;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mergeRawResponse(Payment $payment, array $payload): void
    {
        $existing = $payment->raw_response ?? [];
        if (! is_array($existing)) {
            $existing = [];
        }
        $payment->raw_response = array_merge($existing, $payload);
    }

    /**
     * Capture request headers for logging/audit, excluding sensitive values.
     *
     * @return array<string, list<string>>
     */
    public static function captureHeadersForPayload(Request $request): array
    {
        $headers = $request->headers->all();
        $sensitive = ['authorization', 'cookie', 'x-api-key'];
        foreach ($sensitive as $key) {
            unset($headers[$key]);
        }

        return $headers;
    }

    /**
     * @return array<string, list<string>>
     */
    private function captureHeaders(Request $request): array
    {
        return self::captureHeadersForPayload($request);
    }
}
