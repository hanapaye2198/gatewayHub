<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMerchantApi
{
    /**
     * Handle an incoming request. Find user by Authorization Bearer token hash.
     * Returns 401 for invalid/missing key, 403 for inactive merchant.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $tokenHash = hash('sha256', $token);
        $user = User::query()
            ->where('api_key_hash', $tokenHash)
            ->first();

        if ($user === null) {
            return ApiResponse::error('Invalid API key.', 401);
        }

        if (! $user->is_active) {
            return ApiResponse::error('Merchant account is inactive.', 403);
        }

        $request->attributes->set('merchant', $user);

        return $next($request);
    }
}
