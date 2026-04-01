<?php

namespace App\Services;

use App\Models\Payment;
use App\Services\Gateways\Drivers\CoinsDriver;
use App\Services\Gateways\PlatformGatewayConfigService;
use App\Services\Webhooks\Normalizers\CoinsWebhookNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PaymentStatusSyncService
{
    /**
     * @var list<string>
     */
    private const COINS_ORCHESTRATED_GATEWAYS = ['coins', 'gcash', 'maya', 'paypal', 'qrph'];

    public function __construct(
        protected PlatformGatewayConfigService $platformGatewayConfigService,
        protected CoinsWebhookNormalizer $coinsWebhookNormalizer
    ) {}

    public function syncPendingPayment(Payment $payment): void
    {
        if ($payment->status !== 'pending') {
            return;
        }

        if (! config('coins.status_sync.fallback_enabled', true)) {
            return;
        }

        $requestId = $this->resolveCoinsRequestId($payment);
        if ($requestId === null) {
            return;
        }

        try {
            $providerStatus = (new CoinsDriver(
                $this->platformGatewayConfigService->forGatewayCode('coins')
            ))->getPaymentStatus($requestId);
        } catch (\Throwable $exception) {
            Log::warning('Payment status sync failed for Coins-orchestrated payment', [
                'payment_id' => $payment->id,
                'gateway_code' => $payment->gateway_code,
                'request_id' => $requestId,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $normalized = $this->coinsWebhookNormalizer->normalize($providerStatus, []);
        if ($normalized['status'] === 'pending') {
            return;
        }

        $this->applyNormalizedStatus($payment, $normalized);
        $payment->raw_response = $this->mergeRawResponse($payment->raw_response, $providerStatus);
        $payment->save();
    }

    private function resolveCoinsRequestId(Payment $payment): ?string
    {
        if (! in_array($payment->gateway_code, self::COINS_ORCHESTRATED_GATEWAYS, true)) {
            return null;
        }

        $raw = $payment->raw_response;
        if (! is_array($raw)) {
            return null;
        }

        $candidates = [
            $raw['gateway_request_reference'] ?? null,
            $raw['data']['requestId'] ?? null,
            $raw['requestId'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    /**
     * @param  array{status: 'paid'|'failed'|'pending'|'refunded', paid_at?: int|null}  $normalized
     */
    private function applyNormalizedStatus(Payment $payment, array $normalized): void
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

        if ($status === 'refunded') {
            $payment->status = 'refunded';
        }
    }

    /**
     * @param  array<string, mixed>  $providerStatus
     * @return array<string, mixed>
     */
    private function mergeRawResponse(mixed $existingRawResponse, array $providerStatus): array
    {
        $existing = is_array($existingRawResponse) ? $existingRawResponse : [];

        return array_merge($existing, $providerStatus);
    }
}
