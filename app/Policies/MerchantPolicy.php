<?php

namespace App\Policies;

use App\Models\Merchant;
use App\Models\User;

class MerchantPolicy
{
    /**
     * Determine whether the user can update the merchant.
     */
    public function update(User $user, Merchant $merchant): bool
    {
        if ($user->role === User::ROLE_ADMIN) {
            return true;
        }

        return $user->merchant_id !== null
            && (int) $user->merchant_id === (int) $merchant->id;
    }
}
