<?php

namespace App\Livewire\Admin;

use App\Models\Gateway;
use Livewire\Component;

class Gateways extends Component
{
    public function render()
    {
        return view('livewire.admin.gateways', [
            'gateways' => Gateway::all(),
        ]);
    }

    public function toggle(int $gatewayId): void
    {
        $gateway = Gateway::findOrFail($gatewayId);
        $gateway->update(['is_global_enabled' => ! $gateway->is_global_enabled]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Gateway \"{$gateway->name}\" has been ".($gateway->is_global_enabled ? 'enabled' : 'disabled').'.',
        ]);
    }
}
