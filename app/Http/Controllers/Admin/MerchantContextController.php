<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\User;
use App\Support\MerchantContext;
use Illuminate\Http\RedirectResponse;

class MerchantContextController extends Controller
{
    public function __construct(private MerchantContext $merchantContext) {}

    public function enter(Merchant $merchant): RedirectResponse
    {
        $this->authorize('access', $merchant);

        $actor = auth()->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        if (! $merchant->is_active) {
            return redirect()
                ->route('admin.merchants.show', $merchant)
                ->with('error', 'This merchant is suspended. Access Merchant is available for active merchants only.');
        }

        $this->merchantContext->enter($actor, $merchant);

        return redirect()->route('dashboard');
    }

    public function exit(): RedirectResponse
    {
        $actor = auth()->user();
        if (! $actor instanceof User || ! $actor->isPlatformOperator()) {
            abort(403);
        }

        $this->merchantContext->exit($actor);

        return redirect()
            ->route('admin.index')
            ->with('status', 'You left the merchant context.');
    }
}
