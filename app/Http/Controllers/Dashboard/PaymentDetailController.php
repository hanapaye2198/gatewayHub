<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentDisplayStatusResolver;
use App\Services\PaymentStatusSyncService;
use App\Services\QrCodeGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class PaymentDetailController extends Controller
{
    public function __invoke(
        Payment $payment,
        QrCodeGenerator $qrGenerator,
        PaymentStatusSyncService $paymentStatusSyncService,
    ): View|Response|RedirectResponse {
        abort_unless(auth()->user()?->can('view', $payment), 404);

        if ($payment->status === 'paid') {
            return redirect()->route('dashboard.payments');
        }

        if ($payment->status === 'pending' && $payment->getQrData() === null) {
            $paymentStatusSyncService->syncPendingPayment($payment);
            $payment->refresh();
        }

        $payment->load([
            'gateway',
            'webhookEvents' => static fn (HasMany $query) => $query->orderByDesc('received_at'),
        ]);

        $qrData = $payment->getQrData();
        $qrImageUrl = null;
        if ($qrData !== null) {
            if ($qrData['type'] === 'image') {
                $qrImageUrl = $qrData['value'];
            } else {
                $qrImageUrl = $qrGenerator->toDataUri($qrData['value']);
            }
        }

        $expiresAt = $payment->getExpiresAt();
        $displayStatus = app(PaymentDisplayStatusResolver::class)->resolve($payment);

        return view('dashboard.payment-detail', [
            'payment' => $payment,
            'qrImageUrl' => $qrImageUrl,
            'expiresAt' => $expiresAt,
            'displayStatus' => $displayStatus,
        ]);
    }

    /**
     * Return payment status and GatewayHub-owned fee fields for polling. Uses session auth.
     * Marks expired pending payments as failed.
     */
    public function status(Payment $payment, PaymentStatusSyncService $paymentStatusSyncService): JsonResponse
    {
        abort_unless(auth()->user()?->can('view', $payment), 404);

        if (auth()->user()?->isPlatformOperator()) {
            return $this->statusPayload($payment);
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

        return $this->statusPayload($payment);
    }

    private function statusPayload(Payment $payment): JsonResponse
    {
        $status = match ($payment->status) {
            'paid' => 'success',
            'failed' => 'failed',
            default => 'pending',
        };

        return response()->json(array_merge([
            'status' => $status,
        ], $payment->gatewayHubFeeData()));
    }
}
