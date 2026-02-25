<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class MerchantsController extends Controller
{
    public function index(): View
    {
        $merchants = User::query()
            ->where('role', 'merchant')
            ->orderBy('name')
            ->get();

        return view('admin.merchants.index', [
            'title' => 'Merchants',
            'merchants' => $merchants,
        ]);
    }

    public function toggleActive(User $user): RedirectResponse
    {
        if ($user->role !== 'merchant') {
            abort(404);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return redirect()->route('admin.merchants.index')
            ->with('status', $user->is_active ? 'Merchant activated.' : 'Merchant deactivated.');
    }
}
