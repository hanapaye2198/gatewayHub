<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the platform operator. The stored role remains admin.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
                'role' => User::ROLE_ADMIN,
                'merchant_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
