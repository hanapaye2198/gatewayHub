<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\Payment;
use App\Services\Gateways\Drivers\CoinsDriver;
use App\Services\Gateways\Exceptions\GatewayException;
use App\Services\Gateways\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Single entry point for creating payments. Used by API and dashboard.
 * Resolves gateway via PaymentGatewayManager; no gateway logic lives in controllers.
 *
 * Financial integrity contract:
 *  - A Payment row is always persisted BEFORE any gateway API call.
 *  - On gateway success, the row is transitioned from "provisioning" to "pending".
 *  - On gateway failure, the row is transitioned to "provisioning_failed" and the
 *    raw_response is preserved for audit. Payment rows are NEVER deleted.
 */
class PaymentCreationService
{
    private const STATUS_PROVISIONING = 'provisioning';

    private const STATUS_PROVISIONING_FAILED = 'provisioning_failed';

    private const STATUS_PENDING = 'pending';

    public function __construct(
        protected PaymentGatewayManager $gatewayManager
    ) {}

    /**
     * Create a payment. Resolves driver, persists a provisioning Payment, calls the
     * gateway API, then transitions the Payment to its terminal (success/failed) state.
     *
     * @param  array{amount: float|int|string, currency: string, reference: string, checkout?: bool, product_name?: string|null}  $data
     * @return array{payment: Payment, qr_data: string|null, expires_at: string|null, redirect_url: string|null}
     *
     * @throws GatewayException
     */
    public function create(Merchant $merchant, string $gatewayCode, array $data): array
    {
        if (($data['checkout'] ?? false) === true) {
            return $this->createCoinsCheckoutPayment($merchant, $gatewayCode, $data);
        }

        $driver = $this->gatewayManager->resolve($merchant, $gatewayCode);
        $gatewayRequestReference = $this->buildGatewayRequestReference($merchant);

        $payment = DB::transaction(fn (): Payment => Payment::query()->create([
            'merchant_id' => $merchant->id,
            'gateway_code' => $gatewayCode,
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'reference_id' => $data['reference'],
            'provider_reference' => null,
            'status' => self::STATUS_PROVISIONING,
            'raw_response' => [
                'gateway_request_reference' => $gatewayRequestReference,
                'merchant_reference' => $data['reference'],
            ],
            'paid_at' => null,
        ]));

        try {
            $response = $driver->createPayment([
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'reference' => $gatewayRequestReference,
                'qr_code_merchant_name' => $merchant->getQrMerchantName(),
            ]);
        } catch (Throwable $e) {
            $this->markProvisioningFailed($payment, $gatewayRequestReference, $data['reference'], $e);

            throw $e;
        }

        $externalPaymentId = $response['external_payment_id'] ?? $response['provider_reference'] ?? null;
        $rawToStore = $response['raw'] ?? $response['raw_response'] ?? $response;
        if (isset($response['expires_at']) && is_string($response['expires_at'])) {
            $rawToStore = array_merge(is_array($rawToStore) ? $rawToStore : [], ['expires_at' => $response['expires_at']]);
        }
        $rawToStore = array_merge(is_array($rawToStore) ? $rawToStore : [], [
            'gateway_request_reference' => $gatewayRequestReference,
            'merchant_reference' => $data['reference'],
        ]);

        DB::transaction(function () use ($payment, $externalPaymentId, $rawToStore): void {
            $payment->update([
                'provider_reference' => $externalPaymentId,
                'status' => self::STATUS_PENDING,
                'raw_response' => $rawToStore,
            ]);
        });

        $qrData = $response['qr_data'] ?? $response['qr_string'] ?? $response['qr_image'] ?? null;
        $qrData = is_string($qrData) && $qrData !== '' ? $qrData : null;
        $expiresAt = $response['expires_at'] ?? null;
        $expiresAt = is_string($expiresAt) && $expiresAt !== '' ? $expiresAt : null;
        $redirectUrl = $response['redirect_url'] ?? $response['checkout_url'] ?? $response['checkoutUrl'] ?? $response['url'] ?? null;
        $redirectUrl = is_string($redirectUrl) && $redirectUrl !== '' ? $redirectUrl : null;

        return [
            'payment' => $payment->refresh(),
            'qr_data' => $qrData,
            'expires_at' => $expiresAt,
            'redirect_url' => $redirectUrl,
        ];
    }

