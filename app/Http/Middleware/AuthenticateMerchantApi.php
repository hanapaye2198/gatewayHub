<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMerchantApi
{
    /**
     * Handle an incoming request. Find user by Authorization Bearer token (api_key), ensure is_active, attach as 'merchant'.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return $this->unauthorized();
        }

        $user = User::query()->where('api_key', $token)->first();

        if ($user === null || ! $user->is_active) {
            return $this->unauthorized();
        }

        $request->attributes->set('merchant', $user);

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}
