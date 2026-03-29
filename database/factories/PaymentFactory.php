<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'gateway_code' => 'coins',
            'amount' => fake()->randomFloat(2, 10, 10000),
            'currency' => 'PHP',
            'reference_id' => 'ref-'.fake()->unique()->uuid(),
            'provider_reference' => fake()->unique()->uuid(),
            'status' => 'pending',
            'raw_response' => [],
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            $amount = (float) ($attributes['amount'] ?? 100);
            $percentage = config('platform.fees.percentage', 1.5);
            $fixed = config('platform.fees.fixed', 5);
            $fee = round(($amount * $percentage / 100) + $fixed, 2);
            $net = round($amount - $fee, 2);

            return [
                'status' => 'paid',
                'paid_at' => now(),
                'platform_fee' => $fee,
                'net_amount' => $net,
            ];
        });
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }
}
