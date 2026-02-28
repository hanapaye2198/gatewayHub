<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreatePaymentRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Gateways\Exceptions\GatewayException;
use App\Services\IdempotencyService;
use App\Services\PaymentCreationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class PaymentController extends Controller
{
    /**
     * Create a payment via the specified gateway. Supports Idempotency-Key header.
     */
    public function create(
        CreatePaymentRequest $request,
        PaymentCreationService $creationService,
        IdempotencyService $idempotencyService
    ): JsonResponse {
        $merchant = $request->merchant();

        if ($merchant === null) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $idempotencyKey = $request->header('Idempotency-Key');
        $cacheKey = null;
        if ($idempotencyKey !== null && trim($idempotencyKey) !== '') {
            $idempotencyKey = trim($idempotencyKey);
            $cacheKey = "{$merchant->id}:{$idempotencyKey}";
            $cached = $idempotencyService->get($cacheKey);
            if ($cached !== null) {
                return ApiResponse::success($cached, 201);
            }
        }

        $lockKey = $cacheKey ?? 'payment:'.uniqid('', true);
        $lock = Cache::lock('idempotency_lock:'.hash('sha256', $lockKey), 10);

        return $lock->block(5, function () use ($request, $merchant, $creationService, $idempotencyService, $cacheKey) {
            if ($cacheKey !== null) {
                $cached = $idempotencyService->get($cacheKey);
                if ($cached !== null) {
                    return ApiResponse::success($cached, 201);
                }
            }

            $gatewayCode = $request->validated('gateway');

            try {
                $result = $creationService->create($merchant, $gatewayCode, $request->validated());
            } catch (GatewayException $e) {
                $msg = $e->getMessage();
                $status = match (true) {
                    str_contains($msg, 'not found') => 404,
                    str_contains($msg, 'missing required') => 422,
                    default => 403,
                };

                return ApiResponse::error($msg, $status);
            }

            $payment = $result['payment'];
            $data = [
                'payment_id' => $payment->id,
                'gateway' => $payment->gateway_code,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'qr_data' => $result['qr_data'],
                'expires_at' => $result['expires_at'],
                'redirect_url' => $result['redirect_url'] ?? null,
            ];

            if ($cacheKey !== null) {
                $idempotencyService->store($cacheKey, $data);
            }

            return ApiResponse::success($data, 201);
        });
    }
}
