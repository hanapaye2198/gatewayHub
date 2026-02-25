<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\QrCodeGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PaymentDetailController extends Controller
{
    public function __invoke(Payment $payment, QrCodeGenerator $qrGenerator): View|Response
    {
        if ($payment->user_id !== auth()->id()) {
            abort(404);
        }

        $payment->load(['gateway', 'webhookEvents']);

        $qrData = $payment->getQrData();
        $qrImageUrl = null;
        if ($qrData !== null) {
            if ($qrData['type'] === 'image') {
                $qrImageUrl = $qrData['value'];
            } else {
                $qrImageUrl = $qrGenerator->toDataUri($qrData['value'])
                    ?? 'https://api.qrserver.com/v1/create-qr-code/?size=256x256&data='.urlencode($qrData['value']);
            }
        }

        $expiresAt = $payment->getExpiresAt();

        return view('dashboard.payment-detail', [
            'payment' => $payment,
            'qrImageUrl' => $qrImageUrl,
            'expiresAt' => $expiresAt,
        ]);
    }

    /**
     * Return payment status for polling. Same format as API; uses session auth.
     * Marks expired pending payments as failed.
     */
    public function status(Payment $payment): JsonResponse
    {
        if ($payment->user_id !== auth()->id()) {
            abort(404);
        }

        if ($payment->status === 'pending') {
            $expiresAt = $payment->getExpiresAt();
            if ($expiresAt !== null && now()->isAfter($expiresAt)) {
                $payment->update(['status' => 'failed']);
            }
        }

        $status = match ($payment->status) {
            'paid' => 'success',
            'failed' => 'failed',
            default => 'pending',
        };

        return response()->json(['status' => $status]);
    }
}
