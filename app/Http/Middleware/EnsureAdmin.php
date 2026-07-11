<?php

namespace App\Http\Middleware;

use App\Http\Responses\Fortify\PostLoginRedirect;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Ensure the authenticated user has the admin role.
     * Non-admins are redirected to their own dashboard (merchants) instead of a 403.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403, __('You do not have permission to access the admin panel.'));
        }

        if ($user->role !== User::ROLE_ADMIN) {
            if ($request->expectsJson()) {
                abort(403, __('You do not have permission to access the admin panel.'));
            }

            return new RedirectResponse(PostLoginRedirect::path($user));
        }

        return $next($request);
    }
}
