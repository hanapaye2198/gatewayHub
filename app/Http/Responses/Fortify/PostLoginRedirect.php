<?php

namespace App\Http\Responses\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class PostLoginRedirect
{
    /**
     * Return the URL to redirect to after successful login based on user role.
     * Unknown or missing role redirects to login.
     */
    public static function path(?Authenticatable $user): string
    {
        if ($user === null) {
            return route('login');
        }

        if ($user instanceof User && $user->isPlatformOperator()) {
            return route('admin.index', absolute: false);
        }

        if ($user instanceof User && $user->isMerchantUser()) {
            return $user->merchantOnboardingOrDashboardUrl();
        }

        return route('login');
    }
}
