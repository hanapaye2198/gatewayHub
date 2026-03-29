<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Gateway;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnabledGatewaysController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $merchant = $request->merchant();
        if ($merchant === null) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $gateways = Gateway::query()
            ->where('is_global_enabled', true)
            ->whereHas('merchantGateways', function (Builder $query) use ($merchant): void {
                $query
                    ->where('merchant_id', $merchant->id)
                    ->where('is_enabled', true);
            })
            ->orderBy('name')
            ->get(['code', 'name']);

        $gatewayRows = $gateways->map(static function (Gateway $gateway): array {
            return [
                'code' => $gateway->code,
                'name' => $gateway->name,
            ];
        })->values()->all();

        return ApiResponse::success([
            'gateways' => $gatewayRows,
            'count' => count($gatewayRows),
        ]);
    }
}
