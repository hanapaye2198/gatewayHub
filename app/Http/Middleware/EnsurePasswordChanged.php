<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Send users whose password was reset to the default password to the password settings page.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, __('You must change your password before continuing.'));
        }

        return redirect()
            ->route('user-password.edit')
            ->with('status', __('Your password was reset. Please set a new password to continue.'));
    }
}
