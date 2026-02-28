<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    protected $model = WalletTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'payment_id' => Payment::factory(),
            'direction' => fake()->randomElement(['credit', 'debit']),
            'entry_type' => 'test_entry',
            'amount' => fake()->randomFloat(2, 1, 1000),
            'currency' => 'PHP',
            'metadata' => [],
            'is_settled' => false,
            'settled_at' => null,
        ];
    }
}
