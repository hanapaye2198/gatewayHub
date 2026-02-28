<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StorePaymentRequest;
use App\Models\Gateway;
use App\Services\Gateways\Exceptions\GatewayException;
use App\Services\PaymentCreationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CreatePaymentController extends Controller
{
    public function create(): View
    {
        $user = auth()->user();
        if ($user === null) {
            abort(403);
        }

        $enabledGateways = Gateway::query()
            ->where('is_global_enabled', true)
            ->whereHas('merchantGateways', function ($q) use ($user) {
                $q->where('user_id', $user->id)->where('is_enabled', true);
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('dashboard.payments.create', [
            'enabledGateways' => $enabledGateways,
        ]);
    }

    public function store(StorePaymentRequest $request, PaymentCreationService $creationService): RedirectResponse
    {
        $merchant = auth()->user();
        if ($merchant === null) {
            abort(403);
        }

        $validated = $request->validated();
        $data = [
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'reference' => 'DASH-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4)),
        ];

        try {
            $result = $creationService->create($merchant, $validated['gateway'], $data);
        } catch (GatewayException $e) {
            return back()->withInput()->withErrors(['gateway' => $e->getMessage()]);
        }

        if (is_string($result['redirect_url'] ?? null) && $result['redirect_url'] !== '') {
            return redirect()->away($result['redirect_url']);
        }

        return redirect()->route('dashboard.payments.show', $result['payment'])->with('success', __('Payment created.'));
    }
}
