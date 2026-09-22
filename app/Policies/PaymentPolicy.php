<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Determine whether the user can view the payment.
     */
    public function view(User $user, Payment $payment): bool
    {
        if ($user->isPlatformOperator()) {
            return true;
        }

        return $user->merchant_id !== null
            && (int) $user->merchant_id === (int) $payment->merchant_id;
    }
}
