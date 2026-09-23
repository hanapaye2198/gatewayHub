<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlatformAdministratorRequest;
use App\Http\Requests\Admin\UpdatePlatformAdministratorRequest;
use App\Models\User;
use App\Services\Admin\PlatformAdminManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PlatformAdministratorsController extends Controller
{
    public function __construct(private PlatformAdminManager $administrators) {}

    public function index(): View
    {
        $users = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->whereNull('merchant_id')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_active', 'created_at']);

        return view('admin.administrators.index', [
            'title' => 'Administrators',
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        return view('admin.administrators.create', [
            'title' => 'Create administrator',
        ]);
    }

    public function store(StorePlatformAdministratorRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->administrators->create([
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'password' => (string) $validated['password'],
            'is_active' => (bool) $validated['is_active'],
        ]);

        return redirect()
            ->route('admin.administrators.index')
            ->with('status', 'Administrator created.');
    }

    public function edit(int $administrator): View
    {
        $platformAdmin = $this->administrators->find($administrator);

        return view('admin.administrators.edit', [
            'title' => 'Edit administrator',
            'platformAdmin' => $platformAdmin,
        ]);
    }

    public function update(UpdatePlatformAdministratorRequest $request, int $administrator): RedirectResponse
    {
        $platformAdmin = $this->administrators->find($administrator);
        $validated = $request->validated();

        $this->administrators->update($platformAdmin, [
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'password' => $validated['password'] ?? null,
            'is_active' => (bool) $validated['is_active'],
        ]);

        return redirect()
            ->route('admin.administrators.index')
            ->with('status', 'Administrator updated.');
    }

    public function toggle(int $administrator): RedirectResponse
    {
        $platformAdmin = $this->administrators->find($administrator);
        $platformAdmin = $this->administrators->toggleActive($platformAdmin);

        return redirect()
            ->route('admin.administrators.index')
            ->with('status', $platformAdmin->is_active ? 'Administrator enabled.' : 'Administrator disabled.');
    }
}
