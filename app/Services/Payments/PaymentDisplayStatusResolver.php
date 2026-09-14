<?php

namespace App\Services\Payments;

use App\Enums\PaymentDisplayStatus;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PaymentDisplayStatusResolver
{
    /**
     * Ordered JSON paths (relative to payload or raw_response) used to extract
     * the provider status. Mirrors CoinsWebhookNormalizer candidate order.
     *
     * @var list<string>
     */
    private const EXTRACTED_STATUS_JSON_PATHS = [
        'qrcodeStatus',
        'data->qrcodeStatus',
        'status',
        'data->status',
        'requestStatus',
        'data->requestStatus',
        'paymentStatus',
        'data->paymentStatus',
        'transactionStatus',
        'data->transactionStatus',
    ];

    /**
     * @var list<string>
     */
    private const PROVIDER_STATUS_PAYLOAD_KEYS = [
        'qrcodeStatus',
        'status',
        'requestStatus',
        'paymentStatus',
        'transactionStatus',
    ];

    public function resolve(Payment $payment): PaymentDisplayStatus
    {
        return match ($payment->status) {
            'paid' => PaymentDisplayStatus::Paid,
            'pending' => PaymentDisplayStatus::Pending,
            'provisioning_failed' => PaymentDisplayStatus::ProvisioningFailed,
            'refunded' => PaymentDisplayStatus::Refunded,
            'failed_after_paid' => PaymentDisplayStatus::FailedAfterPaid,
            'failed' => $this->resolveFailedDisplayStatus($payment),
            default => PaymentDisplayStatus::Pending,
        };
    }

    /**
     * @param  Builder<Payment>  $query
     */
    public function applyFilter(Builder $query, string $displayStatus): void
    {
        if ($displayStatus === PaymentDisplayStatus::Expired->value) {
            $query->where('status', 'failed');
            $this->whereAuthoritativeProviderStatusIsExpired($query);

            return;
        }

        if ($displayStatus === PaymentDisplayStatus::Failed->value) {
            $query->where('status', 'failed');
            $this->whereAuthoritativeProviderStatusIsNotExpired($query);

            return;
        }

        if (in_array($displayStatus, [
            PaymentDisplayStatus::Paid->value,
            PaymentDisplayStatus::Pending->value,
            PaymentDisplayStatus::ProvisioningFailed->value,
            PaymentDisplayStatus::Refunded->value,
            PaymentDisplayStatus::FailedAfterPaid->value,
        ], true)) {
            $query->where('status', $displayStatus);
        }
    }

    /**
     * @param  Builder<Payment>  $query
     * @return array{
     *     total_transactions: int,
     *     paid_collections: float,
     *     pending_count: int,
     *     expired_count: int,
     *     failed_count: int,
     *     provisioning_failed_count: int
     * }
     */
    public function summarize(Builder $query): array
    {
        $expiredQuery = (clone $query)->where('status', 'failed');
        $this->whereAuthoritativeProviderStatusIsExpired($expiredQuery);

        $genuineFailedQuery = (clone $query)->where('status', 'failed');
        $this->whereAuthoritativeProviderStatusIsNotExpired($genuineFailedQuery);

        return [
            'total_transactions' => (clone $query)->count(),
            'paid_collections' => (float) (clone $query)->where('status', 'paid')->sum('amount'),
            'pending_count' => (clone $query)->where('status', 'pending')->count(),
            'expired_count' => $expiredQuery->count(),
            'failed_count' => $genuineFailedQuery->count(),
            'provisioning_failed_count' => (clone $query)->where('status', 'provisioning_failed')->count(),
        ];
    }

    private function resolveFailedDisplayStatus(Payment $payment): PaymentDisplayStatus
    {
        $providerStatus = $this->providerStatusFromPayment($payment);

        if ($providerStatus === 'EXPIRED') {
            return PaymentDisplayStatus::Expired;
        }

        return PaymentDisplayStatus::Failed;
    }

    private function providerStatusFromPayment(Payment $payment): ?string
    {
        if ($payment->relationLoaded('webhookEvents')) {
            $fromWebhook = $this->providerStatusFromWebhookEvents($payment->webhookEvents);
            if ($fromWebhook !== null) {
                return $fromWebhook;
            }
        }

        $raw = $payment->raw_response;

        return is_array($raw) ? $this->extractStatusValue($raw) : null;
    }

    /**
     * @param  Collection<int, WebhookEvent>  $webhookEvents
     */
    private function providerStatusFromWebhookEvents(Collection $webhookEvents): ?string
    {
        $latest = $webhookEvents
            ->sortByDesc(function (WebhookEvent $event): string {
                return sprintf(
                    '%020d:%020d',
                    $event->received_at?->getTimestamp() ?? 0,
                    (int) $event->id
                );
            })
            ->first();

        if (! $latest instanceof WebhookEvent) {
            return null;
        }

        $payload = $latest->payload;

        return is_array($payload) ? $this->extractStatusValue($payload) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractStatusValue(array $payload): ?string
    {
        $data = $payload['data'] ?? null;
        $data = is_array($data) ? $data : [];

        foreach (self::PROVIDER_STATUS_PAYLOAD_KEYS as $key) {
            $candidate = $payload[$key] ?? $data[$key] ?? null;
            if (is_string($candidate) && trim($candidate) !== '') {
                return strtoupper(trim($candidate));
            }
        }

        return null;
    }

    /**
     * Same rule as resolve(): latest correlated webhook extracted status, else raw_response.
     * Expired only when that extracted value is EXPIRED.
     *
     * @param  Builder<Payment>  $query
     */
    private function whereAuthoritativeProviderStatusIsExpired(Builder $query): void
    {
        $query->where(function (Builder $outer): void {
            $this->whereLatestWebhookExtractedStatusIsExpired($outer);

            $outer->orWhere(function (Builder $rawFallback): void {
                $rawFallback->where(function (Builder $noWebhookStatus): void {
                    $noWebhookStatus
                        ->whereDoesntHave('webhookEvents')
                        ->orWhere(function (Builder $latestMissing): void {
                            $this->whereLatestWebhookExtractedStatusIsMissing($latestMissing);
                        });
                });
                $this->whereJsonExtractedStatusIsExpired($rawFallback, 'raw_response');
            });
        });
    }

    /**
     * @param  Builder<Payment>  $query
     */
    private function whereAuthoritativeProviderStatusIsNotExpired(Builder $query): void
    {
        $query->whereNot(function (Builder $inner): void {
            $this->whereAuthoritativeProviderStatusIsExpired($inner);
        });
    }

    /**
     * @param  Builder<Payment>  $query
     */
    private function whereLatestWebhookExtractedStatusIsExpired(Builder $query): void
    {
        $query->whereHas('webhookEvents', function (Builder $events): void {
            $this->constrainToLatestWebhookEvent($events);
            $this->whereJsonExtractedStatusIsExpired($events, 'payload');
        });
    }

    /**
     * @param  Builder<Payment>  $query
     */
    private function whereLatestWebhookExtractedStatusIsMissing(Builder $query): void
    {
        $query->whereHas('webhookEvents', function (Builder $events): void {
            $this->constrainToLatestWebhookEvent($events);
            $this->whereJsonExtractedStatusIsMissing($events, 'payload');
        });
    }

    /**
     * @param  Builder<WebhookEvent>  $events
     */
    private function constrainToLatestWebhookEvent(Builder $events): void
    {
        $events->whereRaw(
            'webhook_events.id = (
                select we_latest.id
                from webhook_events as we_latest
                where we_latest.payment_id = webhook_events.payment_id
                order by we_latest.received_at desc, we_latest.id desc
                limit 1
            )'
        );
    }

    /**
     * @param  Builder<Payment>|Builder<WebhookEvent>  $query
     */
    private function whereJsonExtractedStatusIsExpired(Builder $query, string $column): void
    {
        $paths = $this->jsonColumnStatusPaths($column);

        $query->where(function (Builder $match) use ($paths): void {
            foreach ($paths as $index => $path) {
                $match->orWhere(function (Builder $thisPath) use ($paths, $index, $path): void {
                    $thisPath->whereNotNull($path)
                        ->where(function (Builder $expired) use ($path): void {
                            $expired->where($path, 'EXPIRED')
                                ->orWhere($path, 'expired');
                        });

                    for ($prior = 0; $prior < $index; $prior++) {
                        $thisPath->where(function (Builder $absent) use ($paths, $prior): void {
                            $absent->whereNull($paths[$prior])
                                ->orWhere($paths[$prior], '');
                        });
                    }
                });
            }
        });
    }

    /**
     * @param  Builder<Payment>|Builder<WebhookEvent>  $query
     */
    private function whereJsonExtractedStatusIsMissing(Builder $query, string $column): void
    {
        foreach ($this->jsonColumnStatusPaths($column) as $path) {
            $query->where(function (Builder $absent) use ($path): void {
                $absent->whereNull($path)
                    ->orWhere($path, '');
            });
        }
    }

    /**
     * @return list<string>
     */
    private function jsonColumnStatusPaths(string $column): array
    {
        return array_map(
            static fn (string $path): string => $column.'->'.$path,
            self::EXTRACTED_STATUS_JSON_PATHS
        );
    }
}
