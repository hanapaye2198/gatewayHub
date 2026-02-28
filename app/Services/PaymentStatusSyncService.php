<?php

namespace App\Services;

use App\Models\Payment;

class PaymentStatusSyncService
{
    /**
     * Coins webhooks are the source of truth for payment status updates.
     * Dashboard/API polling reads stored status and expiry only.
     */
    public function syncPendingPayment(Payment $payment): void
    {
        // Intentionally no-op in Coins-orchestrated flow.
    }
}
