<?php

namespace App\Services;

use App\Models\Payment;
use App\Services\Gateways\Drivers\CoinsDriver;
use App\Services\Gateways\Drivers\PayMongoDriver;
use App\Services\Gateways\PlatformGatewayConfigService;
use App\Services\Webhooks\Normalizers\CoinsWebhookNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PaymentStatusSyncService
{
    /**
     * @var list<string>
     */
    private const STATUS_SYNC_GATEWAYS = ['coins', 'gcash', 'maya', 'qrph', 'grab_pay', 'shopeepay'];

    /**
     * @var list<string>
     */
    private const PAYMONGO_METHOD_GATEWAYS = ['qrph', 'gcash', 'maya', 'grab_pay', 'shopeepay'];

    public function __construct(
        protected PlatformGatewayConfigService $platformGatewayConfigService,
        protected CoinsWebhookNormalizer $coinsWebhookNormalizer
    ) {}

    public function syncPendingPayment(Payment $payment): void
    {
        if ($payment->status !== 'pending') {
            return;
        }

        if ($this->isPayMongoPayment($payment)) {
            if (! config('paymongo.status_sync.fallback_enabled', true)) {
                return;
            }

            $this->syncPayMongoPayment($payment);

            return;
        }

        if (! config('coins.status_sync.fallback_enabled', true)) {
            return;
        }

        $expiresAt = $payment->getExpiresAt();
        if ($expiresAt !== null && now()->isAfter($expiresAt)) {
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

        $this->rememberQrFromProviderStatus($payment, $providerStatus);

        $normalized = $this->coinsWebhookNormalizer->normalize($providerStatus, []);
        if ($normalized['status'] === 'pending') {
            if ($payment->isDirty('raw_response')) {
                $payment->save();
            }

            return;
        }

        $this->applyNormalizedStatus($payment, $normalized);
        $payment->mergeProviderResponse($providerStatus);
        $payment->save();
    }

    /**
     * Poll recent pending legacy Coins-backed and PayMongo payments for dashboard refreshes.
     */
    public function syncPendingPaymentsForMerchant(int $merchantId, int $limit = 10): void
    {
        if ($merchantId <= 0) {
            return;
        }

        $pending = Payment::query()
            ->where('merchant_id', $merchantId)
            ->where('status', 'pending')
            ->whereIn('gateway_code', self::STATUS_SYNC_GATEWAYS)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('created_at')
            ->limit(max(1, min($limit, 25)))
            ->get();

        foreach ($pending as $payment) {
            $this->syncPendingPayment($payment);
        }
    }

    private function resolveCoinsRequestId(Payment $payment): ?string
    {
        $legacyCoinsGateways = ['coins', 'gcash', 'maya', 'qrph'];
        if (! in_array($payment->gateway_code, $legacyCoinsGateways, true)) {
            return null;
        }

        $raw = $payment->raw_response;
        if ($payment->gateway_code !== 'coins'
            && is_array($raw)
            && array_key_exists('payment_intent', $raw)) {
            return null;
        }

        if (in_array($payment->gateway_code, self::PAYMONGO_METHOD_GATEWAYS, true)
            && str_starts_with((string) $payment->provider_reference, 'pi_')) {
            return null;
        }

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

        // Coins QR / hosted checkout: provider_reference is the Coins order id or request id when raw merge failed.
        // Do not use for PayPal (native); that id is not a Coins get_qr_code requestId.
        if (in_array($payment->gateway_code, ['coins', 'gcash', 'maya', 'qrph'], true)) {
            $providerRef = $payment->provider_reference;
            if (is_string($providerRef) && trim($providerRef) !== '') {
                return trim($providerRef);
            }
        }

        return null;
    }

    private function isPayMongoPayment(Payment $payment): bool
    {
        return in_array($payment->gateway_code, self::PAYMONGO_METHOD_GATEWAYS, true)
            && is_string($payment->provider_reference)
            && str_starts_with($payment->provider_reference, 'pi_');
    }

    private function syncPayMongoPayment(Payment $payment): void
    {
        $paymentIntentId = (string) $payment->provider_reference;

        try {
            $providerStatus = (new PayMongoDriver(
                $this->platformGatewayConfigService->forGatewayCode('paymongo')
            ))->getPaymentStatus($paymentIntentId);
        } catch (\Throwable $exception) {
            Log::warning('Payment status sync failed for PayMongo payment', [
                'payment_id' => $payment->id,
                'payment_intent_id' => $paymentIntentId,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $responseIntentId = data_get($providerStatus, 'data.id');
        $attributes = data_get($providerStatus, 'data.attributes', []);
        $providerIntentStatus = is_array($attributes) ? strtolower((string) ($attributes['status'] ?? '')) : '';
        $status = match ($providerIntentStatus) {
            'succeeded' => 'paid',
            'cancelled', 'canceled' => 'failed',
            default => 'pending',
        };

        if ($status === 'pending') {
            return;
        }

        if (! is_array($attributes)
            || ! is_string($responseIntentId)
            || ! hash_equals($paymentIntentId, $responseIntentId)
            || ! $this->payMongoPaymentDetailsMatch($payment, $attributes)) {
            Log::warning('Payment status sync rejected mismatched PayMongo payment details', [
                'payment_id' => $payment->id,
                'payment_intent_id' => $paymentIntentId,
                'response_intent_id' => $responseIntentId,
                'provider_amount' => is_array($attributes) ? ($attributes['amount'] ?? null) : null,
                'provider_currency' => is_array($attributes) ? ($attributes['currency'] ?? null) : null,
            ]);

            return;
        }

        $paidAt = $attributes['updated_at'] ?? null;
        $normalized = [
            'status' => $status,
            'paid_at' => $status === 'paid' && is_numeric($paidAt) ? (int) $paidAt : null,
        ];

        $this->applyNormalizedStatus($payment, $normalized);
        $payment->raw_response = $this->mergeRawResponse($payment->raw_response, $providerStatus);
        $payment->save();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function payMongoPaymentDetailsMatch(Payment $payment, array $attributes): bool
    {
        $providerAmount = $attributes['amount'] ?? null;
        if ((! is_int($providerAmount) && ! is_string($providerAmount))
            || ! ctype_digit((string) $providerAmount)) {
            return false;
        }

        $providerCurrency = strtoupper((string) ($attributes['currency'] ?? ''));
        if ($providerCurrency === '' || $providerCurrency !== strtoupper((string) $payment->currency)) {
            return false;
        }

        if (! preg_match('/^(\d+)\.(\d{2})$/', (string) $payment->amount, $matches)) {
            return false;
        }

        $expectedAmount = ltrim($matches[1].$matches[2], '0');
        $expectedAmount = $expectedAmount === '' ? '0' : $expectedAmount;
        $receivedAmount = ltrim((string) $providerAmount, '0');
        $receivedAmount = $receivedAmount === '' ? '0' : $receivedAmount;

        return $receivedAmount === $expectedAmount;
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
     */
    private function rememberQrFromProviderStatus(Payment $payment, array $providerStatus): void
    {
        if ($payment->getQrData() !== null) {
            return;
        }

        $qr = Payment::extractQrData($providerStatus);
        if ($qr === null || $qr['type'] !== 'string') {
            return;
        }

        $payment->rememberQrString($qr['value']);
    }
}
