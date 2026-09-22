<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Support\MerchantContext;

class PaymentPolicy
{
    /**
     * Determine whether the user can view the payment.
     */
    public function view(User $user, Payment $payment): bool
    {
        if ($user->isPlatformOperator()) {
            $merchantId = app(MerchantContext::class)->id();

            return $merchantId !== null
                && (int) $merchantId === (int) $payment->merchant_id;
        }

        return $user->merchant_id !== null
            && (int) $user->merchant_id === (int) $payment->merchant_id;
    }
}
