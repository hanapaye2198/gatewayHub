<?php

namespace Tests\Feature\Admin;

use App\Models\Merchant;
use App\Models\PlatformAuditLog;
use App\Models\User;
use App\Services\Admin\PlatformAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_audit_logs_and_merchant_users_cannot(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Tindah Super Admin']);
        $merchantUser = User::factory()->create();
        $log = PlatformAuditLog::factory()->create([
            'actor_user_id' => $admin->id,
            'merchant_id' => $merchantUser->merchant_id,
            'description' => 'Visible audit row',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('Audit Logs')
            ->assertSee('Visible audit row')
            ->assertSee('Tindah Super Admin');

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.show', $log))
            ->assertOk()
            ->assertSee('Visible audit row');

        $this->actingAs($merchantUser);
        $this->get(route('admin.audit-logs.index'))->assertRedirect(url('/dashboard'));
        $this->get(route('admin.audit-logs.show', $log))->assertRedirect(url('/dashboard'));
        $this->put(route('admin.audit-logs.show', $log), ['description' => 'changed'])->assertMethodNotAllowed();
        $this->delete(route('admin.audit-logs.show', $log))->assertMethodNotAllowed();

        $this->assertSame('Visible audit row', $log->refresh()->description);
    }

    public function test_creating_and_updating_a_merchant_writes_audit_records_without_secrets(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $apiKey = 'ak_live_should_not_store';
        $apiKeyHash = hash('sha256', $apiKey);
        $apiSecret = 'live-api-secret-should-not-store';
        $webhookSecret = 'whsec-should-not-store';

        $this->actingAs($admin)
            ->withHeaders([
                'User-Agent' => 'AuditTest/1.0',
                'X-Api-Key' => $apiKey,
            ])
            ->post(route('admin.merchants.store'), [
                'name' => 'ABC Foundation',
                'email' => 'billing@abc.test',
                'actor_user_id' => $otherAdmin->id,
                'merchant_id' => 999,
                'api_key' => $apiKey,
                'password' => 'Secret-Pass-91',
            ])
            ->assertRedirect();

        $merchant = Merchant::query()->where('email', 'billing@abc.test')->firstOrFail();
        $created = PlatformAuditLog::query()->where('action', PlatformAuditLog::ACTION_MERCHANT_CREATED)->firstOrFail();

        $this->assertSame($admin->id, $created->actor_user_id);
        $this->assertNotSame($otherAdmin->id, $created->actor_user_id);
        $this->assertSame($merchant->id, $created->merchant_id);
        $this->assertSame('Merchant', $created->target_type);
        $this->assertSame((string) $merchant->id, $created->target_id);
        $this->assertSame('ABC Foundation', $created->new_values['name']);
        $this->assertTrue($created->new_values['is_active']);
        $this->assertSame('AuditTest/1.0', $created->user_agent);
        $this->assertNull($created->old_values);
        $this->assertStoredLogOmits($created, $apiKey);
        $this->assertStoredLogOmits($created, 'Secret-Pass-91');

        $merchant->forceFill([
            'api_key' => $apiKey,
            'api_key_hash' => $apiKeyHash,
            'api_secret' => $apiSecret,
            'webhook_secret' => $webhookSecret,
        ])->save();

        $this->put(route('admin.merchants.update', $merchant), [
            'name' => 'ABC Foundation Updated',
            'email' => 'billing@abc.test',
            'theme_color' => '#112233',
            'api_key' => $apiKey,
            'api_key_hash' => $apiKeyHash,
            'webhook_secret' => $webhookSecret,
            'actor_user_id' => $otherAdmin->id,
        ])->assertRedirect();

        $updated = PlatformAuditLog::query()->where('action', PlatformAuditLog::ACTION_MERCHANT_UPDATED)->firstOrFail();
        $this->assertSame($admin->id, $updated->actor_user_id);
        $this->assertSame($merchant->id, $updated->merchant_id);
        $this->assertSame(['name' => 'ABC Foundation', 'theme_color' => null], $updated->old_values);
        $this->assertSame(['name' => 'ABC Foundation Updated', 'theme_color' => '#112233'], $updated->new_values);
        $this->assertArrayNotHasKey('email', $updated->new_values);
        $this->assertStoredLogOmits($updated, $apiKey);
        $this->assertStoredLogOmits($updated, $apiKeyHash);
        $this->assertStoredLogOmits($updated, $apiSecret);
        $this->assertStoredLogOmits($updated, $webhookSecret);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.show', $updated))
            ->assertOk()
            ->assertSee('ABC Foundation Updated')
            ->assertDontSee($apiKey)
            ->assertDontSee($apiKeyHash)
            ->assertDontSee($webhookSecret);
    }

    public function test_activating_and_suspending_a_merchant_are_audited_separately_from_users(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = Merchant::factory()->create(['name' => 'Suspended Co', 'is_active' => true]);
        $user = User::factory()->create([
            'merchant_id' => $merchant->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.merchants.toggle', $merchant))
            ->assertRedirect();

        $suspended = PlatformAuditLog::query()->where('action', PlatformAuditLog::ACTION_MERCHANT_SUSPENDED)->firstOrFail();
        $this->assertSame($admin->id, $suspended->actor_user_id);
        $this->assertSame($merchant->id, $suspended->merchant_id);
        $this->assertSame(['is_active' => true], $suspended->old_values);
        $this->assertSame(['is_active' => false], $suspended->new_values);
        $this->assertSame(0, PlatformAuditLog::query()->where('action', PlatformAuditLog::ACTION_MERCHANT_USER_DISABLED)->count());
        $this->assertFalse($user->refresh()->is_active);

        $this->patch(route('admin.merchants.toggle', $merchant))->assertRedirect();

        $activated = PlatformAuditLog::query()->where('action', PlatformAuditLog::ACTION_MERCHANT_ACTIVATED)->firstOrFail();
        $this->assertSame($merchant->id, $activated->merchant_id);
        $this->assertSame(['is_active' => false], $activated->old_values);
        $this->assertSame(['is_active' => true], $activated->new_values);
        $this->assertTrue($merchant->refresh()->is_active);
    }

    public function test_merchant_user_changes_are_audited_without_passwords(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = Merchant::factory()->create(['name' => 'ABC Foundation']);
        $password = 'Secret-Pass-91';

        $this->actingAs($admin)
            ->post(route('admin.merchants.users.store', $merchant), [
                'name' => 'Maria Santos',
                'email' => 'maria@example.com',
                'password' => $password,
                'password_confirmation' => $password,
                'role' => User::ROLE_ADMIN,
                'actor_user_id' => 999,
                'merchant_id' => 999,
            ])
            ->assertRedirect();

        $user = User::query()->where('email', 'maria@example.com')->firstOrFail();
        $created = PlatformAuditLog::query()->where('action', PlatformAuditLog::ACTION_MERCHANT_USER_CREATED)->firstOrFail();

        $this->assertSame($admin->id, $created->actor_user_id);
        $this->assertSame($merchant->id, $created->merchant_id);
        $this->assertSame('User', $created->target_type);
        $this->assertSame((string) $user->id, $created->target_id);
        $this->assertSame(User::ROLE_MERCHANT_USER, $created->new_values['role']);
        $this->assertSame($merchant->id, $created->new_values['merchant_id']);
        $this->assertTrue($created->new_values['is_active']);
        $this->assertStoredLogOmits($created, $password);
        $this->assertStoredLogOmits($created, $user->password);

        $this->put(route('admin.merchants.users.update', [$merchant, $user]), [
            'name' => 'Maria Santos Updated',
            'email' => 'maria@example.com',
            'password' => 'New-Secret-91',
            'password_confirmation' => 'New-Secret-91',
            'is_active' => '1',
        ])->assertRedirect();

        $updated = PlatformAuditLog::query()->where('action', PlatformAuditLog::ACTION_MERCHANT_USER_UPDATED)->firstOrFail();
        $this->assertSame(['name' => 'Maria Santos'], $updated->old_values);
        $this->assertSame(['name' => 'Maria Santos Updated'], $updated->new_values);
        $this->assertStoredLogOmits($updated, 'New-Secret-91');
        $this->assertStoredLogOmits($updated, $user->refresh()->password);

        $this->patch(route('admin.merchants.users.toggle', [$merchant, $user]))->assertRedirect();
        $disabled = PlatformAuditLog::query()->where('action', PlatformAuditLog::ACTION_MERCHANT_USER_DISABLED)->firstOrFail();
        $this->assertSame($merchant->id, $disabled->merchant_id);
        $this->assertSame((string) $user->id, $disabled->target_id);
        $this->assertSame(['is_active' => false], $disabled->new_values);
        $this->assertTrue($merchant->refresh()->is_active);

        $this->patch(route('admin.merchants.users.toggle', [$merchant, $user]))->assertRedirect();
        $enabled = PlatformAuditLog::query()->where('action', PlatformAuditLog::ACTION_MERCHANT_USER_ENABLED)->firstOrFail();
        $this->assertSame(['is_active' => true], $enabled->new_values);
        $this->assertTrue($user->refresh()->is_active);
    }

    public function test_failed_validation_and_cross_merchant_edits_do_not_write_success_events(): void
    {
        $admin = User::factory()->admin()->create();
        Merchant::factory()->create(['email' => 'taken@example.test']);
        $merchantA = Merchant::factory()->create();
        $merchantB = Merchant::factory()->create();
        $userB = User::factory()->create([
            'merchant_id' => $merchantB->id,
            'name' => 'Pedro Garcia',
            'email' => 'pedro@example.com',
        ]);
        $adminUser = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.merchants.create'))
            ->post(route('admin.merchants.store'), [
                'name' => 'Duplicate Co',
                'email' => 'taken@example.test',
            ])
            ->assertSessionHasErrors('email');

        $this->from(route('admin.merchants.users.create', $merchantA))
            ->post(route('admin.merchants.users.store', $merchantA), [
                'name' => 'Someone',
                'email' => 'pedro@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasErrors('email');

        $this->put(route('admin.merchants.users.update', [$merchantA, $userB]), [
            'name' => 'Hijacked',
            'email' => 'hijacked@example.com',
            'is_active' => '0',
        ])->assertNotFound();

        $this->get(route('admin.merchants.users.edit', [$merchantA, $adminUser]))->assertNotFound();

        $this->assertSame(0, PlatformAuditLog::query()->count());
        $this->assertSame('Pedro Garcia', $userB->refresh()->name);
        $this->assertSame(User::ROLE_ADMIN, $adminUser->refresh()->role);
    }

    public function test_sensitive_credentials_are_stripped_before_storage(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = Merchant::factory()->create();
        $secrets = [
            'Secret-Pass-91',
            '$2y$10$should-not-store',
            'ak_live_should_not_store',
            'hash-should-not-store',
            'whsec-should-not-store',
            'gateway-secret-should-not-store',
            'gateway-client-secret',
        ];

        $this->actingAs($admin);

        $log = app(PlatformAuditService::class)->record(
            PlatformAuditLog::ACTION_MERCHANT_UPDATED,
            $merchant,
            $merchant,
            [],
            [
                'name' => 'Visible Merchant',
                'password' => $secrets[0],
                'password_hash' => $secrets[1],
                'api_key' => $secrets[2],
                'api_key_hash' => $secrets[3],
                'webhook_secret' => $secrets[4],
                'config_json' => ['client_secret' => $secrets[5], 'name' => 'should-drop-with-parent'],
                'client_secret' => $secrets[6],
            ],
            'Updated merchant Visible Merchant.',
        );

        $this->assertSame($admin->id, $log->actor_user_id);
        $this->assertSame(['name' => 'Visible Merchant'], $log->new_values);

        foreach ($secrets as $secret) {
            $this->assertStoredLogOmits($log, $secret);
        }
    }

    public function test_audit_records_cannot_be_changed_through_admin_routes(): void
    {
        $admin = User::factory()->admin()->create();
        $log = PlatformAuditLog::factory()->create([
            'actor_user_id' => $admin->id,
            'description' => 'Original audit row',
        ]);

        $this->actingAs($admin);
        $this->put(route('admin.audit-logs.show', $log), ['description' => 'changed'])->assertMethodNotAllowed();
        $this->patch(route('admin.audit-logs.show', $log), ['description' => 'changed'])->assertMethodNotAllowed();
        $this->delete(route('admin.audit-logs.show', $log))->assertMethodNotAllowed();
        $this->post(route('admin.audit-logs.index'), [
            'action' => PlatformAuditLog::ACTION_MERCHANT_CREATED,
            'actor_user_id' => $admin->id,
            'description' => 'Forged audit row',
        ])->assertMethodNotAllowed();

        $this->assertSame('Original audit row', $log->refresh()->description);
        $this->assertSame(1, PlatformAuditLog::query()->count());
    }

    public function test_audit_filters_and_pagination_run_in_the_query(): void
    {
        $ada = User::factory()->admin()->create(['name' => 'Ada Admin']);
        $bea = User::factory()->admin()->create(['name' => 'Bea Admin']);
        $alpha = Merchant::factory()->create(['name' => 'Alpha Merchant']);
        $beta = Merchant::factory()->create(['name' => 'Beta Merchant']);

        PlatformAuditLog::factory()->create([
            'actor_user_id' => $ada->id,
            'merchant_id' => $alpha->id,
            'action' => PlatformAuditLog::ACTION_MERCHANT_CREATED,
            'description' => 'old-audit-row',
            'created_at' => '2026-01-15 09:00:00',
        ]);
        PlatformAuditLog::factory()->create([
            'actor_user_id' => $bea->id,
            'merchant_id' => $beta->id,
            'action' => PlatformAuditLog::ACTION_MERCHANT_UPDATED,
            'description' => 'new-audit-row',
            'created_at' => '2026-02-15 09:00:00',
        ]);

        $this->actingAs($ada);

        $this->get(route('admin.audit-logs.index', [
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31',
        ]))->assertOk()->assertSee('old-audit-row')->assertDontSee('new-audit-row');

        $this->get(route('admin.audit-logs.index', [
            'action' => PlatformAuditLog::ACTION_MERCHANT_UPDATED,
        ]))->assertOk()->assertSee('new-audit-row')->assertDontSee('old-audit-row');

        $this->get(route('admin.audit-logs.index', [
            'merchant_id' => $beta->id,
        ]))->assertOk()->assertSee('new-audit-row')->assertDontSee('old-audit-row');

        $this->get(route('admin.audit-logs.index', [
            'actor_user_id' => $ada->id,
        ]))->assertOk()->assertSee('old-audit-row')->assertDontSee('new-audit-row');

        for ($i = 1; $i <= 26; $i++) {
            PlatformAuditLog::factory()->create([
                'actor_user_id' => $ada->id,
                'merchant_id' => $alpha->id,
                'description' => sprintf('entry-%02d', $i),
                'created_at' => now()->subMinutes(30 - $i),
            ]);
        }

        $page = $this->get(route('admin.audit-logs.index'));
        $page->assertOk()->assertSee('entry-26')->assertDontSee('entry-01');
        $this->assertSame(25, $page->viewData('logs')->perPage());

        $this->get(route('admin.audit-logs.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('entry-01')
            ->assertDontSee('entry-26');
    }

    public function test_unchanged_merchant_update_does_not_write_an_audit_record(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = Merchant::factory()->create([
            'name' => 'Same Name',
            'email' => 'same@example.test',
            'theme_color' => null,
            'qr_display_name' => null,
            'webhook_url' => null,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.merchants.update', $merchant), [
                'name' => 'Same Name',
                'email' => 'same@example.test',
            ])
            ->assertRedirect();

        $this->assertSame(0, PlatformAuditLog::query()->count());
    }

    private function assertStoredLogOmits(PlatformAuditLog $log, string $secret): void
    {
        $encoded = json_encode($log->fresh()?->getAttributes());

        $this->assertIsString($encoded);
        $this->assertStringNotContainsString($secret, $encoded);
    }
}