    /**
     * @param  array{amount: float|int|string, currency: string, reference: string, product_name?: string|null}  $data
     * @return array{payment: Payment, qr_data: null, expires_at: null, redirect_url: string|null}
     */
    private function createCoinsCheckoutPayment(Merchant $merchant, string $gatewayCode, array $data): array
    {
        if ($gatewayCode !== 'coins') {
            throw new GatewayException('Checkout flow is only available for gateway "coins".');
        }

        $driver = $this->gatewayManager->resolve($merchant, $gatewayCode);
        if (! $driver instanceof CoinsDriver) {
            throw new GatewayException('Checkout flow requires the Coins gateway driver.');
        }

        $gatewayRequestReference = $this->buildGatewayRequestReference($merchant);

        $payment = DB::transaction(fn (): Payment => Payment::query()->create([
            'merchant_id' => $merchant->id,
            'gateway_code' => $gatewayCode,
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'reference_id' => $data['reference'],
            'provider_reference' => null,
            'status' => self::STATUS_PROVISIONING,
            'raw_response' => [
                'gateway_request_reference' => $gatewayRequestReference,
                'merchant_reference' => $data['reference'],
                'checkout' => true,
            ],
            'paid_at' => null,
        ]));

        $redirectUrls = $this->buildCheckoutRedirectUrls($payment);

        $productName = $data['product_name'] ?? null;
        $productName = is_string($productName) && trim($productName) !== '' ? trim($productName) : null;

        $sessionPayload = [
            'reference' => $gatewayRequestReference,
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'merchant_name' => $merchant->getDisplayName(),
            'redirect_urls' => $redirectUrls,
        ];
        if ($productName !== null) {
            $sessionPayload['product_name'] = $productName;
        }

        try {
            $response = $driver->createCheckoutSession($sessionPayload);
        } catch (Throwable $e) {
            $this->markProvisioningFailed(
                $payment,
                $gatewayRequestReference,
                $data['reference'],
                $e,
                ['checkout' => true]
            );

            throw $e;
        }

        $externalPaymentId = $response['external_payment_id'] ?? null;
        $rawToStore = $response['raw'] ?? [];
        $rawToStore = array_merge(is_array($rawToStore) ? $rawToStore : [], [
            'gateway_request_reference' => $gatewayRequestReference,
            'merchant_reference' => $data['reference'],
            'checkout' => true,
        ]);

        DB::transaction(function () use ($payment, $externalPaymentId, $rawToStore): void {
            $payment->update([
                'provider_reference' => $externalPaymentId,
                'status' => self::STATUS_PENDING,
                'raw_response' => $rawToStore,
            ]);
        });

        $redirectUrl = $response['redirect_url'] ?? null;
        $redirectUrl = is_string($redirectUrl) && $redirectUrl !== '' ? $redirectUrl : null;

        return [
            'payment' => $payment->refresh(),
            'qr_data' => null,
            'expires_at' => null,
            'redirect_url' => $redirectUrl,
        ];
    }

    /**
     * Transition a provisioning Payment to the failed state while preserving audit
     * context. The row is never deleted so every gateway call has a DB record.
     *
     * @param  array<string, mixed>  $extraContext
     */
    private function markProvisioningFailed(
        Payment $payment,
        string $gatewayRequestReference,
        string $merchantReference,
        Throwable $error,
        array $extraContext = []
    ): void {
        DB::transaction(function () use ($payment, $gatewayRequestReference, $merchantReference, $error, $extraContext): void {
            $existing = is_array($payment->raw_response) ? $payment->raw_response : [];

            $payment->update([
                'status' => self::STATUS_PROVISIONING_FAILED,
                'raw_response' => array_merge($existing, $extraContext, [
                    'gateway_request_reference' => $gatewayRequestReference,
                    'merchant_reference' => $merchantReference,
                    'error' => [
                        'type' => $error::class,
                        'message' => $error->getMessage(),
                    ],
                ]),
            ]);
        });
    }

    /**
     * @return array{success: string, failure: string, cancel: string, default: string}
     */
    private function buildCheckoutRedirectUrls(Payment $payment): array
    {
        $id = $payment->getKey();
        if (! is_string($id) || $id === '') {
            throw new GatewayException('Invalid payment identifier for checkout redirects.');
        }

        return [
            'success' => route('payment.success', ['transaction' => $id], absolute: true),
            'failure' => route('payment.failure', ['transaction' => $id], absolute: true),
            'cancel' => route('payment.cancel', ['transaction' => $id], absolute: true),
            'default' => route('payment.default', ['transaction' => $id], absolute: true),
        ];
    }

    private function buildGatewayRequestReference(Merchant $merchant): string
    {
        return sprintf('GH-%d-%s', $merchant->id, Str::upper((string) Str::ulid()));
    }
}
