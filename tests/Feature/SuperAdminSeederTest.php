<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    private const SEED_PASSWORD = 'seeder-test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.super_admin.password' => self::SEED_PASSWORD,
            'auth.super_admin.email' => 'admin@example.com',
        ]);
    }

    public function test_super_admin_seeder_creates_a_platform_operator(): void
    {
        $this->seed(SuperAdminSeeder::class);

        $user = User::query()->where('email', 'admin@example.com')->first();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('Super Admin', $user->name);
        $this->assertSame(User::ROLE_SUPER_ADMIN, $user->role);
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->isPlatformOperator());
        $this->assertFalse($user->isAdmin());
        $this->assertNull($user->merchant_id);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check(self::SEED_PASSWORD, $user->password));
        $this->assertNotSame(self::SEED_PASSWORD, $user->password);
        $this->assertNull($user->merchant);
    }

    public function test_super_admin_seeder_does_not_create_a_duplicate(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $this->seed(SuperAdminSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'admin@example.com')->count());
    }

    public function test_running_the_seeder_again_does_not_overwrite_an_existing_password(): void
    {
        $merchant = Merchant::factory()->create();
        $existing = User::factory()->create([
            'email' => 'admin@example.com',
            'name' => 'Existing Admin',
            'password' => 'already-chosen-secret',
            'role' => User::ROLE_MERCHANT_USER,
            'merchant_id' => $merchant->id,
            'is_active' => false,
        ]);
        $passwordHash = $existing->password;

        $this->seed(SuperAdminSeeder::class);
        $this->seed(SuperAdminSeeder::class);

        $existing->refresh();

        $this->assertSame(1, User::query()->where('email', 'admin@example.com')->count());
        $this->assertSame($passwordHash, $existing->password);
        $this->assertTrue(Hash::check('already-chosen-secret', $existing->password));
        $this->assertFalse(Hash::check(self::SEED_PASSWORD, $existing->password));
        $this->assertSame('Existing Admin', $existing->name);
        $this->assertSame(User::ROLE_SUPER_ADMIN, $existing->role);
        $this->assertNull($existing->merchant_id);
        $this->assertTrue($existing->is_active);
    }

    public function test_missing_password_configuration_fails_clearly(): void
    {
        foreach ([null, ''] as $password) {
            config(['auth.super_admin.password' => $password]);

            try {
                $this->seed(SuperAdminSeeder::class);
                $this->fail('Seeder should fail when SUPER_ADMIN_PASSWORD is missing.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('SUPER_ADMIN_PASSWORD', $exception->getMessage());
            }
        }

        $this->assertSame(0, User::query()->where('email', 'admin@example.com')->count());
    }

    public function test_admin_user_seeder_seeds_the_same_platform_operator(): void
    {
        $this->seed(AdminUserSeeder::class);

        $user = User::query()->where('email', 'admin@example.com')->first();

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->isPlatformOperator());
        $this->assertSame(User::ROLE_SUPER_ADMIN, $user->role);
        $this->assertNull($user->merchant_id);
        $this->assertTrue($user->is_active);
        $this->assertSame(0, User::query()->where('role', User::ROLE_ADMIN)->count());
        $this->assertSame(1, User::query()->where('role', User::ROLE_SUPER_ADMIN)->count());
    }

    public function test_seeder_does_not_promote_other_admin_accounts(): void
    {
        $other = User::factory()->admin()->create([
            'email' => 'ops@example.com',
        ]);

        $this->seed(SuperAdminSeeder::class);

        $other->refresh();
        $this->assertSame(User::ROLE_ADMIN, $other->role);
        $this->assertFalse($other->isSuperAdmin());
        $this->assertNull($other->merchant_id);
    }

    public function test_promotion_updates_only_the_designated_admin(): void
    {
        config(['auth.super_admin.email' => 'owner@example.com']);
        $merchant = Merchant::factory()->create();
        $owner = User::factory()->admin()->create([
            'email' => 'owner@example.com',
            'merchant_id' => $merchant->id,
            'password' => 'owner-secret',
        ]);
        $password = $owner->password;
        $other = User::factory()->admin()->create(['email' => 'ops@example.com']);
        $merchantUser = User::factory()->create();

        $this->assertSame(1, \App\Support\DesignatedPlatformOwner::promoteExistingAdmin());

        $owner->refresh();
        $this->assertSame(User::ROLE_SUPER_ADMIN, $owner->role);
        $this->assertNull($owner->merchant_id);
        $this->assertSame($password, $owner->password);
        $this->assertSame(User::ROLE_ADMIN, $other->refresh()->role);
        $this->assertSame(User::ROLE_MERCHANT_USER, $merchantUser->refresh()->role);
        $this->assertNotNull($merchantUser->merchant_id);
    }
}
