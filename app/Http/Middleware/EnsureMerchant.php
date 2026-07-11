<?php

namespace App\Http\Middleware;

use App\Http\Responses\Fortify\PostLoginRedirect;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMerchant
{
    /**
     * Ensure the authenticated user is a merchant account user with an active linked merchant.
     * Admins are redirected to the admin panel instead of a 403.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->role !== \App\Models\User::ROLE_MERCHANT_USER) {
            if ($user !== null && $user->role === \App\Models\User::ROLE_ADMIN && ! $request->expectsJson()) {
                return new RedirectResponse(PostLoginRedirect::path($user));
            }

            abort(403, __('Merchant dashboard access is limited to merchant accounts.'));
        }

        $merchant = $user->merchant;
        if ($merchant === null || ! $merchant->is_active) {
            abort(403, __('This merchant account is inactive. Contact support for assistance.'));
        }

        if (! $user->is_active) {
            abort(403, __('Your account has been deactivated.'));
        }

        $request->attributes->set('current_merchant', $merchant);

        return $next($request);
    }
}
