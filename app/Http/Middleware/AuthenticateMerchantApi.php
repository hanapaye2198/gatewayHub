<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\Merchant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMerchantApi
{
    /**
     * Resolve {@see Merchant} from Authorization Bearer token (api_key hash on merchants table).
     * Returns 401 for invalid/missing key, 403 for inactive merchant.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $tokenHash = hash('sha256', $token);
        $merchant = Merchant::query()
            ->where('api_key_hash', $tokenHash)
            ->first();

        if ($merchant === null) {
            return ApiResponse::error('Invalid API key.', 401);
        }

        if (! $merchant->is_active) {
            return ApiResponse::error('Merchant account is inactive.', 403);
        }

        $request->attributes->set('merchant', $merchant);

        return $next($request);
    }
}
