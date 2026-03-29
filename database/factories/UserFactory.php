<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => User::ROLE_MERCHANT_USER,
            'is_active' => true,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->role === User::ROLE_MERCHANT_USER && $user->merchant_id === null) {
                Merchant::provisionForUser($user);
                $user->refresh();
                $user->forceFill([
                    'onboarding_gateways_at' => now(),
                    'onboarding_completed_at' => now(),
                ])->save();
            }
        });
    }

    /**
     * Merchant user with no linked merchant (e.g. onboarding tests).
     */
    public function withoutMerchant(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->merchant_id !== null) {
                $mid = $user->merchant_id;
                $user->forceFill([
                    'merchant_id' => null,
                    'onboarding_gateways_at' => null,
                    'onboarding_completed_at' => null,
                ])->save();
                Merchant::query()->where('id', $mid)->delete();
            }
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the user has the admin role.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_ADMIN,
            'merchant_id' => null,
        ]);
    }

    /**
     * Store API credentials on the linked {@see Merchant} (Bearer auth resolves merchants, not users).
     */
    public function withMerchantApiKey(string $apiKey): static
    {
        return $this->afterCreating(function (User $user) use ($apiKey): void {
            $user->merchant?->forceFill(['api_key' => $apiKey])->save();
        });
    }
}
