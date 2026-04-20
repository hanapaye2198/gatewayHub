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
use Throwable;

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
            Log::warning("{$providerName} webhook: missing event_id and payment reference; acknowledging without update", [
                'provider' => $normalized['provider'] ?? null,
                'payment_reference' => $normalized['payment_reference'] ?? null,
            ]);

            return $this->acknowledge();
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

            return $this->acknowledgeWithMessage([
                'message' => 'Already processed',
            ]);
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

                Log::info('WEBHOOK PROCESSED', [
                    'request_id' => $paymentReference,
                    'status' => $normalized['status'],
                    'updated_to' => $payment->status,
                    'duplicate' => true,
                ]);

                return $this->acknowledgeWithMessage(['message' => 'Already processed']);
            }

            DB::transaction(function () use ($payment, $normalized): void {
                $this->applyStatusFromNormalized($payment, $normalized);
                $this->mergeRawResponse($payment, $normalized['raw_payload']);
                $payment->save();
            });

            $payment->refresh();

            Log::info('WEBHOOK PROCESSED', [
                'request_id' => $paymentReference,
                'status' => $normalized['status'],
                'updated_to' => $payment->status,
                'payment_id' => $payment->id,
            ]);

            $this->markEventProcessed($event, $payment->id);

            return $this->acknowledge();
        } catch (Throwable $e) {
            $this->markEventFailed($event, $e->getMessage());

            Log::warning("{$providerName} webhook: processing failed", [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Transitions a webhook may drive on a Payment. Everything outside this map
     * is treated as terminal or pre-live and silently ignored (with a log).
     *
     * Rules enforced:
     *  - pending -> paid    : normal success
     *  - pending -> failed  : normal failure (expired/cancelled/etc.)
     *  - paid   -> *        : BLOCKED. paid is financial finality; reversals
     *                         must go through an explicit reconciliation flow,
     *                         never a late-arriving webhook.
     *  - failed -> paid     : BLOCKED. We already decided this payment failed;
     *                         a later "SUCCEEDED" could be a replay, a misrouted
     *                         provider retry, or a compromised callback.
     *  - refunded / failed_after_paid / provisioning / provisioning_failed
     *                       : terminal or pre-live, webhook cannot mutate.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_WEBHOOK_TRANSITIONS = [
        'pending' => ['paid', 'failed'],
    ];

    /**
     * Apply a normalized webhook status to the payment, respecting the strict
     * transition map above. Returns true when the payment's status actually
     * changed, false when the inbound webhook was ignored (same-state delivery
     * or a blocked transition). Blocked transitions are logged so ops can see
     * replays, misrouted callbacks, or provider-side reversals they need to
     * reconcile out-of-band.
     *
     * @param  array{status: 'paid'|'failed'|'pending'|'refunded'|'failed_after_paid', paid_at?: int|null}  $normalized
     */
    private function applyStatusFromNormalized(Payment $payment, array $normalized): bool
    {
        $incoming = $normalized['status'];
        $current = (string) $payment->status;

        if ($incoming === $current) {
            return false;
        }

        $allowed = self::ALLOWED_WEBHOOK_TRANSITIONS[$current] ?? [];
        if (! in_array($incoming, $allowed, true)) {
            Log::warning('webhook.payment_transition_blocked', [
                'payment_id' => $payment->id,
                'current_status' => $current,
                'incoming_status' => $incoming,
                'reason' => $this->resolveBlockReason($current, $incoming),
            ]);

            return false;
        }

        if ($incoming === 'paid') {
            $payment->status = 'paid';
            $paidAt = $normalized['paid_at'] ?? null;
            $payment->paid_at = $paidAt !== null ? Carbon::createFromTimestamp($paidAt) : now();

            return true;
        }

        if ($incoming === 'failed') {
            $payment->status = 'failed';

            return true;
        }

        return false;
    }

    private function resolveBlockReason(string $current, string $incoming): string
    {
        if ($current === 'paid') {
            return 'paid_is_terminal';
        }

        if ($current === 'failed' && $incoming === 'paid') {
            return 'failed_cannot_become_paid';
        }

        if (in_array($current, ['refunded', 'failed_after_paid'], true)) {
            return 'terminal_state';
        }

        if (in_array($current, ['provisioning', 'provisioning_failed'], true)) {
            return 'payment_not_live';
        }

        return 'transition_not_allowed';
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
        return response()->json([
            'success' => true,
            'received' => true,
        ], 200);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function acknowledgeWithMessage(array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'success' => true,
            'received' => true,
        ], $extra), 200);
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
                $gatewayQuery->whereIn('gateway_code', ['coins', 'gcash', 'maya', 'paypal', 'qrph']);

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
     * Strict, tenant-safe resolution for Coins (and Coins-backed gateways).
     *
     * A match is ONLY accepted when the normalized payment reference exactly
     * equals either our outbound identifier (`raw_response->gateway_request_reference`,
     * a per-payment ULID we issue) or the provider-assigned id we stored on the
     * payment (`provider_reference`). These are the only fields we are
     * contractually sure are unique per tenant/payment.
     *
     * Fuzzy/fallback paths (reference_id, raw_response->data->referenceId,
     * checkoutId, orderId, ...) have been removed: they could resolve the same
     * callback to a different merchant's payment, which is a cross-tenant
     * leakage risk. It is better to miss a match than to assign to the wrong
     * merchant — operators can re-drive via the queue or reconcile via the
     * status-sync service.
     *
     * @param  EloquentBuilder<Payment>  $baseQuery
     * @param  array<string, mixed>  $normalized
     * @return array{status: 'matched', payment: Payment}|array{status: 'not_found'}
     */
    private function resolveCoinsPayment(EloquentBuilder $baseQuery, string $paymentReference, array $normalized): array
    {
        $matches = (clone $baseQuery)
            ->where(function (EloquentBuilder $paymentQuery) use ($paymentReference): void {
                $paymentQuery
                    ->where('provider_reference', $paymentReference)
                    ->orWhere('raw_response->gateway_request_reference', $paymentReference);
            })
            ->limit(2)
            ->get();

        if ($matches->isEmpty()) {
            Log::warning('coins.webhook.payment_not_found', [
                'payment_reference' => $paymentReference,
                'reason' => 'no_strict_match',
                'status' => $normalized['status'] ?? null,
            ]);

            return ['status' => 'not_found'];
        }

        if ($matches->count() > 1) {
            Log::warning('coins.webhook.payment_not_found', [
                'payment_reference' => $paymentReference,
                'reason' => 'multiple_strict_candidates',
                'status' => $normalized['status'] ?? null,
                'candidate_payment_ids' => $matches->pluck('id')->all(),
                'candidate_merchant_ids' => $matches->pluck('merchant_id')->unique()->values()->all(),
            ]);

            return ['status' => 'not_found'];
        }

        return [
            'status' => 'matched',
            'payment' => $matches->first(),
        ];
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
