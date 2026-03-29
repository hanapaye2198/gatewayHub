<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMerchantGatewayStatusRequest;
use App\Http\Requests\Admin\UpdatePlatformGatewayConfigRequest;
use App\Models\Gateway;
use App\Models\Merchant;
use App\Models\MerchantGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class GatewaysController extends Controller
{
    public function index(): View
    {
        $gateways = Gateway::query()
            ->with('merchantGateways')
            ->orderBy('name')
            ->get();
        $merchants = Merchant::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $platformCredentialFields = [];
        $platformConfigs = [];
        foreach ($gateways as $gateway) {
            $fields = config('gateway_credentials.'.$gateway->code, []);
            $platformCredentialFields[$gateway->id] = is_array($fields) ? $fields : [];
            $platformConfigs[$gateway->id] = is_array($gateway->config_json) ? $gateway->config_json : [];
        }

        return view('admin.gateways.index', [
            'title' => 'Gateways',
            'gateways' => $gateways,
            'merchants' => $merchants,
            'platformCredentialFields' => $platformCredentialFields,
            'platformConfigs' => $platformConfigs,
        ]);
    }

    public function toggleEnabled(Gateway $gateway): RedirectResponse
    {
        try {
            $gateway->update(['is_global_enabled' => ! $gateway->is_global_enabled]);

            return redirect()->route('admin.gateways.index')
                ->with('status', $gateway->is_global_enabled
                    ? "Gateway \"{$gateway->name}\" has been enabled globally."
                    : "Gateway \"{$gateway->name}\" has been disabled globally.");
        } catch (\Throwable $e) {
            return redirect()->route('admin.gateways.index')
                ->with('error', 'Unable to update gateway. Please try again.');
        }
    }

    public function updateMerchantGateway(
        UpdateMerchantGatewayStatusRequest $request,
        Gateway $gateway,
        Merchant $merchant
    ): RedirectResponse {
        $isEnabled = (bool) $request->validated('is_enabled');
        if ($isEnabled && ! $gateway->is_global_enabled) {
            return redirect()
                ->route('admin.gateways.index')
                ->with('error', "Gateway \"{$gateway->name}\" is globally disabled and cannot be enabled per merchant.");
        }

        MerchantGateway::query()->updateOrCreate(
            [
                'merchant_id' => $merchant->id,
                'gateway_id' => $gateway->id,
            ],
            [
                'is_enabled' => $isEnabled,
            ]
        );

        $action = $isEnabled ? 'enabled' : 'disabled';

        return redirect()
            ->route('admin.gateways.index')
            ->with('status', "Gateway \"{$gateway->name}\" {$action} for {$merchant->name}.");
    }

    public function updatePlatformConfig(
        UpdatePlatformGatewayConfigRequest $request,
        Gateway $gateway
    ): RedirectResponse {
        $validated = $request->validated();
        $incomingConfig = is_array($validated['config'] ?? null) ? $validated['config'] : [];
        $existingConfig = is_array($gateway->config_json) ? $gateway->config_json : [];
        $fields = config('gateway_credentials.'.$gateway->code, []);
        $normalizedConfig = [];

        if (! is_array($fields) || $fields === []) {
            return redirect()
                ->route('admin.gateways.index')
                ->with('status', "Gateway \"{$gateway->name}\" does not require platform credential fields.");
        }

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = $field['key'] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }

            $value = $incomingConfig[$key] ?? null;
            $isMasked = (bool) ($field['masked'] ?? false);

            if ($isMasked) {
                if (is_string($value) && trim($value) !== '') {
                    $normalizedConfig[$key] = trim($value);
                } else {
                    $normalizedConfig[$key] = $existingConfig[$key] ?? '';
                }

                continue;
            }

            if (is_string($value)) {
                $normalizedConfig[$key] = trim($value);

                continue;
            }

            $normalizedConfig[$key] = $value;
        }

        $gateway->update([
            'config_json' => $normalizedConfig,
        ]);

        return redirect()
            ->route('admin.gateways.index')
            ->with('status', "Platform credentials updated for gateway \"{$gateway->name}\".");
    }
}
