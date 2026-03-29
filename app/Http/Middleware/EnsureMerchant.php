<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMerchant
{
    /**
     * Ensure the authenticated user is a merchant account user with an active linked merchant.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->role !== \App\Models\User::ROLE_MERCHANT_USER) {
            abort(403);
        }

        $merchant = $user->merchant;
        if ($merchant === null || ! $merchant->is_active) {
            abort(403);
        }

        if (! $user->is_active) {
            abort(403);
        }

        $request->attributes->set('current_merchant', $merchant);

        return $next($request);
    }
}
