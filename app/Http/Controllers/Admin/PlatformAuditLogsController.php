<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterPlatformAuditLogsRequest;
use App\Models\Merchant;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Contracts\View\View;

class PlatformAuditLogsController extends Controller
{
    private const PER_PAGE = 25;

    public function index(FilterPlatformAuditLogsRequest $request): View
    {
        $this->authorize('viewAny', PlatformAuditLog::class);

        $filters = $request->validated();

        $logs = PlatformAuditLog::query()
            ->select([
                'id',
                'actor_user_id',
                'action',
                'merchant_id',
                'target_type',
                'target_id',
                'description',
                'ip_address',
                'created_at',
            ])
            ->with([
                'actor:id,name',
                'merchant:id,name',
            ])
            ->filtered($filters)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'title' => 'Audit logs',
            'logs' => $logs,
            'filters' => $filters,
            'actions' => PlatformAuditLog::ACTIONS,
            'merchants' => Merchant::query()->orderBy('name')->get(['id', 'name']),
            'actors' => User::query()->where('role', User::ROLE_ADMIN)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(PlatformAuditLog $platformAuditLog): View
    {
        $this->authorize('view', $platformAuditLog);

        $platformAuditLog->load([
            'actor:id,name,email',
            'merchant:id,name',
        ]);

        return view('admin.audit-logs.show', [
            'title' => 'Audit log',
            'log' => $platformAuditLog,
        ]);
    }
}
