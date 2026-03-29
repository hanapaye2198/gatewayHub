<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class MerchantsController extends Controller
{
    public function index(): View
    {
        $merchants = Merchant::query()
            ->with('users')
            ->orderBy('name')
            ->get();

        return view('admin.merchants.index', [
            'title' => 'Merchants',
            'merchants' => $merchants,
        ]);
    }

    public function toggleActive(Merchant $merchant): RedirectResponse
    {
        $merchant->update(['is_active' => ! $merchant->is_active]);

        User::query()->where('merchant_id', $merchant->id)->update(['is_active' => $merchant->is_active]);

        return redirect()->route('admin.merchants.index')
            ->with('status', $merchant->is_active ? 'Merchant activated.' : 'Merchant deactivated.');
    }
}
