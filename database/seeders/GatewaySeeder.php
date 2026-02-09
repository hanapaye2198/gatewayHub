<?php

namespace Database\Seeders;

use App\Models\Gateway;
use Illuminate\Database\Seeder;

class GatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gateways = [
            [
                'code' => 'coins',
                'name' => 'Coins.ph',
                'driver_class' => 'App\Services\Gateways\Drivers\CoinsDriver',
            ],
            [
                'code' => 'gcash',
                'name' => 'Gcash',
                'driver_class' => 'App\Services\Gateways\Drivers\GcashDriver',
            ],
            [
                'code' => 'maya',
                'name' => 'Maya',
                'driver_class' => 'App\Services\Gateways\Drivers\MayaDriver',
            ],
            [
                'code' => 'paypal',
                'name' => 'PayPal',
                'driver_class' => 'App\Services\Gateways\Drivers\PaypalDriver',
            ],
        ];

        foreach ($gateways as $gateway) {
            Gateway::updateOrCreate(
                ['code' => $gateway['code']],
                [
                    'name' => $gateway['name'],
                    'driver_class' => $gateway['driver_class'],
                    'is_global_enabled' => true,
                ]
            );
        }
    }
}
