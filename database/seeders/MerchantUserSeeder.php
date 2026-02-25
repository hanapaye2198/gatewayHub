<?php

namespace Database\Seeders;

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
        User::query()->firstOrCreate(
            ['email' => 'merchant@example.com'],
            [
                'name' => 'Demo Merchant',
                'password' => Hash::make('password'),
                'role' => 'merchant',
                'email_verified_at' => now(),
                'is_active' => true,
                'api_key' => Str::random(64),
            ]
        );
    }
}
