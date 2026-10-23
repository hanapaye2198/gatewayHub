<?php

namespace Tests\Feature\Admin;

use App\Models\Merchant;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MerchantUserPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const DEFAULT_PASSWORD = 'Default-Pass-2026';

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.merchant_default_password' => self::DEFAULT_PASSWORD]);
    }

    public function test_super_admin_resets_merchant_user_password_to_default(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        [$merchant, $merchantUser] = $this->merchantWithUser();

        $this->actingAs($superAdmin)
            ->patch(route('admin.merchants.users.reset-password', [$merchant, $merchantUser]))
            ->assertRedirect(route('admin.merchants.users.index', $merchant))
            ->assertSessionHas('status');

        $merchantUser->refresh();

        $this->assertTrue(Hash::check(self::DEFAULT_PASSWORD, $merchantUser->password));
        $this->assertTrue($merchantUser->must_change_password);

        $audit = PlatformAuditLog::query()
            ->where('action', PlatformAuditLog::ACTION_MERCHANT_USER_PASSWORD_RESET)
            ->firstOrFail();

        $this->assertSame($superAdmin->id, $audit->actor_user_id);
        $this->assertSame($merchant->id, $audit->merchant_id);
        $this->assertStringNotContainsString(self::DEFAULT_PASSWORD, json_encode($audit->toArray()));
    }

    public function test_super_admin_sees_reset_password_button_and_pending_badge(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        [$merchant, $merchantUser] = $this->merchantWithUser();

        $this->actingAs($superAdmin)
            ->get(route('admin.merchants.users.index', $merchant))
            ->assertOk()
            ->assertSee('Reset Password')
            ->assertDontSee('Password change required');

        $merchantUser->forceFill(['must_change_password' => true])->save();

        $this->actingAs($superAdmin)
            ->get(route('admin.merchants.users.index', $merchant))
            ->assertOk()
            ->assertSee('Password change required');
    }

    public function test_admin_cannot_reset_merchant_user_password(): void
    {
        $admin = User::factory()->admin()->create();
        [$merchant, $merchantUser] = $this->merchantWithUser();
        $originalPassword = $merchantUser->password;

        $this->actingAs($admin)
            ->get(route('admin.merchants.users.index', $merchant))
            ->assertOk()
            ->assertDontSee('Reset Password');

        $this->actingAs($admin)
            ->patch(route('admin.merchants.users.reset-password', [$merchant, $merchantUser]))
            ->assertForbidden();

        $merchantUser->refresh();
        $this->assertSame($originalPassword, $merchantUser->password);
        $this->assertFalse($merchantUser->must_change_password);
    }

    public function test_merchant_user_cannot_reset_passwords(): void
    {
        [$merchant, $merchantUser] = $this->merchantWithUser();
        $originalPassword = $merchantUser->password;

        $this->actingAs($merchantUser)
            ->patch(route('admin.merchants.users.reset-password', [$merchant, $merchantUser]))
            ->assertRedirect(url('/dashboard'));

        $this->assertSame($originalPassword, $merchantUser->refresh()->password);
    }

    public function test_reset_cannot_target_another_merchants_user_or_a_platform_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        [$merchantA] = $this->merchantWithUser();
        [, $userB] = $this->merchantWithUser();
        $userBPassword = $userB->password;
        $adminPassword = $admin->password;

        $this->actingAs($superAdmin)
            ->patch(route('admin.merchants.users.reset-password', [$merchantA, $userB]))
            ->assertNotFound();

        $this->actingAs($superAdmin)
            ->patch(route('admin.merchants.users.reset-password', [$merchantA, $admin]))
            ->assertNotFound();

        $this->assertSame($userBPassword, $userB->refresh()->password);
        $this->assertFalse($userB->must_change_password);
        $this->assertSame($adminPassword, $admin->refresh()->password);
    }

    public function test_merchant_user_with_reset_password_is_redirected_to_change_password(): void
    {
        [, $merchantUser] = $this->merchantWithUser();
        $merchantUser->forceFill(['must_change_password' => true])->save();

        $this->actingAs($merchantUser)
            ->get(route('dashboard'))
            ->assertRedirect(route('user-password.edit'));

        $this->actingAs($merchantUser)
            ->get(route('dashboard.api-credentials'))
            ->assertRedirect(route('user-password.edit'));

        $this->actingAs($merchantUser)
            ->get(route('user-password.edit'))
            ->assertOk()
            ->assertSee('Your password was reset to the default password.');
    }

    public function test_merchant_user_changes_default_password_and_regains_dashboard_access(): void
    {
        [, $merchantUser] = $this->merchantWithUser();
        $merchantUser->forceFill([
            'password' => self::DEFAULT_PASSWORD,
            'must_change_password' => true,
        ])->save();

        $this->actingAs($merchantUser);

        Livewire::test('pages::settings.password')
            ->set('current_password', self::DEFAULT_PASSWORD)
            ->set('password', 'My-New-Secret-91')
            ->set('password_confirmation', 'My-New-Secret-91')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertRedirect(url('/dashboard'));

        $merchantUser->refresh();

        $this->assertTrue(Hash::check('My-New-Secret-91', $merchantUser->password));
        $this->assertFalse($merchantUser->must_change_password);

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_merchant_user_cannot_keep_the_default_password(): void
    {
        [, $merchantUser] = $this->merchantWithUser();
        $merchantUser->forceFill([
            'password' => self::DEFAULT_PASSWORD,
            'must_change_password' => true,
        ])->save();

        $this->actingAs($merchantUser);

        Livewire::test('pages::settings.password')
            ->set('current_password', self::DEFAULT_PASSWORD)
            ->set('password', self::DEFAULT_PASSWORD)
            ->set('password_confirmation', self::DEFAULT_PASSWORD)
            ->call('updatePassword')
            ->assertHasErrors(['password']);

        $this->assertTrue($merchantUser->refresh()->must_change_password);
    }

    /**
     * @return array{0: Merchant, 1: User}
     */
    private function merchantWithUser(): array
    {
        $merchant = Merchant::factory()->create(['is_active' => true]);
        $user = User::factory()->create([
            'merchant_id' => $merchant->id,
            'role' => User::ROLE_MERCHANT_USER,
            'is_active' => true,
            'onboarding_gateways_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        return [$merchant, $user];
    }
}
