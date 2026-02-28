<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Billing\PlatformFeeService;
use App\Services\Billing\WalletSettlementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentReversalEffectsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public string $paymentId, public string $reason) {}

    /**
     * Execute the job.
     */
    public function handle(PlatformFeeService $platformFeeService, WalletSettlementService $walletSettlementService): void
    {
        $payment = Payment::query()->find($this->paymentId);
        if ($payment === null) {
            return;
        }

        $platformFeeService->reverseForPayment($payment, $this->reason);
        $walletSettlementService->reverseFromTunnel($payment, $this->reason);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessPaymentReversalEffectsJob failed', [
            'payment_id' => $this->paymentId,
            'reason' => $this->reason,
            'error' => $exception->getMessage(),
        ]);
    }
}
