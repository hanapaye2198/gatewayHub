<?php

namespace Tests\Feature\Admin;

use App\Models\Gateway;
use App\Models\Merchant;
use App\Models\MerchantGateway;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_operators_are_super_admin_and_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $merchantUser = User::factory()->create();
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'merchant_id' => null,
        ]);

        $this->assertSame(User::ROLE_SUPER_ADMIN, $superAdmin->role);
        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertFalse($superAdmin->isAdmin());
        $this->assertTrue($superAdmin->isPlatformOperator());
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isSuperAdmin());
        $this->assertTrue($admin->isPlatformOperator());
        $this->assertFalse($merchantUser->isPlatformOperator());
        $this->assertFalse($staff->isPlatformOperator());
    }

    public function test_admin_can_access_merchant_list(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.merchants.index'))
            ->assertOk();
    }

    public function test_merchant_user_cannot_access_merchant_administration_routes(): void
    {
        $merchantUser = User::factory()->create();
        $merchant = Merchant::factory()->create();

        $this->actingAs($merchantUser);

        $this->get(route('admin.merchants.index'))->assertRedirect(url('/dashboard'));
        $this->get(route('admin.merchants.create'))->assertRedirect(url('/dashboard'));
        $this->post(route('admin.merchants.store'), $this->validMerchantPayload())->assertRedirect(url('/dashboard'));
        $this->get(route('admin.merchants.show', $merchant))->assertRedirect(url('/dashboard'));
        $this->get(route('admin.merchants.edit', $merchant))->assertRedirect(url('/dashboard'));
        $this->put(route('admin.merchants.update', $merchant), $this->validMerchantPayload())->assertRedirect(url('/dashboard'));
        $this->patch(route('admin.merchants.toggle', $merchant))->assertRedirect(url('/dashboard'));
    }

    public function test_staff_cannot_access_merchant_administration_routes(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'merchant_id' => null,
        ]);
        $merchant = Merchant::factory()->create();

        $this->actingAs($staff);

        $this->get(route('admin.merchants.index'))->assertRedirect(route('login'));
        $this->get(route('admin.merchants.create'))->assertRedirect(route('login'));
        $this->post(route('admin.merchants.store'), $this->validMerchantPayload())->assertRedirect(route('login'));
        $this->get(route('admin.merchants.show', $merchant))->assertRedirect(route('login'));
        $this->get(route('admin.merchants.edit', $merchant))->assertRedirect(route('login'));
        $this->put(route('admin.merchants.update', $merchant), $this->validMerchantPayload())->assertRedirect(route('login'));
        $this->patch(route('admin.merchants.toggle', $merchant))->assertRedirect(route('login'));
    }

    public function test_guests_cannot_access_merchant_administration_routes(): void
    {
        $merchant = Merchant::factory()->create();

        $this->get(route('admin.merchants.index'))->assertRedirect(route('login'));
        $this->get(route('admin.merchants.create'))->assertRedirect(route('login'));
        $this->get(route('admin.merchants.show', $merchant))->assertRedirect(route('login'));
        $this->get(route('admin.merchants.edit', $merchant))->assertRedirect(route('login'));
    }

    public function test_admin_can_open_merchant_creation_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.merchants.create'))
            ->assertOk()
            ->assertSee('Create merchant');
    }

    public function test_admin_can_create_a_merchant_without_api_credentials(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.merchants.store'), [
            'name' => 'Northwind Payments',
            'email' => 'billing@northwind.test',
            'qr_display_name' => 'Northwind',
            'theme_color' => '#112233',
            'webhook_url' => 'https://example.com/hooks/gatewayhub',
        ]);

        $merchant = Merchant::query()->where('email', 'billing@northwind.test')->first();

        $this->assertNotNull($merchant);
        $response->assertRedirect(route('admin.merchants.show', $merchant));

        $this->assertTrue($merchant->is_active);
        $this->assertSame('Northwind Payments', $merchant->name);
        $this->assertNull($merchant->api_key);
        $this->assertNull($merchant->api_key_hash);
        $this->assertNull($merchant->api_secret);
        $this->assertNull($merchant->webhook_secret);
        $this->assertSame('https://example.com/hooks/gatewayhub', $merchant->webhook_url);
    }

    public function test_duplicate_merchant_email_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        Merchant::factory()->create(['email' => 'taken@example.test']);

        $this->actingAs($admin)
            ->from(route('admin.merchants.create'))
            ->post(route('admin.merchants.store'), [
                'name' => 'Duplicate Co',
                'email' => 'taken@example.test',
            ])
            ->assertRedirect(route('admin.merchants.create'))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Merchant::query()->where('email', 'taken@example.test')->count());
    }

    public function test_admin_can_edit_a_merchant_and_persists_profile_fields_only(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = Merchant::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.test',
            'is_active' => true,
        ]);
        $merchant->forceFill([
            'api_key_hash' => hash('sha256', 'existing-api-key'),
            'api_key_last_four' => 'key1',
        ])->save();

        $this->actingAs($admin)
            ->put(route('admin.merchants.update', $merchant), [
                'name' => 'Updated Name',
                'email' => 'updated@example.test',
                'qr_display_name' => 'Updated QR',
                'theme_color' => '#ABCDEF',
                'webhook_url' => 'https://example.com/hooks/updated',
            ])
            ->assertRedirect(route('admin.merchants.show', $merchant));

        $merchant->refresh();

        $this->assertSame('Updated Name', $merchant->name);
        $this->assertSame('updated@example.test', $merchant->email);
        $this->assertSame('Updated QR', $merchant->qr_display_name);
        $this->assertSame('#ABCDEF', $merchant->theme_color);
        $this->assertSame('https://example.com/hooks/updated', $merchant->webhook_url);
        $this->assertTrue($merchant->is_active);
        $this->assertSame(hash('sha256', 'existing-api-key'), $merchant->api_key_hash);
        $this->assertSame('key1', $merchant->api_key_last_four);
    }

    public function test_admin_can_suspend_a_merchant_without_affecting_another_merchant(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = Merchant::factory()->create(['is_active' => true]);
        $other = Merchant::factory()->create(['is_active' => true]);
        $user = User::factory()->create([
            'merchant_id' => $merchant->id,
            'is_active' => true,
        ]);
        $otherUser = User::factory()->create([
            'merchant_id' => $other->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.merchants.toggle', $merchant))
            ->assertRedirect(route('admin.merchants.index'));

        $merchant->refresh();
        $other->refresh();
        $user->refresh();
        $otherUser->refresh();

        $this->assertFalse($merchant->is_active);
        $this->assertFalse($user->is_active);
        $this->assertTrue($other->is_active);
        $this->assertTrue($otherUser->is_active);

        $this->patch(route('admin.merchants.toggle', $merchant))
            ->assertRedirect(route('admin.merchants.index'));

        $this->assertTrue($merchant->refresh()->is_active);
        $this->assertTrue($user->refresh()->is_active);
        $this->assertTrue($other->refresh()->is_active);
    }

    public function test_merchant_detail_page_loads_without_secrets(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = Merchant::factory()->create([
            'name' => 'Visible Merchant',
            'email' => 'visible@example.test',
        ]);
        $merchant->forceFill([
            'api_key_hash' => 'api-key-hash-do-not-render',
            'api_secret' => 'api-secret-do-not-render',
            'webhook_secret' => 'webhook-secret-do-not-render',
        ])->save();

        $gateway = Gateway::query()->create([
            'code' => 'visible-gw',
            'name' => 'Visible Gateway',
            'driver_class' => 'App\\Services\\Gateways\\Drivers\\CoinsDriver',
            'is_global_enabled' => true,
        ]);

        MerchantGateway::query()->create([
            'merchant_id' => $merchant->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => ['client_secret' => 'gateway-secret-do-not-render'],
        ]);

        Payment::factory()->for($merchant)->create();
        User::factory()->create(['merchant_id' => $merchant->id]);

        $response = $this->actingAs($admin)
            ->get(route('admin.merchants.show', $merchant));

        $response->assertOk();
        $response->assertSee('Visible Merchant');
        $response->assertSee('visible@example.test');
        $response->assertSee('Visible Gateway');
        $response->assertSee('Active');
        $response->assertSee(route('admin.merchants.edit', $merchant), false);
        $response->assertSee(route('admin.payments.index', ['merchant_id' => $merchant->id]), false);
        $response->assertDontSee('api-key-hash-do-not-render');
        $response->assertDontSee('api-secret-do-not-render');
        $response->assertDontSee('webhook-secret-do-not-render');
        $response->assertDontSee('gateway-secret-do-not-render');
        $response->assertDontSee('config_json');
    }

    public function test_admin_merchant_list_links_to_create_edit_and_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = Merchant::factory()->create(['name' => 'Linked Merchant']);

        $this->actingAs($admin)
            ->get(route('admin.merchants.index'))
            ->assertOk()
            ->assertSee('Linked Merchant')
            ->assertSee(route('admin.merchants.create'), false)
            ->assertSee(route('admin.merchants.show', $merchant), false)
            ->assertSee(route('admin.merchants.edit', $merchant), false)
            ->assertSee('Suspend');
    }

    /**
     * @return array<string, string>
     */
    private function validMerchantPayload(): array
    {
        return [
            'name' => 'Denied Merchant',
            'email' => 'denied@example.test',
        ];
    }
}
