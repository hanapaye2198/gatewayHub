<?php

namespace App\Services\Webhooks;

use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Webhooks\Contracts\WebhookNormalizerInterface;
use App\Services\Webhooks\Contracts\WebhookReplayValidatorInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
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
        $event = $this->claimEvent($normalized['provider'], $eventId, $normalized['raw_payload'], $headers);

        if ($event->status === 'processed') {
            Log::info("{$providerName} webhook: duplicate event ignored (idempotent)", [
                'event_id' => $eventId,
            ]);

            return $this->acknowledge();
        }

        try {
            if ($paymentReference === null || $paymentReference === '') {
                Log::warning("{$providerName} webhook: payment reference missing", [
                    'event_id' => $eventId,
                    'status' => $normalized['status'],
                ]);
                $this->markEventProcessed($event);

                return $this->acknowledge();
            }

            $resolution = $this->resolvePayment($provider, $paymentReference, $verifiedMerchantIds, $normalized);
            if ($resolution['status'] === 'not_found') {
                Log::warning("{$providerName} webhook: payment not found", [
                    'event_id' => $eventId,
                    'payment_reference' => $paymentReference,
                    'status' => $normalized['status'],
                ]);
                $this->markEventProcessed($event);

                return $this->acknowledge();
            }

            if ($resolution['status'] === 'ambiguous') {
                throw new \RuntimeException(sprintf(
                    'Unable to uniquely resolve payment for webhook reference [%s].',
                    $paymentReference
                ));
            }

            $payment = $resolution['payment'];
            if ($payment->status === 'paid' && in_array($normalized['status'], ['paid', 'pending'], true)) {
                $this->markEventProcessed($event, $payment->id);

                return $this->acknowledge();
            }

            DB::transaction(function () use ($payment, $normalized): void {
                $this->applyStatusFromNormalized($payment, $normalized);
                $this->mergeRawResponse($payment, $normalized['raw_payload']);
                $payment->save();
            });

            $this->markEventProcessed($event, $payment->id);

            return $this->acknowledge();
        } catch (\Throwable $e) {
            $this->markEventFailed($event, $e->getMessage());

            Log::warning("{$providerName} webhook: processing failed", [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
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

    private function acknowledge(): JsonResponse
    {
        return response()->json(['received' => true], 200);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, list<string>>  $headers
     */
    private function claimEvent(string $provider, string $eventId, array $payload, array $headers): WebhookEvent
    {
        return DB::transaction(function () use ($provider, $eventId, $payload, $headers): WebhookEvent {
            $event = WebhookEvent::query()
                ->where('provider', $provider)
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if (! $event instanceof WebhookEvent) {
                return WebhookEvent::query()->create([
                    'provider' => $provider,
                    'event_id' => $eventId,
                    'received_at' => now(),
                    'payload' => $payload,
                    'headers' => $headers,
                    'status' => 'received',
                ]);
            }

            if ($event->status === 'processed') {
                return $event;
            }

            $event->update([
                'received_at' => now(),
                'processed_at' => null,
                'payload' => $payload,
                'headers' => $headers,
                'status' => 'received',
                'error_message' => null,
            ]);

            return $event->fresh() ?? $event;
        });
    }

    private function markEventProcessed(WebhookEvent $event, ?string $paymentId = null): void
    {
        $attributes = [
            'status' => 'processed',
            'processed_at' => now(),
            'error_message' => null,
        ];

        if ($paymentId !== null) {
            $attributes['payment_id'] = $paymentId;
        }

        $event->update($attributes);
    }

    private function markEventFailed(WebhookEvent $event, string $errorMessage): void
    {
        $event->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
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
    private function resolvePayment(string $provider, string $paymentReference, array $verifiedMerchantIds, array $normalized): array
    {
        $baseQuery = Payment::query()->where(function ($gatewayQuery) use ($provider): void {
            if ($provider === 'coins') {
                $gatewayQuery->whereIn('gateway_code', ['coins', 'gcash', 'maya', 'paypal', 'qrph', 'payqrph']);

                return;
            }

            $gatewayQuery->where('gateway_code', $provider);
        });

        if ($verifiedMerchantIds !== []) {
            $baseQuery->whereIn('merchant_id', $verifiedMerchantIds);
        }

        if ($provider === 'coins') {
            return $this->resolveCoinsPayment($baseQuery, $paymentReference, $normalized);
        }

        return $this->resolveSinglePayment(
            (clone $baseQuery)->where(function (EloquentBuilder $paymentQuery) use ($paymentReference): void {
                $paymentQuery
                    ->where('provider_reference', $paymentReference)
                    ->orWhere('reference_id', $paymentReference);
            })
        );
    }

    /**
     * @param  EloquentBuilder<Payment>  $baseQuery
     * @param  array<string, mixed>  $normalized
     * @return array{status: 'matched', payment: Payment}|array{status: 'not_found'|'ambiguous'}
     */
    private function resolveCoinsPayment(EloquentBuilder $baseQuery, string $paymentReference, array $normalized): array
    {
        $exactQuery = (clone $baseQuery)->where(function (EloquentBuilder $paymentQuery) use ($paymentReference): void {
            $paymentQuery
                ->where('provider_reference', $paymentReference)
                ->orWhere('raw_response->gateway_request_reference', $paymentReference)
                ->orWhere('raw_response->requestId', $paymentReference)
                ->orWhere('raw_response->data->requestId', $paymentReference)
                ->orWhere('raw_response->referenceId', $paymentReference)
                ->orWhere('raw_response->data->referenceId', $paymentReference)
                ->orWhere('raw_response->checkoutId', $paymentReference)
                ->orWhere('raw_response->data->checkoutId', $paymentReference)
                ->orWhere('raw_response->orderId', $paymentReference)
                ->orWhere('raw_response->data->orderId', $paymentReference)
                ->orWhere('raw_response->internalOrderId', $paymentReference)
                ->orWhere('raw_response->data->internalOrderId', $paymentReference)
                ->orWhere('raw_response->externalOrderId', $paymentReference)
                ->orWhere('raw_response->data->externalOrderId', $paymentReference)
                ->orWhere('raw_response->id', $paymentReference)
                ->orWhere('raw_response->data->id', $paymentReference);
        });
        $exactResolution = $this->resolveSinglePayment($exactQuery);
        if ($exactResolution['status'] !== 'not_found') {
            return $exactResolution;
        }

        $narrowedReferenceQuery = (clone $baseQuery)->where('reference_id', $paymentReference);
        $narrowedReferenceQuery = $this->applyWebhookPayloadNarrowing($narrowedReferenceQuery, $normalized);
        $narrowedResolution = $this->resolveSinglePayment($narrowedReferenceQuery);
        if ($narrowedResolution['status'] !== 'not_found') {
            return $narrowedResolution;
        }

        return $this->resolveSinglePayment((clone $baseQuery)->where('reference_id', $paymentReference));
    }

    /**
     * @param  EloquentBuilder<Payment>  $query
     * @param  array<string, mixed>  $normalized
     * @return EloquentBuilder<Payment>
     */
    private function applyWebhookPayloadNarrowing(EloquentBuilder $query, array $normalized): EloquentBuilder
    {
        $amount = $normalized['amount'] ?? null;
        if (is_numeric($amount)) {
            $query->where('amount', number_format((float) $amount, 2, '.', ''));
        }

        $currency = $normalized['currency'] ?? null;
        if (is_string($currency) && trim($currency) !== '') {
            $query->where('currency', strtoupper(trim($currency)));
        }

        return $query;
    }

    /**
     * @param  EloquentBuilder<Payment>  $query
     * @return array{status: 'matched', payment: Payment}|array{status: 'not_found'|'ambiguous'}
     */
    private function resolveSinglePayment(EloquentBuilder $query): array
    {
        $matches = $query->limit(2)->get();
        if ($matches->isEmpty()) {
            return ['status' => 'not_found'];
        }

        if ($matches->count() > 1) {
            return ['status' => 'ambiguous'];
        }

        return [
            'status' => 'matched',
            'payment' => $matches->first(),
        ];
    }
}
