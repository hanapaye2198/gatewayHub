<?php

namespace App\Livewire\Admin;

use App\Models\Gateway;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Gateways extends Component
{
    public function render(): View
    {
        return view('livewire.admin.gateways', [
            'gateways' => Gateway::query()->get(),
        ]);
    }

    public function toggle(int $gatewayId): void
    {
        $gateway = Gateway::query()->findOrFail($gatewayId);
        $gateway->update(['is_global_enabled' => ! $gateway->is_global_enabled]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Gateway \"{$gateway->name}\" has been ".($gateway->is_global_enabled ? 'enabled' : 'disabled').'.',
        ]);
    }
}
