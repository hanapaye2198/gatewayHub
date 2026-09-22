<?php

namespace App\Policies;

use App\Models\Merchant;
use App\Models\User;

class MerchantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformOperator();
    }

    public function view(User $user, Merchant $merchant): bool
    {
        return $user->isPlatformOperator();
    }

    public function create(User $user): bool
    {
        return $user->isPlatformOperator();
    }

    /**
     * Super Admin opens a temporary merchant-dashboard context.
     */
    public function access(User $user, Merchant $merchant): bool
    {
        return $user->isPlatformOperator();
    }

    /**
     * Super Admin manages the users that belong to a merchant.
     */
    public function manageUsers(User $user, Merchant $merchant): bool
    {
        return $user->isPlatformOperator();
    }

    /**
     * Determine whether the user can update the merchant.
     */
    public function update(User $user, Merchant $merchant): bool
    {
        if ($user->isPlatformOperator()) {
            return true;
        }

        return $user->merchant_id !== null
            && (int) $user->merchant_id === (int) $merchant->id;
    }
}
