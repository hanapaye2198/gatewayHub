<?php

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Services\Gateways\PayMongoMethodCapabilityService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $legacyGatewayIds = Gateway::query()
            ->whereNotIn('code', array_merge(['paymongo'], PayMongoMethodCapabilityService::gatewayCodes()))
            ->pluck('id');

        Gateway::query()->whereIn('id', $legacyGatewayIds)->update(['is_global_enabled' => false]);
        MerchantGateway::query()->whereIn('gateway_id', $legacyGatewayIds)->update(['is_enabled' => false]);
    }

    public function down(): void {}
};
