<?php

namespace Tests\Feature\Admin;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MerchantUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_only_the_selected_merchants_users(): void
    {
        $admin = User::factory()->admin()->create();
        [$merchantA, $userA, $merchantB, $userB] = $this->twoMerchants();

        $this->actingAs($admin)
            ->get(route('admin.merchants.show', $merchantA))
            ->assertOk()
            ->assertSee('Manage Users');

        $this->actingAs($admin)
            ->get(route('admin.merchants.users.index', $merchantA))
            ->assertOk()
            ->assertSee($userA->name)
            ->assertSee($userA->email)
            ->assertSee('Merchant user')
            ->assertSee('Active')
            ->assertDontSee($userB->email)
            ->assertDontSee($userB->name);

        $this->actingAs($admin)
            ->get(route('admin.merchants.users.index', $merchantB))
            ->assertOk()
            ->assertSee($userB->email)
            ->assertDontSee($userA->email);
    }

    public function test_super_admin_creates_an_active_merchant_user_for_the_selected_merchant(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = Merchant::factory()->create();
        $password = 'Secret-Pass-91';

        $this->actingAs($admin)
            ->post(route('admin.merchants.users.store', $merchant), [
                'name' => 'Maria Santos',
                'email' => 'maria@example.com',
                'password' => $password,
                'password_confirmation' => $password,
                'role' => User::ROLE_ADMIN,
                'merchant_id' => 999,
            ])
            ->assertRedirect(route('admin.merchants.users.index', $merchant));

        $user = User::query()->where('email', 'maria@example.com')->firstOrFail();

        $this->assertSame(User::ROLE_MERCHANT_USER, $user->role);
        $this->assertSame($merchant->id, $user->merchant_id);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->onboarding_completed_at);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertNotSame($password, $user->password);

        $this->actingAs($admin)
            ->get(route('admin.merchants.users.index', $merchant))
            ->assertOk()
            ->assertSee('maria@example.com')
            ->assertDontSee($password)
            ->assertDontSee($user->password);

        $this->actingAs($admin)
            ->get(route('admin.merchants.users.edit', [$merchant, $user]))
            ->assertOk()
            ->assertDontSee($password)
            ->assertDontSee($user->password);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_new_merchant_user_is_active_when_status_is_omitted(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = Merchant::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.merchants.users.store', $merchant), [
                'name' => 'Juan Dela Cruz',
                'email' => 'juan@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect(route('admin.merchants.users.index', $merchant));

        $this->assertTrue(User::query()->where('email', 'juan@example.com')->firstOrFail()->is_active);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = Merchant::factory()->create();
        $existing = User::factory()->create([
            'merchant_id' => $merchant->id,
            'email' => 'taken@example.com',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.merchants.users.create', $merchant))
            ->post(route('admin.merchants.users.store', $merchant), [
                'name' => 'Someone Else',
                'email' => $existing->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect(route('admin.merchants.users.create', $merchant))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::query()->where('email', 'taken@example.com')->count());
    }

    public function test_super_admin_can_edit_a_merchant_user_and_keep_their_email(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = Merchant::factory()->create();
        $user = User::factory()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
        ]);
        $originalPassword = $user->password;

        $this->actingAs($admin)
            ->put(route('admin.merchants.users.update', [$merchant, $user]), [
                'name' => 'Maria Santos Updated',
                'email' => 'maria@example.com',
                'password' => '',
                'password_confirmation' => '',
                'is_active' => '1',
                'role' => User::ROLE_ADMIN,
                'merchant_id' => 999,
            ])
            ->assertRedirect(route('admin.merchants.users.index', $merchant));

        $user->refresh();

        $this->assertSame('Maria Santos Updated', $user->name);
        $this->assertSame('maria@example.com', $user->email);
        $this->assertSame(User::ROLE_MERCHANT_USER, $user->role);
        $this->assertSame($merchant->id, $user->merchant_id);
        $this->assertSame($originalPassword, $user->password);
    }

    public function test_edit_rejects_another_users_email(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = Merchant::factory()->create();
        $user = User::factory()->create(['merchant_id' => $merchant->id]);
        $other = User::factory()->create(['merchant_id' => $merchant->id, 'email' => 'other@example.com']);

        $this->actingAs($admin)
            ->from(route('admin.merchants.users.edit', [$merchant, $user]))
            ->put(route('admin.merchants.users.update', [$merchant, $user]), [
                'name' => $user->name,
                'email' => $other->email,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.merchants.users.edit', [$merchant, $user]))
            ->assertSessionHasErrors('email');

        $this->assertNotSame($other->email, $user->refresh()->email);
        $this->assertSame('other@example.com', $other->refresh()->email);
    }

    public function test_optional_password_change_is_hashed(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = Merchant::factory()->create();
        $user = User::factory()->create(['merchant_id' => $merchant->id]);

        $this->actingAs($admin)
            ->put(route('admin.merchants.users.update', [$merchant, $user]), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'New-Secret-91',
                'password_confirmation' => 'New-Secret-91',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.merchants.users.index', $merchant));

        $user->refresh();

        $this->assertTrue(Hash::check('New-Secret-91', $user->password));
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_disabling_one_user_does_not_change_the_merchant_or_other_users(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = Merchant::factory()->create(['is_active' => true]);
        $maria = User::factory()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Maria Santos',
            'is_active' => true,
            'onboarding_gateways_at' => now(),
            'onboarding_completed_at' => now(),
        ]);
        $juan = User::factory()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Juan Dela Cruz',
            'is_active' => true,
            'onboarding_gateways_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.merchants.users.toggle', [$merchant, $maria]))
            ->assertRedirect(route('admin.merchants.users.index', $merchant));

        $this->assertFalse($maria->refresh()->is_active);
        $this->assertTrue($juan->refresh()->is_active);
        $this->assertTrue($merchant->refresh()->is_active);

        $this->actingAs($maria)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($juan)->get(route('dashboard'))->assertOk();

        $this->actingAs($admin)
            ->patch(route('admin.merchants.users.toggle', [$merchant, $maria]))
            ->assertRedirect(route('admin.merchants.users.index', $merchant));

        $this->assertTrue($maria->refresh()->is_active);
        $this->assertTrue($juan->refresh()->is_active);
        $this->assertTrue($merchant->refresh()->is_active);
        $this->actingAs($maria)->get(route('dashboard'))->assertOk();
    }

    public function test_cross_merchant_urls_cannot_read_or_change_another_merchants_user(): void
    {
        $admin = User::factory()->admin()->create();
        [$merchantA, $userA, $merchantB, $userB] = $this->twoMerchants();
        $before = $userB->only(['name', 'email', 'role', 'merchant_id', 'is_active', 'password']);

        $this->actingAs($admin);

        $this->get(route('admin.merchants.users.edit', [$merchantA, $userB]))->assertNotFound();
        $this->put(route('admin.merchants.users.update', [$merchantA, $userB]), [
            'name' => 'Hijacked',
            'email' => 'hijacked@example.com',
            'is_active' => '0',
            'role' => User::ROLE_ADMIN,
        ])->assertNotFound();
        $this->patch(route('admin.merchants.users.update', [$merchantA, $userB]), [
            'name' => 'Hijacked',
            'email' => 'hijacked@example.com',
            'is_active' => '0',
        ])->assertNotFound();
        $this->patch(route('admin.merchants.users.toggle', [$merchantA, $userB]))->assertNotFound();

        $userB->refresh();
        $this->assertSame($before['name'], $userB->name);
        $this->assertSame($before['email'], $userB->email);
        $this->assertSame($before['role'], $userB->role);
        $this->assertSame($before['merchant_id'], $userB->merchant_id);
        $this->assertSame($before['is_active'], $userB->is_active);
        $this->assertSame($before['password'], $userB->password);

        $this->get(route('admin.merchants.users.edit', [$merchantB, $userA]))->assertNotFound();
        $this->put(route('admin.merchants.users.update', [$merchantB, $userA]), [
            'name' => 'Hijacked',
            'email' => 'hijacked-a@example.com',
            'is_active' => '0',
        ])->assertNotFound();

        $this->assertSame('Maria Santos', $userA->refresh()->name);
        $this->assertSame($merchantA->id, $userA->merchant_id);
    }

    public function test_platform_admin_cannot_be_edited_through_merchant_user_management(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = Merchant::factory()->create();

        $this->actingAs($admin);

        $this->get(route('admin.merchants.users.edit', [$merchant, $admin]))->assertNotFound();
        $this->put(route('admin.merchants.users.update', [$merchant, $admin]), [
            'name' => 'Converted Admin',
            'email' => $admin->email,
            'is_active' => '1',
            'role' => User::ROLE_MERCHANT_USER,
            'merchant_id' => $merchant->id,
        ])->assertNotFound();
        $this->patch(route('admin.merchants.users.toggle', [$merchant, $admin]))->assertNotFound();

        $admin->refresh();
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertNull($admin->merchant_id);
        $this->assertNotSame('Converted Admin', $admin->name);
    }

    public function test_merchant_user_cannot_access_admin_or_manage_users(): void
    {
        [$merchantA, $userA, $merchantB, $userB] = $this->twoMerchants();

        $this->actingAs($userA);

        $this->get(route('admin.index'))->assertRedirect(url('/dashboard'));
        $this->get(route('admin.merchants.index'))->assertRedirect(url('/dashboard'));
        $this->get(route('admin.merchants.users.index', $merchantA))->assertRedirect(url('/dashboard'));
        $this->get(route('admin.merchants.users.index', $merchantB))->assertRedirect(url('/dashboard'));
        $this->get(route('admin.merchants.users.create', $merchantB))->assertRedirect(url('/dashboard'));
        $this->post(route('admin.merchants.users.store', $merchantB), [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(url('/dashboard'));
        $this->put(route('admin.merchants.users.update', [$merchantB, $userB]), [
            'name' => 'Hijacked',
            'email' => $userB->email,
            'is_active' => '0',
        ])->assertRedirect(url('/dashboard'));

        $this->assertNull(User::query()->where('email', 'intruder@example.com')->first());
        $this->assertTrue($userB->refresh()->is_active);
        $this->assertSame($merchantB->id, $userB->merchant_id);
    }

    /**
     * @return array{0: Merchant, 1: User, 2: Merchant, 3: User}
     */
    private function twoMerchants(): array
    {
        $merchantA = Merchant::factory()->create(['name' => 'Davao Foundation']);
        $userA = User::factory()->create([
            'merchant_id' => $merchantA->id,
            'name' => 'Maria Santos',
            'email' => 'maria.santos@example.com',
            'role' => User::ROLE_MERCHANT_USER,
            'is_active' => true,
            'onboarding_gateways_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $merchantB = Merchant::factory()->create(['name' => 'Cebu Cooperative']);
        $userB = User::factory()->create([
            'merchant_id' => $merchantB->id,
            'name' => 'Pedro Garcia',
            'email' => 'pedro.garcia@example.com',
            'role' => User::ROLE_MERCHANT_USER,
            'is_active' => true,
            'onboarding_gateways_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        return [$merchantA, $userA, $merchantB, $userB];
    }
}
