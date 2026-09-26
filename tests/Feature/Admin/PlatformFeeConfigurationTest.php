<?php

namespace Tests\Feature\Admin;

use App\Models\Payment;
use App\Models\PlatformAuditLog;
use App\Models\PlatformFeeRule;
use App\Models\User;
use App\Services\Billing\PlatformFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformFeeConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_and_update_the_platform_fee(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get(route('admin.platform-fee.edit'))
            ->assertOk()
            ->assertSee('Current Rate: 1.50%')
            ->assertSee('added on top of it');

        $this->actingAs($superAdmin)
            ->put(route('admin.platform-fee.update'), [
                'percentage' => '2',
            ])
            ->assertRedirect(route('admin.platform-fee.edit'))
            ->assertSessionHas('status');

        $rule = PlatformFeeRule::activeGlobalPercentageRule();
        $this->assertNotNull($rule);
        $this->assertSame('0.0200', (string) $rule->fee_value);
        $this->assertSame('2.00', $rule->percentage());

        $log = PlatformAuditLog::query()->where('action', PlatformAuditLog::ACTION_PLATFORM_FEE_UPDATED)->firstOrFail();
        $this->assertSame($superAdmin->id, $log->actor_user_id);
        $this->assertSame('1.50', $log->old_values['percentage'] ?? null);
        $this->assertSame('2.00', $log->new_values['percentage'] ?? null);
        $this->assertNotNull($log->ip_address);
        $this->assertNotNull($log->user_agent);
        $this->assertNotNull($log->created_at);
        $encoded = json_encode($log->only(['old_values', 'new_values', 'description']));
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('password', strtolower($encoded));
        $this->assertStringNotContainsString('secret', strtolower($encoded));

        $this->actingAs($superAdmin)
            ->get(route('admin.platform-fee.edit'))
            ->assertOk()
            ->assertSee('Current Rate: 2.00%');
    }

    public function test_admin_and_merchant_user_cannot_update_the_platform_fee(): void
    {
        $admin = User::factory()->admin()->create();
        $merchantUser = User::factory()->create();
        $original = (string) PlatformFeeRule::activeGlobalPercentageRule()?->fee_value;

        $this->actingAs($admin)
            ->put(route('admin.platform-fee.update'), ['percentage' => '4'])
            ->assertForbidden();

        $this->actingAs($merchantUser)
            ->put(route('admin.platform-fee.update'), ['percentage' => '4'])
            ->assertRedirect(url('/dashboard'));

        $this->assertSame($original, (string) PlatformFeeRule::activeGlobalPercentageRule()?->fee_value);
        $this->assertSame(0, PlatformAuditLog::query()->where('action', PlatformAuditLog::ACTION_PLATFORM_FEE_UPDATED)->count());
    }

    public function test_invalid_platform_fee_values_are_rejected(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $original = (string) PlatformFeeRule::activeGlobalPercentageRule()?->fee_value;

        foreach (['-1', 'abc', '101', '1.555', '1e2'] as $percentage) {
            $this->actingAs($superAdmin)
                ->from(route('admin.platform-fee.edit'))
                ->put(route('admin.platform-fee.update'), ['percentage' => $percentage])
                ->assertRedirect(route('admin.platform-fee.edit'))
                ->assertSessionHasErrors('percentage');
        }

        $this->assertSame($original, (string) PlatformFeeRule::activeGlobalPercentageRule()?->fee_value);
        $this->assertSame(0, PlatformAuditLog::query()->where('action', PlatformAuditLog::ACTION_PLATFORM_FEE_UPDATED)->count());
    }

    public function test_zero_percent_is_accepted_and_used_by_the_existing_formula(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->put(route('admin.platform-fee.update'), ['percentage' => '0'])
            ->assertRedirect(route('admin.platform-fee.edit'));

        $calculated = app(PlatformFeeService::class)->calculateFromConfig(1000);

        $this->assertSame('0.00', number_format($calculated['fee_amount'], 2, '.', ''));
        $this->assertSame('1000.00', number_format($calculated['net_amount'], 2, '.', ''));
    }

    public function test_a_rate_change_applies_only_to_later_payments(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $merchantUser = User::factory()->create();
        $service = app(PlatformFeeService::class);

        $paymentA = Payment::factory()->create([
            'merchant_id' => $merchantUser->merchant_id,
            'amount' => 1000,
            'status' => 'paid',
            'paid_at' => now(),
            'platform_fee' => null,
            'net_amount' => null,
        ]);

        $service->record($paymentA);

        $paymentA->refresh();
        $originalFeeId = $paymentA->platformFee?->id;
        $this->assertSame('15.00', (string) $paymentA->platform_fee);
        $this->assertSame('985.00', (string) $paymentA->net_amount);
        $this->assertSame('0.0150', (string) $paymentA->platformFee?->fee_rate);
        $this->assertSame('15.00', (string) $paymentA->platformFee?->fee_amount);

        $this->actingAs($superAdmin)
            ->put(route('admin.platform-fee.update'), ['percentage' => '2.00'])
            ->assertRedirect(route('admin.platform-fee.edit'));

        config(['platform.fees.percentage' => 9]);

        $paymentB = Payment::factory()->create([
            'merchant_id' => $merchantUser->merchant_id,
            'amount' => 1000,
            'status' => 'paid',
            'paid_at' => now(),
            'platform_fee' => null,
            'net_amount' => null,
        ]);

        $service->record($paymentB);

        $paymentA->refresh();
        $paymentB->refresh();

        $this->assertSame($originalFeeId, $paymentA->platformFee?->id);
        $this->assertSame('15.00', (string) $paymentA->platform_fee);
        $this->assertSame('985.00', (string) $paymentA->net_amount);
        $this->assertSame('0.0150', (string) $paymentA->platformFee?->fee_rate);
        $this->assertSame('15.00', (string) $paymentA->platformFee?->fee_amount);

        $this->assertSame('20.00', (string) $paymentB->platform_fee);
        $this->assertSame('980.00', (string) $paymentB->net_amount);
        $this->assertSame('0.0200', (string) $paymentB->platformFee?->fee_rate);
        $this->assertSame('20.00', (string) $paymentB->platformFee?->fee_amount);
        $this->assertSame('1000.00', (string) $paymentA->amount);
        $this->assertSame('1000.00', (string) $paymentB->amount);
    }
}
