<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Merchant;
use App\Models\Payment;
use App\Services\PaymentStatusSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentStatusController extends Controller
{
    /**
     * Return payment status. Lightweight, no joins. Checks merchant ownership.
     */
    public function __invoke(Request $request, string $id, PaymentStatusSyncService $paymentStatusSyncService): JsonResponse
    {
        $merchant = $request->merchant();
        if (! $merchant instanceof Merchant) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $payment = Payment::query()
            ->where('id', $id)
            ->where('merchant_id', $merchant->id)
            ->first();

        if ($payment === null) {
            return ApiResponse::error('Payment not found.', 404);
        }

        if ($payment->status === 'pending') {
            $paymentStatusSyncService->syncPendingPayment($payment);
            $payment->refresh();

            if ($payment->status === 'pending') {
                $expiresAt = $payment->getExpiresAt();
                if ($expiresAt !== null && now()->isAfter($expiresAt)) {
                    $payment->update(['status' => 'failed']);
                }
            }
        }

        $status = match ($payment->status) {
            'paid' => 'success',
            'failed' => 'failed',
            default => 'pending',
        };

        return ApiResponse::success([
            'status' => $status,
            'merchant' => $merchant->brandingForApi(),
        ]);
    }
}
