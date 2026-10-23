<?php

use App\Models\Gateway;
use App\Services\Gateways\Drivers\CoinsDriver;
use App\Services\Gateways\Drivers\PayMongoDriver;
use App\Services\Gateways\Drivers\PaypalDriver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Replace the Coins payment gateway with PayMongo's supported payment methods.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $qrph = Gateway::query()->where('code', 'qrph')->first();
            $paymongo = Gateway::query()->firstOrNew(['code' => 'paymongo']);
            $legacyConfig = $qrph?->config_json;
            $currentConfig = is_array($paymongo->config_json) ? $paymongo->config_json : [];

            if (is_array($legacyConfig)) {
                $currentConfig = array_merge($legacyConfig, $currentConfig);
            }

            $paymongo->fill([
                'name' => 'PayMongo',
                'driver_class' => PayMongoDriver::class,
                'is_global_enabled' => true,
                'config_json' => $currentConfig,
            ]);
            $paymongo->save();

            if ($qrph instanceof Gateway) {
                $qrph->config_json = [];
                $qrph->save();
            }

            $paymentMethods = [
                'gcash' => ['GCash', true],
                'maya' => ['Maya', true],
                'grab_pay' => ['GrabPay', false],
                'shopeepay' => ['ShopeePay', false],
                'qrph' => ['QR Ph', true],
            ];

            foreach ($paymentMethods as $code => [$name, $enabledByDefault]) {
                $gateway = Gateway::query()->firstOrNew(['code' => $code]);
                $isNewGateway = ! $gateway->exists;
                $gateway->fill([
                    'name' => $name,
                    'driver_class' => PayMongoDriver::class,
                    'is_global_enabled' => $isNewGateway ? $enabledByDefault : $gateway->is_global_enabled,
                ]);
                $gateway->save();
            }

            Gateway::query()->where('code', 'paypal')->update([
                'name' => 'PayPal',
                'driver_class' => PaypalDriver::class,
            ]);

            $legacyCoinsGateway = Gateway::query()->where('code', 'coins')->first();
            if ($legacyCoinsGateway instanceof Gateway) {
                $legacyCoinsGateway->update(['is_global_enabled' => false]);
                $legacyCoinsGateway->merchantGateways()->update(['is_enabled' => false]);
            }
        });
    }

    /**
     * Restore the prior gateway configuration for a rollback.
     */
    public function down(): void
    {
        DB::transaction(function (): void {
            $paymongo = Gateway::query()->where('code', 'paymongo')->first();
            $qrph = Gateway::query()->where('code', 'qrph')->first();

            if ($paymongo instanceof Gateway && $qrph instanceof Gateway) {
                $qrph->config_json = is_array($paymongo->config_json) ? $paymongo->config_json : [];
                $qrph->name = 'QR Ph (PayMongo)';
                $qrph->driver_class = 'App\\Services\\Gateways\\Drivers\\QrphDriver';
                $qrph->save();
            }

            Gateway::query()->whereIn('code', ['paymongo', 'grab_pay', 'shopeepay'])->delete();

            Gateway::query()->where('code', 'gcash')->update([
                'name' => 'Gcash',
                'driver_class' => 'App\\Services\\Gateways\\Drivers\\GcashDriver',
            ]);
            Gateway::query()->where('code', 'maya')->update([
                'driver_class' => 'App\\Services\\Gateways\\Drivers\\MayaDriver',
            ]);
            Gateway::query()->where('code', 'coins')->update([
                'driver_class' => CoinsDriver::class,
                'is_global_enabled' => true,
            ]);
        });
    }
};
