<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Gateway;
use App\Services\Gateways\PayMongoMethodCapabilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnabledGatewaysController extends Controller
{
    public function __invoke(Request $request, PayMongoMethodCapabilityService $payMongoMethodCapabilityService): JsonResponse
    {
        $merchant = $request->merchant();
        if ($merchant === null) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $availablePayMongoMethods = $payMongoMethodCapabilityService->availableGatewayCodes();
        $gatewayQuery = Gateway::query()
            ->where('is_global_enabled', true)
            ->whereIn('code', PayMongoMethodCapabilityService::gatewayCodes())
            ->whereHas('merchantGateways', function (Builder $query) use ($merchant): void {
                $query
                    ->where('merchant_id', $merchant->id)
                    ->where('is_enabled', true);
            })
            ->whereIn('code', $availablePayMongoMethods ?? [])
            ->orderBy('name');
        $gateways = $gatewayQuery->get(['code', 'name']);

        $gatewayRows = $gateways->map(static function (Gateway $gateway): array {
            return [
                'code' => $gateway->code,
                'name' => $gateway->name,
            ];
        })->values()->all();

        $merchantData = $merchant->brandingForApi();
        $merchantData['id'] = (string) $merchant->id;

        return ApiResponse::success([
            'gateways' => $gatewayRows,
            'count' => count($gatewayRows),
            'merchant' => $merchantData,
            'provider_availability' => [
                'paymongo' => $payMongoMethodCapabilityService->summary(),
            ],
        ]);
    }
}
