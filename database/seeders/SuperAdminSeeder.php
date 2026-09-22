<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the platform operator. The stored role remains admin.
     */
    public function run(): void
    {
        $password = config('auth.super_admin.password');

        if (! is_string($password) || $password === '') {
            throw new RuntimeException(
                'SUPER_ADMIN_PASSWORD is not set. Add it to the environment before running SuperAdminSeeder. The seeder does not use a default password.'
            );
        }

        $user = User::query()->firstOrNew([
            'email' => 'admin@example.com',
        ]);

        if (! $user->exists) {
            $user->name = 'Super Admin';
            $user->password = $password;
            $user->email_verified_at = now();
        }

        $user->forceFill([
            'role' => User::ROLE_ADMIN,
            'merchant_id' => null,
            'is_active' => true,
        ])->save();
    }
}
