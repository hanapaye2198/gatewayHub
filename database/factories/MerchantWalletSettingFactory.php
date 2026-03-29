<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\MerchantWalletSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MerchantWalletSetting>
 */
class MerchantWalletSettingFactory extends Factory
{
    protected $model = MerchantWalletSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'tunnel_wallet_enabled' => true,
            'auto_settle_to_real_wallet' => true,
            'default_currency' => 'PHP',
            'tunnel_client_id' => 'tunnel-client-id',
            'tunnel_client_secret' => 'tunnel-client-secret',
            'tunnel_webhook_id' => 'tunnel-webhook-id',
            'notes' => null,
        ];
    }
}
