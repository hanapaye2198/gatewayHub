<?php

namespace Tests\Feature\Admin;

use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformAdministratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_platform_operations_and_super_admin_pages(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get(route('admin.merchants.index'))
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get(route('admin.administrators.index'))
            ->assertOk()
            ->assertSee('Administrators');

        $this->actingAs($superAdmin)
            ->get(route('admin.platform-fee.edit'))
            ->assertOk()
            ->assertSee('Current Rate: 1.50%')
            ->assertSee('deducts this fee from the merchant proceeds');

        $this->actingAs($superAdmin)
            ->get(route('admin.index'))
            ->assertOk()
            ->assertSee(route('admin.platform-fee.edit'))
            ->assertSee(route('admin.administrators.index'));
    }

    public function test_admin_cannot_open_super_admin_routes_or_see_them_in_navigation(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.index'))
            ->assertOk()
            ->assertDontSee(route('admin.platform-fee.edit'))
            ->assertDontSee(route('admin.administrators.index'));

        $this->actingAs($admin)->get(route('admin.administrators.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.administrators.create'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.platform-fee.edit'))->assertForbidden();
        $this->actingAs($admin)->put(route('admin.platform-fee.update'), [
            'percentage' => '2',
        ])->assertForbidden();
    }

    public function test_merchant_user_cannot_access_super_admin_routes(): void
    {
        $merchantUser = User::factory()->create();

        $this->actingAs($merchantUser);

        $this->get(route('admin.administrators.index'))->assertRedirect(url('/dashboard'));
        $this->get(route('admin.administrators.create'))->assertRedirect(url('/dashboard'));
        $this->post(route('admin.administrators.store'), [
            'name' => 'Injected Admin',
            'email' => 'injected@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => User::ROLE_SUPER_ADMIN,
        ])->assertRedirect(url('/dashboard'));
        $this->get(route('admin.platform-fee.edit'))->assertRedirect(url('/dashboard'));
        $this->put(route('admin.platform-fee.update'), [
            'percentage' => '9',
        ])->assertRedirect(url('/dashboard'));

        $this->assertNull(User::query()->where('email', 'injected@example.com')->first());
        $this->assertSame('0.0150', (string) \App\Models\PlatformFeeRule::activeGlobalPercentageRule()?->fee_value);
    }

    public function test_super_admin_can_create_and_disable_an_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $password = 'Secret-Pass-91';

        $this->actingAs($superAdmin)
            ->post(route('admin.administrators.store'), [
                'name' => 'Ops Admin',
                'email' => 'ops@example.com',
                'password' => $password,
                'password_confirmation' => $password,
                'role' => User::ROLE_SUPER_ADMIN,
                'merchant_id' => 999,
            ])
            ->assertRedirect(route('admin.administrators.index'));

        $admin = User::query()->where('email', 'ops@example.com')->firstOrFail();

        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isSuperAdmin());
        $this->assertNull($admin->merchant_id);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check($password, $admin->password));

        $created = PlatformAuditLog::query()->where('action', PlatformAuditLog::ACTION_ADMIN_CREATED)->firstOrFail();
        $this->assertSame($superAdmin->id, $created->actor_user_id);
        $this->assertSame('ops@example.com', $created->new_values['email'] ?? null);
        $this->assertArrayNotHasKey('password', $created->new_values ?? []);

        $this->actingAs($superAdmin)
            ->patch(route('admin.administrators.toggle', $admin))
            ->assertRedirect(route('admin.administrators.index'));

        $this->assertFalse($admin->refresh()->is_active);
        $this->actingAs($admin)->get(route('admin.index'))->assertForbidden();

        $disabled = PlatformAuditLog::query()->where('action', PlatformAuditLog::ACTION_ADMIN_DISABLED)->firstOrFail();
        $this->assertSame($superAdmin->id, $disabled->actor_user_id);
        $this->assertFalse($disabled->new_values['is_active'] ?? true);

        $this->actingAs($superAdmin)
            ->patch(route('admin.administrators.toggle', $admin))
            ->assertRedirect(route('admin.administrators.index'));

        $this->assertTrue($admin->refresh()->is_active);
        $this->actingAs($admin)->get(route('admin.merchants.index'))->assertOk();
    }

    public function test_admin_cannot_create_an_admin_or_modify_a_super_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['email' => 'owner@example.com']);
        $admin = User::factory()->admin()->create();
        $before = $superAdmin->only(['name', 'email', 'role', 'merchant_id', 'is_active', 'password']);

        $this->actingAs($admin)
            ->post(route('admin.administrators.store'), [
                'name' => 'Second Admin',
                'email' => 'second@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertForbidden();

        $this->assertNull(User::query()->where('email', 'second@example.com')->first());

        $this->actingAs($admin)
            ->put(route('admin.administrators.update', $superAdmin), [
                'name' => 'Demoted',
                'email' => 'demoted@example.com',
                'role' => User::ROLE_ADMIN,
                'merchant_id' => 1,
                'is_active' => '0',
            ])
            ->assertForbidden();

        $superAdmin->refresh();
        $this->assertSame($before['name'], $superAdmin->name);
        $this->assertSame($before['email'], $superAdmin->email);
        $this->assertSame($before['role'], $superAdmin->role);
        $this->assertSame($before['merchant_id'], $superAdmin->merchant_id);
        $this->assertSame($before['is_active'], $superAdmin->is_active);
        $this->assertSame($before['password'], $superAdmin->password);
    }

    public function test_super_admin_cannot_edit_or_convert_a_super_admin_through_administrator_management(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $owner = User::factory()->superAdmin()->create([
            'name' => 'Platform Owner',
            'email' => 'owner@example.com',
        ]);

        $this->actingAs($actor)
            ->get(route('admin.administrators.edit', $owner))
            ->assertNotFound();

        $this->actingAs($actor)
            ->put(route('admin.administrators.update', $owner), [
                'name' => 'Merchant Now',
                'email' => 'owner@example.com',
                'role' => User::ROLE_MERCHANT_USER,
                'merchant_id' => 5,
                'is_active' => '0',
            ])
            ->assertNotFound();

        $owner->refresh();
        $this->assertSame(User::ROLE_SUPER_ADMIN, $owner->role);
        $this->assertNull($owner->merchant_id);
        $this->assertSame('Platform Owner', $owner->name);
        $this->assertTrue($owner->is_active);
    }

    public function test_merchant_user_management_cannot_change_a_super_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $merchant = \App\Models\Merchant::factory()->create();

        $this->actingAs($superAdmin)
            ->put(route('admin.merchants.users.update', [$merchant, $superAdmin]), [
                'name' => 'Converted',
                'email' => 'converted@example.com',
                'role' => User::ROLE_MERCHANT_USER,
                'merchant_id' => $merchant->id,
                'is_active' => '1',
            ])
            ->assertNotFound();

        $superAdmin->refresh();
        $this->assertSame(User::ROLE_SUPER_ADMIN, $superAdmin->role);
        $this->assertNull($superAdmin->merchant_id);
        $this->assertNotSame('Converted', $superAdmin->name);
    }
}
