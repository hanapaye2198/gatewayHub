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
     * @param  array<string, mixed>  $context
     */
    public function process(
        Request $request,
        array $payload,
        WebhookReplayValidatorInterface $replayValidator,
        WebhookNormalizerInterface $normalizer,
        string $providerName,
        array $context = []
    ): JsonResponse {
        $skipReplayValidation = (bool) ($context['skip_replay_validation'] ?? false);
        if (! $skipReplayValidation && ! $replayValidator->isValid($request, $payload)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $headers = $this->captureHeaders($request);
        $normalized = $normalizer->normalize($payload, $headers);

        if ($normalized['event_id'] === null) {
            return response()->json(['received' => true], 200);
        }

        $eventId = $normalized['event_id'];
        $paymentReference = $normalized['payment_reference'];
        $provider = (string) ($normalized['provider'] ?? '');
        $verifiedMerchantIds = $this->extractVerifiedMerchantIds($context);

        return DB::transaction(function () use ($request, $normalized, $eventId, $paymentReference, $providerName, $provider, $verifiedMerchantIds): JsonResponse {
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

                $payment = $this->resolvePayment($provider, $paymentReference, $verifiedMerchantIds);
                if ($payment === null) {
                    $event->update(['status' => 'processed', 'processed_at' => now()]);

                    return response()->json(['received' => true], 200);
                }

                $event->update(['payment_id' => $payment->id]);

                if ($payment->status === 'paid' && in_array($normalized['status'], ['paid', 'pending'], true)) {
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
     * @param  array{status: 'paid'|'failed'|'pending'|'refunded'|'failed_after_paid', paid_at?: int|null}  $normalized
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
            if ($payment->status === 'paid') {
                $payment->status = 'failed_after_paid';

                return;
            }

            $payment->status = 'failed';

            return;
        }

        if ($status === 'failed_after_paid') {
            $payment->status = 'failed_after_paid';

            return;
        }

        if ($status === 'refunded') {
            $payment->status = 'refunded';

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

    /**
     * @param  array<string, mixed>  $context
     * @return list<int>
     */
    private function extractVerifiedMerchantIds(array $context): array
    {
        $merchantIds = $context['verified_merchant_ids'] ?? null;
        if (! is_array($merchantIds)) {
            return [];
        }

        $result = [];
        foreach ($merchantIds as $merchantId) {
            if (! is_int($merchantId) && ! (is_string($merchantId) && ctype_digit($merchantId))) {
                continue;
            }

            $intMerchantId = (int) $merchantId;
            if ($intMerchantId <= 0) {
                continue;
            }

            $result[] = $intMerchantId;
        }

        return array_values(array_unique($result));
    }

    /**
     * @param  list<int>  $verifiedMerchantIds
     */
    private function resolvePayment(string $provider, string $paymentReference, array $verifiedMerchantIds): ?Payment
    {
        $query = Payment::query()->where(function ($gatewayQuery) use ($provider): void {
            if ($provider === 'coins') {
                $gatewayQuery->whereIn('gateway_code', ['coins', 'gcash', 'maya', 'paypal', 'qrph', 'payqrph']);

                return;
            }

            $gatewayQuery->where('gateway_code', $provider);
        })->where(function ($paymentQuery) use ($paymentReference): void {
            $paymentQuery
                ->where('provider_reference', $paymentReference)
                ->orWhere('reference_id', $paymentReference);
        });

        if ($verifiedMerchantIds !== []) {
            $query->whereIn('user_id', $verifiedMerchantIds);
        }

        $matches = $query->limit(2)->get();
        if ($matches->count() !== 1) {
            return null;
        }

        return $matches->first();
    }
}
