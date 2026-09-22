<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMerchantUserRequest;
use App\Http\Requests\Admin\UpdateMerchantUserRequest;
use App\Models\Merchant;
use App\Models\User;
use App\Services\Admin\MerchantUserManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class MerchantUsersController extends Controller
{
    public function __construct(private MerchantUserManager $merchantUsers) {}

    public function index(Merchant $merchant): View
    {
        $this->authorize('manageUsers', $merchant);

        $users = $merchant->users()
            ->where('role', User::ROLE_MERCHANT_USER)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_active', 'created_at']);

        return view('admin.merchants.users.index', [
            'title' => $merchant->name.' users',
            'merchant' => $merchant,
            'users' => $users,
        ]);
    }

    public function create(Merchant $merchant): View
    {
        $this->authorize('manageUsers', $merchant);

        return view('admin.merchants.users.create', [
            'title' => 'Create user',
            'merchant' => $merchant,
        ]);
    }

    public function store(StoreMerchantUserRequest $request, Merchant $merchant): RedirectResponse
    {
        $this->authorize('manageUsers', $merchant);

        $validated = $request->validated();

        $this->merchantUsers->create($merchant, [
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'password' => (string) $validated['password'],
            'is_active' => (bool) $validated['is_active'],
        ]);

        return redirect()
            ->route('admin.merchants.users.index', $merchant)
            ->with('status', 'Merchant user created.');
    }

    public function edit(Merchant $merchant, int $user): View
    {
        $this->authorize('manageUsers', $merchant);

        $merchantUser = $this->merchantUsers->find($merchant, $user);

        return view('admin.merchants.users.edit', [
            'title' => 'Edit user',
            'merchant' => $merchant,
            'merchantUser' => $merchantUser,
        ]);
    }

    public function update(UpdateMerchantUserRequest $request, Merchant $merchant, int $user): RedirectResponse
    {
        $this->authorize('manageUsers', $merchant);

        $merchantUser = $this->merchantUsers->find($merchant, $user);
        $validated = $request->validated();

        $this->merchantUsers->update($merchant, $merchantUser, [
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'password' => $validated['password'] ?? null,
            'is_active' => (bool) $validated['is_active'],
        ]);

        return redirect()
            ->route('admin.merchants.users.index', $merchant)
            ->with('status', 'Merchant user updated.');
    }

    public function toggle(Merchant $merchant, int $user): RedirectResponse
    {
        $this->authorize('manageUsers', $merchant);

        $merchantUser = $this->merchantUsers->find($merchant, $user);
        $this->merchantUsers->toggleActive($merchant, $merchantUser);

        return redirect()
            ->route('admin.merchants.users.index', $merchant)
            ->with('status', $merchantUser->is_active ? 'Merchant user enabled.' : 'Merchant user disabled.');
    }
}
