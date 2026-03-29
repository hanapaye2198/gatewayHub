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

        $role = $user->role ?? null;

        if ($role === User::ROLE_ADMIN) {
            return url('/admin');
        }

        if ($role === User::ROLE_MERCHANT_USER) {
            return $user->merchantOnboardingOrDashboardUrl();
        }

        return route('login');
    }
}
