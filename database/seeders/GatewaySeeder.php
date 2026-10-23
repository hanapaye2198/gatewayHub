<?php

namespace Database\Seeders;

use App\Models\Gateway;
use App\Services\Gateways\Drivers\PayMongoDriver;
use Illuminate\Database\Seeder;

class GatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gateways = [
            ['code' => 'paymongo', 'name' => 'PayMongo', 'driver_class' => PayMongoDriver::class],
            ['code' => 'gcash', 'name' => 'GCash', 'driver_class' => PayMongoDriver::class],
            ['code' => 'maya', 'name' => 'Maya', 'driver_class' => PayMongoDriver::class],
            ['code' => 'grab_pay', 'name' => 'GrabPay', 'driver_class' => PayMongoDriver::class],
            ['code' => 'shopeepay', 'name' => 'ShopeePay', 'driver_class' => PayMongoDriver::class],
            ['code' => 'qrph', 'name' => 'QR Ph', 'driver_class' => PayMongoDriver::class],
        ];

        foreach ($gateways as $gateway) {
            $model = Gateway::query()->firstOrNew(['code' => $gateway['code']]);
            $isNewGateway = ! $model->exists;
            $model->fill([
                'name' => $gateway['name'],
                'driver_class' => $gateway['driver_class'],
                'is_global_enabled' => $isNewGateway,
            ]);
            $model->save();
        }

        $legacyCoinsGateway = Gateway::query()->where('code', 'coins')->first();
        if ($legacyCoinsGateway instanceof Gateway) {
            $legacyCoinsGateway->update(['is_global_enabled' => false]);
            $legacyCoinsGateway->merchantGateways()->update(['is_enabled' => false]);
        }
    }
}
