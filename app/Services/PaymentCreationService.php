<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\Payment;
use App\Services\Gateways\Drivers\CoinsDriver;
use App\Services\Gateways\Exceptions\GatewayException;
use App\Services\Gateways\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

        $response = $driver->createPayment([
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'reference' => $gatewayRequestReference,
            'qr_code_merchant_name' => $merchant->getQrMerchantName(),
        ]);

        $externalPaymentId = $response['external_payment_id'] ?? $response['provider_reference'] ?? null;
        $rawToStore = $response['raw'] ?? $response['raw_response'] ?? $response;
        if (isset($response['expires_at']) && is_string($response['expires_at'])) {
            $rawToStore = array_merge(is_array($rawToStore) ? $rawToStore : [], ['expires_at' => $response['expires_at']]);
        }
        $rawToStore = array_merge(is_array($rawToStore) ? $rawToStore : [], [
            'gateway_request_reference' => $gatewayRequestReference,
            'merchant_reference' => $data['reference'],
        ]);

        $payment = DB::transaction(function () use ($merchant, $gatewayCode, $data, $externalPaymentId, $rawToStore) {
            return Payment::query()->create([
                'merchant_id' => $merchant->id,
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
        $redirectUrl = $response['redirect_url'] ?? $response['checkout_url'] ?? $response['checkoutUrl'] ?? $response['url'] ?? null;
        $redirectUrl = is_string($redirectUrl) && $redirectUrl !== '' ? $redirectUrl : null;

        return [
            'payment' => $payment,
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

        return DB::transaction(function () use ($merchant, $gatewayCode, $data, $driver, $gatewayRequestReference): array {
            $payment = Payment::query()->create([
                'merchant_id' => $merchant->id,
                'gateway_code' => $gatewayCode,
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'reference_id' => $data['reference'],
                'provider_reference' => null,
                'status' => 'pending',
                'raw_response' => [
                    'gateway_request_reference' => $gatewayRequestReference,
                    'merchant_reference' => $data['reference'],
                    'checkout' => true,
                ],
            ]);

            $redirectUrls = $this->buildCheckoutRedirectUrls($payment);

            $productName = $data['product_name'] ?? null;
            $productName = is_string($productName) && trim($productName) !== '' ? trim($productName) : null;

            try {
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

                $response = $driver->createCheckoutSession($sessionPayload);
            } catch (\Throwable $e) {
                $payment->delete();

                throw $e;
            }

            $externalPaymentId = $response['external_payment_id'] ?? null;
            $rawToStore = $response['raw'] ?? [];
            $rawToStore = array_merge(is_array($rawToStore) ? $rawToStore : [], [
                'gateway_request_reference' => $gatewayRequestReference,
                'merchant_reference' => $data['reference'],
                'checkout' => true,
            ]);

            $payment->update([
                'provider_reference' => $externalPaymentId,
                'raw_response' => $rawToStore,
            ]);

            $redirectUrl = $response['redirect_url'] ?? null;
            $redirectUrl = is_string($redirectUrl) && $redirectUrl !== '' ? $redirectUrl : null;

            return [
                'payment' => $payment->fresh(),
                'qr_data' => null,
                'expires_at' => null,
                'redirect_url' => $redirectUrl,
            ];
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
