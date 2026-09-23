<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePlatformFeeRequest;
use App\Models\PlatformFeeRule;
use App\Services\Admin\PlatformFeeConfigurator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PlatformFeeController extends Controller
{
    public function __construct(private PlatformFeeConfigurator $fees) {}

    public function edit(): View
    {
        return view('admin.platform-fee.edit', [
            'title' => 'Platform Fee',
            'percentage' => number_format(PlatformFeeRule::configuredPercentage(), 2, '.', ''),
        ]);
    }

    public function update(UpdatePlatformFeeRequest $request): RedirectResponse
    {
        $this->fees->updatePercentage($request->validated('percentage'));

        return redirect()
            ->route('admin.platform-fee.edit')
            ->with('status', 'Platform fee saved.');
    }
}
