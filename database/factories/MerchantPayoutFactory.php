<?php

namespace Database\Factories;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MerchantPayout>
 */
class MerchantPayoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'provider' => 'instapay',
            'amount' => fake()->randomFloat(2, 1, 10000),
            'currency' => 'PHP',
            'destination_account' => [
                'number' => fake()->numerify('################'),
                'name' => fake()->name(),
                'bic' => 'BNORPHMM',
                'bank_name' => 'Test Bank',
            ],
            'status' => 'pending',
            'reference_number' => fake()->unique()->bothify('KP##########'),
            'idempotency_key' => fake()->unique()->uuid(),
            'request_fingerprint' => hash('sha256', fake()->uuid()),
            'paymongo_batch_transfer_id' => null,
            'paymongo_transfer_id' => null,
            'provider_reference_number' => null,
            'provider_fee' => null,
            'failure_code' => null,
            'failure_message' => null,
            'submission_started_at' => now(),
            'completed_at' => null,
            'funds_released_at' => null,
        ];
    }
}
