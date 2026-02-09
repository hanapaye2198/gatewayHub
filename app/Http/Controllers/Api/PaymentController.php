<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreatePaymentRequest;
use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\Payment;
use App\Services\Gateways\Exceptions\GatewayException;
use App\Services\Gateways\GatewayCapability;
use App\Services\Gateways\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Create a payment via the specified gateway.
     * Persists Coins.ph (and other gateway) response: provider_reference, status, full raw_response.
     * For QR-based gateways (e.g. coins), includes qr in the API response.
     */
    public function create(CreatePaymentRequest $request, PaymentGatewayManager $manager): JsonResponse
    {
        $merchant = $request->merchant();

        if ($merchant === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $gateway = Gateway::query()->where('code', $request->validated('gateway'))->first();

        if ($gateway === null) {
            return response()->json(['message' => 'Gateway not found.'], 404);
        }

        $merchantGateway = MerchantGateway::query()
            ->where('user_id', $merchant->id)
            ->where('gateway_id', $gateway->id)
            ->where('is_enabled', true)
            ->first();

        if ($merchantGateway === null) {
            return response()->json(['message' => 'Gateway is not enabled for this merchant.'], 403);
        }

        $gatewayCode = $request->validated('gateway');

        try {
            $result = DB::transaction(function () use ($request, $manager, $merchantGateway, $merchant, $gatewayCode) {
                $driver = $manager->getDriver($gatewayCode, $merchantGateway->config_json ?? []);
                $data = [
                    'amount' => $request->validated('amount'),
                    'currency' => $request->validated('currency'),
                    'reference' => $request->validated('reference'),
                ];
                $driverResponse = $driver->createPayment($data);

                $payment = Payment::query()->create([
                    'user_id' => $merchant->id,
                    'gateway_code' => $gatewayCode,
                    'amount' => $request->validated('amount'),
                    'currency' => $request->validated('currency'),
                    'reference_id' => $request->validated('reference'),
                    'provider_reference' => $driverResponse['provider_reference'] ?? $driverResponse['reference_id'] ?? null,
                    'status' => 'pending',
                    'raw_response' => $driverResponse['raw_response'] ?? $driverResponse,
                    'paid_at' => null,
                ]);

                return [
                    'payment' => $payment,
                    'driver_response' => $driverResponse,
                ];
            });
        } catch (GatewayException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $payment = $result['payment'];
        $driverResponse = $result['driver_response'];

        $capability = $gateway->getCapability();

        $paymentPayload = [
            'id' => $payment->id,
            'reference_id' => $payment->reference_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'gateway' => $payment->gateway_code,
            'capability' => $capability->value,
            'status' => $payment->status,
        ];

        if ($capability === GatewayCapability::QR && $this->hasQrData($driverResponse)) {
            $paymentPayload['qr'] = $this->buildQrPayload($driverResponse);
        }

        return response()->json([
            'message' => 'Payment created.',
            'payment' => $paymentPayload,
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $driverResponse
     */
    private function hasQrData(array $driverResponse): bool
    {
        return isset($driverResponse['qr_string']) || isset($driverResponse['qr_image']);
    }

    /**
     * @param  array<string, mixed>  $driverResponse
     * @return array{type: string, value: string, expires_at: string}
     */
    private function buildQrPayload(array $driverResponse): array
    {
        $value = $driverResponse['qr_string']
            ?? $driverResponse['qr_image']
            ?? '';

        $expiresAt = isset($driverResponse['expires_at'])
            ? Carbon::parse($driverResponse['expires_at'])->format('c')
            : Carbon::now()->addSeconds(1800)->format('c');

        return [
            'type' => 'qrph',
            'value' => $value,
            'expires_at' => $expiresAt,
        ];
    }
}
