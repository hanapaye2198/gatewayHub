<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class GatewaysController extends Controller
{
    public function index(): View
    {
        $gateways = Gateway::query()->orderBy('name')->get();

        return view('admin.gateways.index', [
            'title' => 'Gateways',
            'gateways' => $gateways,
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
}
