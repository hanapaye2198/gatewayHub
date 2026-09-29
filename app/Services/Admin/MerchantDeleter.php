<?php

namespace App\Services\Admin;

use App\Models\Merchant;
use App\Models\PlatformAuditLog;
use App\Models\PlatformFeeRule;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Permanently deletes a merchant together with its users, wallets and payment data.
 */
final class MerchantDeleter
{
    public function __construct(private PlatformAuditService $audit) {}

    public function delete(Merchant $merchant): void
    {
        $logoPath = $merchant->logo_path;

        DB::transaction(function () use ($merchant): void {
            $merchant->loadCount(['users', 'payments']);

            $this->audit->record(
                PlatformAuditLog::ACTION_MERCHANT_DELETED,
                $merchant,
                $merchant,
                $this->audit->merchantSnapshot($merchant),
                [
                    'users_deleted' => (int) $merchant->users_count,
                    'payments_deleted' => (int) $merchant->payments_count,
                ],
                'Deleted merchant '.$merchant->name.' and all of its data.',
            );

            $merchant->users()
                ->where('role', User::ROLE_MERCHANT_USER)
                ->delete();

            Wallet::query()->where('merchant_id', $merchant->id)->delete();

            PlatformFeeRule::query()
                ->where('scope_type', 'merchant')
                ->where('scope_id', $merchant->id)
                ->delete();

            $merchant->delete();
        });

        if (is_string($logoPath) && $logoPath !== '') {
            Storage::disk('public')->delete($logoPath);
        }
    }
}
