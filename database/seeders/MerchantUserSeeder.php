<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MerchantUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'merchant@example.com'],
            [
                'name' => 'Demo Merchant',
                'password' => Hash::make('password'),
                'role' => User::ROLE_MERCHANT_USER,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        if ($user->merchant_id === null) {
            Merchant::provisionForUser($user);
            $user->refresh();
            $user->forceFill([
                'onboarding_gateways_at' => now(),
                'onboarding_completed_at' => now(),
            ])->save();
        }

        $merchant = $user->merchant ?? Merchant::query()->find($user->merchant_id);
        if ($merchant !== null) {
            $merchant->forceFill([
                'api_key' => Str::random(64),
                'is_active' => true,
            ])->save();
        }
    }
}
