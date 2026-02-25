<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use App\Services\Gateways\Exceptions\GatewayException;
use App\Services\Gateways\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for creating payments. Used by API and dashboard.
 * Resolves gateway via PaymentGatewayManager; no gateway logic lives in controllers.
 */
class PaymentCreationService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager
    ) {}

    /**
     * Create a payment. Resolves driver, calls gateway API, persists payment.
     *
     * @param  array{amount: float|int|string, currency: string, reference: string}  $data
     * @return array{payment: Payment, qr_data: string|null, expires_at: string|null}
     *
     * @throws GatewayException
     */
    public function create(User $merchant, string $gatewayCode, array $data): array
    {
        $driver = $this->gatewayManager->resolve($merchant, $gatewayCode);

        $response = $driver->createPayment([
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'reference' => $data['reference'],
        ]);

        $externalPaymentId = $response['external_payment_id'] ?? $response['provider_reference'] ?? null;
        $rawToStore = $response['raw'] ?? $response['raw_response'] ?? $response;
        if (isset($response['expires_at']) && is_string($response['expires_at'])) {
            $rawToStore = array_merge(is_array($rawToStore) ? $rawToStore : [], ['expires_at' => $response['expires_at']]);
        }

        $payment = DB::transaction(function () use ($merchant, $gatewayCode, $data, $externalPaymentId, $rawToStore) {
            return Payment::query()->create([
                'user_id' => $merchant->id,
                'gateway_code' => $gatewayCode,
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'reference_id' => $data['reference'],
                'provider_reference' => $externalPaymentId,
                'status' => 'pending',
                'raw_response' => $rawToStore,
                'paid_at' => null,
            ]);
        });

        $qrData = $response['qr_data'] ?? $response['qr_string'] ?? $response['qr_image'] ?? null;
        $qrData = is_string($qrData) && $qrData !== '' ? $qrData : null;
        $expiresAt = $response['expires_at'] ?? null;
        $expiresAt = is_string($expiresAt) && $expiresAt !== '' ? $expiresAt : null;

        return [
            'payment' => $payment,
            'qr_data' => $qrData,
            'expires_at' => $expiresAt,
        ];
    }
}
