<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMerchantPlatformFeeRequest;
use App\Http\Requests\Admin\UpdatePlatformFeeRequest;
use App\Models\Merchant;
use App\Models\PlatformFeeRule;
use App\Services\Admin\PlatformFeeConfigurator;
use App\Services\Billing\PlatformFeeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PlatformFeeController extends Controller
{
    public function __construct(private PlatformFeeConfigurator $fees) {}

    public function edit(PlatformFeeService $platformFees): View
    {
        return view('admin.platform-fee.edit', [
            'title' => 'Platform Fee',
            'percentage' => number_format(PlatformFeeRule::configuredPercentage(), 2, '.', ''),
            'convenienceFee' => number_format($platformFees->convenienceFeeAmount(), 2, '.', ''),
        ]);
    }

    public function update(UpdatePlatformFeeRequest $request): RedirectResponse
    {
        $this->fees->updatePercentage($request->validated('percentage'));

        return redirect()
            ->route('admin.platform-fee.edit')
            ->with('status', 'Platform fee saved.');
    }

    public function updateMerchant(UpdateMerchantPlatformFeeRequest $request, Merchant $merchant): RedirectResponse
    {
        $this->fees->updateMerchantPercentage($merchant, $request->validated('percentage'));

        return redirect()
            ->route('admin.merchants.show', $merchant)
            ->with('status', 'Merchant platform fee saved.');
    }
}
