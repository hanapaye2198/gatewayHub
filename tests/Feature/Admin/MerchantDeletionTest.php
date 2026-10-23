<?php

namespace Tests\Feature\Admin;

use App\Models\Merchant;
use App\Models\MerchantWalletSetting;
use App\Models\Payment;
use App\Models\PlatformAuditLog;
use App\Models\PlatformFeeRule;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MerchantDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_deletes_merchant_and_all_related_data(): void
    {
        Storage::fake('public');
        $logoPath = UploadedFile::fake()->image('logo.png')->store('merchant-logos', 'public');

        $superAdmin = User::factory()->superAdmin()->create();
        $merchant = Merchant::factory()->create(['name' => 'Demo Store', 'logo_path' => $logoPath]);
        $merchantUser = User::factory()->create(['merchant_id' => $merchant->id]);
        $payment = Payment::factory()->for($merchant)->create();
        $wallet = Wallet::factory()->create(['merchant_id' => $merchant->id]);
        $walletTransaction = WalletTransaction::factory()->create(['wallet_id' => $wallet->id, 'payment_id' => $payment->id]);
        $walletSetting = MerchantWalletSetting::factory()->create(['merchant_id' => $merchant->id]);
        $feeRule = PlatformFeeRule::query()->create([
            'scope_type' => 'merchant',
            'scope_id' => $merchant->id,
            'fee_type' => 'percentage',
            'fee_value' => 1.5,
            'is_active' => true,
            'effective_from' => now()->subDay(),
        ]);

        $otherMerchant = Merchant::factory()->create();
        $otherUser = User::factory()->create(['merchant_id' => $otherMerchant->id]);
        $otherPayment = Payment::factory()->for($otherMerchant)->create();

        $this->actingAs($superAdmin)
            ->delete(route('admin.merchants.destroy', $merchant), ['confirm_name' => 'Demo Store'])
            ->assertRedirect(route('admin.merchants.index'))
            ->assertSessionHas('status', 'Merchant Demo Store was deleted.');

        $this->assertModelMissing($merchant);
        $this->assertModelMissing($merchantUser);
        $this->assertModelMissing($payment);
        $this->assertModelMissing($wallet);
        $this->assertModelMissing($walletTransaction);
        $this->assertModelMissing($walletSetting);
        $this->assertModelMissing($feeRule);
        Storage::disk('public')->assertMissing($logoPath);

        $this->assertModelExists($otherMerchant);
        $this->assertModelExists($otherUser);
        $this->assertModelExists($otherPayment);
        $this->assertModelExists($superAdmin);

        $audit = PlatformAuditLog::query()
            ->where('action', PlatformAuditLog::ACTION_MERCHANT_DELETED)
            ->firstOrFail();

        $this->assertSame($superAdmin->id, $audit->actor_user_id);
        $this->assertSame($merchant->id, $audit->merchant_id);
        $this->assertSame('Demo Store', $audit->old_values['name']);
        $this->assertSame(1, $audit->new_values['users_deleted']);
        $this->assertSame(1, $audit->new_values['payments_deleted']);
    }

    public function test_deletion_requires_matching_merchant_name(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $merchant = Merchant::factory()->create(['name' => 'Demo Store']);

        $this->actingAs($superAdmin)
            ->from(route('admin.merchants.show', $merchant))
            ->delete(route('admin.merchants.destroy', $merchant), ['confirm_name' => 'demo store'])
            ->assertRedirect(route('admin.merchants.show', $merchant))
            ->assertSessionHasErrors('confirm_name');

        $this->actingAs($superAdmin)
            ->from(route('admin.merchants.show', $merchant))
            ->delete(route('admin.merchants.destroy', $merchant))
            ->assertSessionHasErrors('confirm_name');

        $this->assertModelExists($merchant);
        $this->assertSame(0, PlatformAuditLog::query()->where('action', PlatformAuditLog::ACTION_MERCHANT_DELETED)->count());
    }

    public function test_admin_cannot_delete_merchant(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = Merchant::factory()->create(['name' => 'Demo Store']);

        $this->actingAs($admin)
            ->get(route('admin.merchants.show', $merchant))
            ->assertOk()
            ->assertDontSee('Delete merchant permanently');

        $this->actingAs($admin)
            ->delete(route('admin.merchants.destroy', $merchant), ['confirm_name' => 'Demo Store'])
            ->assertForbidden();

        $this->assertModelExists($merchant);
    }

    public function test_merchant_user_cannot_delete_merchant(): void
    {
        $merchant = Merchant::factory()->create(['name' => 'Demo Store']);
        $merchantUser = User::factory()->create([
            'merchant_id' => $merchant->id,
            'onboarding_gateways_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $this->actingAs($merchantUser)
            ->delete(route('admin.merchants.destroy', $merchant), ['confirm_name' => 'Demo Store'])
            ->assertRedirect(url('/dashboard'));

        $this->assertModelExists($merchant);
    }

    public function test_super_admin_sees_delete_section_on_merchant_page(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $merchant = Merchant::factory()->create(['name' => 'Demo Store']);

        $this->actingAs($superAdmin)
            ->get(route('admin.merchants.show', $merchant))
            ->assertOk()
            ->assertSee('Delete merchant permanently')
            ->assertSee('Type Demo Store to confirm');
    }
}
