<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformAuditLog>
 */
class PlatformAuditLogFactory extends Factory
{
    protected $model = PlatformAuditLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_user_id' => User::factory()->admin(),
            'action' => PlatformAuditLog::ACTION_MERCHANT_CREATED,
            'merchant_id' => Merchant::factory(),
            'target_type' => 'Merchant',
            'target_id' => (string) fake()->numberBetween(1, 1000),
            'description' => 'Created merchant '.fake()->company().'.',
            'old_values' => null,
            'new_values' => ['name' => fake()->company(), 'is_active' => true],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => now(),
        ];
    }
}
