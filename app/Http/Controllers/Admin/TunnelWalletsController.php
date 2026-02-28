<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSurepayBatchSettingRequest;
use App\Http\Requests\Admin\UpdateTunnelWalletSettingRequest;
use App\Models\MerchantWalletSetting;
use App\Models\SurepayBatchSetting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Billing\WalletSettlementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class TunnelWalletsController extends Controller
{
    public function index(): View
    {
        $this->ensureWalletSettlementEnabled();

        $merchants = User::query()
            ->where('role', 'merchant')
            ->with('merchantWalletSetting')
            ->orderBy('name')
            ->get();

        $pendingSettlements = WalletTransaction::query()
            ->where('entry_type', WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE)
            ->where('is_settled', false)
            ->count();

        $tunnelSendingSetting = SurepayBatchSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'batch_interval_minutes' => 15,
                'batch_interval_seconds' => 900,
                'tax_percentage' => 0,
                'tax_absolute_value' => 0,
                'updated_by' => auth()->id(),
            ]
        );

        return view('admin.tunnel-wallets.index', [
            'title' => 'SurePay Settlement Controls',
            'merchants' => $merchants,
            'pendingSettlements' => $pendingSettlements,
            'tunnelSendingSetting' => $tunnelSendingSetting,
            'tunnelSendingIntervalValue' => $tunnelSendingSetting->intervalValue(),
            'tunnelSendingIntervalUnit' => $tunnelSendingSetting->intervalUnit(),
        ]);
    }

    public function updateSetting(UpdateTunnelWalletSettingRequest $request, User $user): RedirectResponse
    {
        $this->ensureWalletSettlementEnabled();

        if ($user->role !== 'merchant') {
            abort(404);
        }

        $setting = MerchantWalletSetting::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'tunnel_wallet_enabled' => true,
                'auto_settle_to_real_wallet' => true,
                'default_currency' => 'PHP',
            ]
        );

        $validated = $request->validated();
        $secretInput = trim((string) ($validated['tunnel_client_secret'] ?? ''));
        $hasCurrentSecret = is_string($setting->tunnel_client_secret) && $setting->tunnel_client_secret !== '';
        if ($secretInput === '' && ! $hasCurrentSecret) {
            return redirect()
                ->route('admin.surepay-wallets.index')
                ->withErrors(['tunnel_client_secret' => 'Client Secret is required.']);
        }

        $setting->update([
            'tunnel_wallet_enabled' => true,
            'auto_settle_to_real_wallet' => (bool) $validated['auto_settle_to_real_wallet'],
            'default_currency' => strtoupper((string) $validated['default_currency']),
            'tunnel_client_id' => trim((string) $validated['tunnel_client_id']),
            'tunnel_client_secret' => $secretInput !== '' ? $secretInput : $setting->tunnel_client_secret,
            'tunnel_webhook_id' => trim((string) ($validated['tunnel_webhook_id'] ?? '')) ?: null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('admin.surepay-wallets.index')
            ->with('status', "SurePay settlement configuration updated for {$user->name}.");
    }

    public function settleBatch(WalletSettlementService $service): RedirectResponse
    {
        $this->ensureWalletSettlementEnabled();

        $settled = $service->settlePendingNetBatch(null, 200);

        return redirect()
            ->route('admin.surepay-wallets.index')
            ->with('status', "Batch settlement completed. Settled {$settled} entries.");
    }

    public function updateSurepaySendingSetting(UpdateSurepayBatchSettingRequest $request): RedirectResponse
    {
        $this->ensureWalletSettlementEnabled();

        $validated = $request->validated();
        $multiplier = match ($validated['batch_interval_unit']) {
            SurepayBatchSetting::INTERVAL_UNIT_SECONDS => 1,
            SurepayBatchSetting::INTERVAL_UNIT_DAYS => 86400,
            SurepayBatchSetting::INTERVAL_UNIT_WEEKS => 604800,
            SurepayBatchSetting::INTERVAL_UNIT_MINUTES => 60,
            default => 1,
        };
        $intervalSeconds = (int) $validated['batch_interval_value'] * $multiplier;
        $intervalMinutes = (int) ceil($intervalSeconds / 60);

        $setting = SurepayBatchSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'batch_interval_minutes' => 15,
                'batch_interval_seconds' => 900,
                'tax_percentage' => 0,
                'tax_absolute_value' => 0,
                'updated_by' => auth()->id(),
            ]
        );

        $setting->update([
            'batch_interval_minutes' => $intervalMinutes,
            'batch_interval_seconds' => $intervalSeconds,
            'tax_percentage' => round((float) $validated['tax_percentage'], 2),
            'tax_absolute_value' => 0,
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.surepay-wallets.index')
            ->with('status', 'SurePay settlement sending configuration updated.');
    }

    private function ensureWalletSettlementEnabled(): void
    {
        if (! config('surepay.features.wallet_settlement', false)) {
            abort(404);
        }
    }
}
