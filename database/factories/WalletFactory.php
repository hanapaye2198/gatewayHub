<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'wallet_type' => Wallet::TYPE_MERCHANT_REAL,
            'currency' => 'PHP',
            'balance' => 0,
        ];
    }
}
