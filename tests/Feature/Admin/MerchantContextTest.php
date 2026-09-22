<?php

namespace Tests\Feature\Admin;

use App\Models\Gateway;
use App\Models\Merchant;
use App\Models\MerchantGateway;
use App\Models\Payment;
use App\Models\PlatformAuditLog;
use App\Models\User;
use App\Support\MerchantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class MerchantContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_enters_an_active_merchant_without_changing_their_account(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = Merchant::factory()->create(['name' => 'ABC Foundation', 'is_active' => true]);
        $originalRole = $admin->role;
        $originalMerchantId = $admin->merchant_id;

        $this->actingAs($admin)
            ->post(route('admin.merchants.access', $merchant))
            ->assertRedirect(route('dashboard'));

        $admin->refresh();
        $this->assertAuthenticatedAs($admin);
        $this->assertSame($originalRole, $admin->role);
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertSame($originalMerchantId, $admin->merchant_id);
        $this->assertNull($admin->merchant_id);
        $this->assertSame($merchant->id, session(MerchantContext::SESSION_ID));
        $this->assertNotNull(session(MerchantContext::SESSION_AT));

        $entered = PlatformAuditLog::query()->where('action', PlatformAuditLog::ACTION_MERCHANT_CONTEXT_ENTERED)->firstOrFail();
        $this->assertSame($admin->id, $entered->actor_user_id);
        $this->assertSame($merchant->id, $entered->merchant_id);
        $this->assertSame('ABC Foundation', $entered->new_values['name']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Merchant Context: ABC Foundation')
            ->assertSee('You are viewing this merchant as Super Admin')
            ->assertSee('Exit Merchant');
    }

    public function test_merchant_users_and_inactive_merchants_cannot_enter_context(): void
    {
        $merchantUser = User::factory()->create();
        $active = Merchant::factory()->create(['is_active' => true]);
        $suspended = Merchant::factory()->create(['name' => 'Suspended Co', 'is_active' => false]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($merchantUser)
            ->post(route('admin.merchants.access', $active))
            ->assertRedirect(url('/dashboard'));

        $this->assertNull(session(MerchantContext::SESSION_ID));
        $this->get(route('admin.merchants.index'))->assertRedirect(url('/dashboard'));
        $this->assertSame(0, PlatformAuditLog::query()->count());

        $this->actingAs($admin)
            ->get(route('admin.merchants.show', $suspended))
            ->assertOk()
            ->assertSee('Access Merchant is available for active merchants only.');

        $this->actingAs($admin)
            ->from(route('admin.merchants.show', $suspended))
            ->post(route('admin.merchants.access', $suspended))
            ->assertRedirect(route('admin.merchants.show', $suspended))
            ->assertSessionHas('error');

        $this->assertNull(session(MerchantContext::SESSION_ID));
        $this->assertFalse($suspended->refresh()->is_active);
        $this->assertSame(0, PlatformAuditLog::query()->count());
    }

    public function test_exit_returns_to_the_admin_area_without_logging_out(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = Merchant::factory()->create(['name' => 'ABC Foundation', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.merchants.access', $merchant))
            ->assertRedirect(route('dashboard'));

        $this->post(route('admin.merchant-context.exit'))
            ->assertRedirect(route('admin.index'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session(MerchantContext::SESSION_ID));
        $this->assertSame(User::ROLE_ADMIN, $admin->refresh()->role);
        $this->assertNull($admin->merchant_id);
        $this->get(route('admin.index'))->assertOk()->assertDontSee('Merchant Context: ABC Foundation');

        $exited = PlatformAuditLog::query()->where('action', PlatformAuditLog::ACTION_MERCHANT_CONTEXT_EXITED)->firstOrFail();
        $this->assertSame($admin->id, $exited->actor_user_id);
        $this->assertSame($merchant->id, $exited->merchant_id);
    }

    public function test_merchant_context_scopes_payments_reports_and_ignores_query_overrides(): void
    {
        $admin = User::factory()->admin()->create();
        [$merchantA, $merchantB] = $this->twoMerchants();
        $this->createGateway();

        Payment::factory()->for($merchantA)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'PAY-MERCHANT-A',
            'amount' => 10,
            'status' => 'paid',
        ]);
        $paymentB = Payment::factory()->for($merchantB)->create([
            'gateway_code' => 'coins',
            'reference_id' => 'PAY-MERCHANT-B',
            'amount' => 9999,
            'status' => 'paid',
        ]);

        $this->actingAs($admin)->post(route('admin.merchants.access', $merchantA));

        $this->get(route('dashboard', ['merchant_id' => $merchantB->id]))
            ->assertOk()
            ->assertSee('PAY-MERCHANT-A')
            ->assertDontSee('PAY-MERCHANT-B');

        $this->assertSame($merchantA->id, session(MerchantContext::SESSION_ID));

        $this->get(route('dashboard.payments.show', $paymentB))->assertNotFound();

        $worksheet = $this->worksheet(
            $this->get(route('dashboard.payments.report', ['merchant_id' => $merchantB->id]))
                ->assertOk()
                ->streamedContent()
        );

        $this->assertStringContainsString('PAY-MERCHANT-A', $worksheet);
        $this->assertStringNotContainsString('PAY-MERCHANT-B', $worksheet);
        $this->assertStringNotContainsString('9999.00', $worksheet);
        $this->assertSame($merchantA->id, session(MerchantContext::SESSION_ID));
    }

    public function test_gateways_and_webhook_settings_stay_on_the_selected_merchant_without_secrets(): void
    {
        $admin = User::factory()->admin()->create();
        [$merchantA, $merchantB] = $this->twoMerchants();
        $gateway = $this->createGateway();

        MerchantGateway::query()->create([
            'merchant_id' => $merchantA->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => ['client_secret' => 'merchant-a-gateway-secret'],
        ]);
        MerchantGateway::query()->create([
            'merchant_id' => $merchantB->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => false,
            'config_json' => ['client_secret' => 'merchant-b-gateway-secret'],
        ]);

        $merchantA->forceFill([
            'webhook_url' => 'https://alpha.example/hooks/gatewayhub',
            'webhook_secret' => 'whsec-alpha-secret',
            'api_key' => 'ak_alpha_should_not_show',
            'api_key_hash' => hash('sha256', 'ak_alpha_should_not_show'),
        ])->save();
        $merchantB->forceFill([
            'webhook_url' => 'https://beta.example/hooks/gatewayhub',
            'webhook_secret' => 'whsec-beta-secret',
            'api_key' => 'ak_beta_should_not_show',
        ])->save();

        $this->actingAs($admin)->post(route('admin.merchants.access', $merchantA));

        $this->get(route('dashboard.gateways'))
            ->assertOk()
            ->assertSee('Coins.ph')
            ->assertDontSee('merchant-a-gateway-secret')
            ->assertDontSee('merchant-b-gateway-secret');

        $this->assertSame(1, MerchantGateway::query()->where('merchant_id', $merchantA->id)->count());
        $this->assertFalse(MerchantGateway::query()->where('merchant_id', $merchantB->id)->firstOrFail()->is_enabled);

        $this->get(route('dashboard.api-credentials'))
            ->assertOk()
            ->assertSee('https://alpha.example/hooks/gatewayhub')
            ->assertDontSee('https://beta.example/hooks/gatewayhub')
            ->assertDontSee('whsec-alpha-secret')
            ->assertDontSee('whsec-beta-secret')
            ->assertDontSee('ak_alpha_should_not_show')
            ->assertDontSee('ak_beta_should_not_show');

        $this->patch(route('merchant.webhook.update'), [
            'webhook_url' => 'https://evil.example/hooks',
            'webhook_secret' => 'stolen-secret',
        ])->assertForbidden();

        $this->assertSame('https://alpha.example/hooks/gatewayhub', $merchantA->refresh()->webhook_url);
        $this->assertSame('https://beta.example/hooks/gatewayhub', $merchantB->refresh()->webhook_url);
    }

    public function test_switching_merchants_replaces_context_and_invalid_context_is_cleared(): void
    {
        $admin = User::factory()->admin()->create();
        [$merchantA, $merchantB] = $this->twoMerchants();

        $this->actingAs($admin);
        $this->post(route('admin.merchants.access', $merchantA));
        $this->post(route('admin.merchants.access', $merchantB))->assertRedirect(route('dashboard'));

        $this->assertSame($merchantB->id, session(MerchantContext::SESSION_ID));
        $this->assertSame(1, PlatformAuditLog::query()->where('action', PlatformAuditLog::ACTION_MERCHANT_CONTEXT_EXITED)->where('merchant_id', $merchantA->id)->count());
        $this->assertSame(1, PlatformAuditLog::query()->where('action', PlatformAuditLog::ACTION_MERCHANT_CONTEXT_ENTERED)->where('merchant_id', $merchantB->id)->count());

        $merchantB->update(['is_active' => false]);

        $this->get(route('dashboard'))
            ->assertRedirect(route('admin.index'));

        $this->assertNull(session(MerchantContext::SESSION_ID));
        $this->assertSame(User::ROLE_ADMIN, $admin->refresh()->role);
        $this->assertNull($admin->merchant_id);
        $this->assertFalse($merchantB->refresh()->is_active);

        session()->put(MerchantContext::SESSION_ID, 999999);

        $this->get(route('dashboard'))->assertRedirect(route('admin.index'));
        $this->assertNull(session(MerchantContext::SESSION_ID));
        $this->assertAuthenticatedAs($admin);
    }

    /**
     * @return array{0: Merchant, 1: Merchant}
     */
    private function twoMerchants(): array
    {
        return [
            Merchant::factory()->create(['name' => 'Alpha Merchant', 'is_active' => true]),
            Merchant::factory()->create(['name' => 'Beta Merchant', 'is_active' => true]),
        ];
    }

    private function createGateway(): Gateway
    {
        return Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => 'App\\Services\\Gateways\\Drivers\\CoinsDriver',
            'is_global_enabled' => true,
        ]);
    }

    private function worksheet(string $workbook): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'gatewayhub-context-report-');
        $this->assertNotFalse($temporaryPath);

        try {
            $this->assertNotFalse(file_put_contents($temporaryPath, $workbook));
            $archive = new ZipArchive;
            $this->assertTrue($archive->open($temporaryPath) === true);
            $contents = $archive->getFromName('xl/worksheets/sheet1.xml');
            $archive->close();
            $this->assertIsString($contents);

            return $contents;
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
