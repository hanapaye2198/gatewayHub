<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterAdminDashboardRequest;
use App\Models\Merchant;
use App\Models\Payment;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(FilterAdminDashboardRequest $request): View
    {
        $validated = $request->validated();
        $selectedClientId = isset($validated['client_id']) ? (int) $validated['client_id'] : null;

        $clients = Merchant::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $collectionsByClient = Payment::query()
            ->where('status', 'paid')
            ->selectRaw('merchant_id, SUM(amount) as total')
            ->groupBy('merchant_id')
            ->pluck('total', 'merchant_id');

        $clientRows = $clients->map(static function (Merchant $client) use ($collectionsByClient): array {
            return [
                'id' => (int) $client->id,
                'name' => $client->name,
                'total_collections' => (float) ($collectionsByClient[$client->id] ?? 0),
            ];
        });

        $totalCollections = (float) $clientRows->sum('total_collections');
        $filteredCollections = $selectedClientId === null
            ? $totalCollections
            : (float) ($collectionsByClient[$selectedClientId] ?? 0);
        $selectedClientName = $selectedClientId === null
            ? null
            : $clients->firstWhere('id', $selectedClientId)?->name;

        return view('admin.dashboard', [
            'title' => 'Dashboard',
            'totalCollections' => $totalCollections,
            'filteredCollections' => $filteredCollections,
            'selectedClientId' => $selectedClientId,
            'selectedClientName' => $selectedClientName,
            'clientRows' => $clientRows,
        ]);
    }
}
