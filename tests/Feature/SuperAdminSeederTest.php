<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_seeder_creates_a_platform_operator(): void
    {
        $this->seed(SuperAdminSeeder::class);

        $user = User::query()->where('email', 'admin@example.com')->first();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('Super Admin', $user->name);
        $this->assertSame(User::ROLE_ADMIN, $user->role);
        $this->assertTrue($user->isPlatformOperator());
        $this->assertNull($user->merchant_id);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertNull($user->merchant);
    }

    public function test_super_admin_seeder_does_not_create_a_duplicate(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $this->seed(SuperAdminSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'admin@example.com')->count());
    }

    public function test_admin_user_seeder_seeds_the_same_platform_operator(): void
    {
        $this->seed(AdminUserSeeder::class);

        $user = User::query()->where('email', 'admin@example.com')->first();

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($user->isPlatformOperator());
        $this->assertSame(1, User::query()->where('role', User::ROLE_ADMIN)->count());
    }
}
