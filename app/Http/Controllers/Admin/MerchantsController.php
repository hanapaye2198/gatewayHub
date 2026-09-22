<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMerchantRequest;
use App\Http\Requests\Admin\UpdateMerchantRequest;
use App\Models\Merchant;
use App\Models\MerchantGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;

class MerchantsController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Merchant::class);

        $merchants = Merchant::query()
            ->with('users')
            ->orderBy('name')
            ->get();

        return view('admin.merchants.index', [
            'title' => 'Merchants',
            'merchants' => $merchants,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Merchant::class);

        return view('admin.merchants.create', [
            'title' => 'Create merchant',
        ]);
    }

    public function store(StoreMerchantRequest $request): RedirectResponse
    {
        $this->authorize('create', Merchant::class);

        $merchant = Merchant::query()->create([
            ...$this->merchantProfileAttributes($request->validated()),
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.merchants.show', $merchant)
            ->with('status', 'Merchant created.');
    }

    public function show(Merchant $merchant): View
    {
        $this->authorize('view', $merchant);

        $merchant->loadCount(['users', 'payments']);

        return view('admin.merchants.show', [
            'title' => $merchant->name,
            'merchant' => $merchant,
            'enabledGateways' => $this->enabledGateways($merchant),
            'apiKeyConfigured' => $merchant->hasApiKey(),
            'webhookSecretConfigured' => filled($merchant->getRawOriginal('webhook_secret')),
        ]);
    }

    public function edit(Merchant $merchant): View
    {
        $this->authorize('update', $merchant);

        return view('admin.merchants.edit', [
            'title' => 'Edit merchant',
            'merchant' => $merchant,
        ]);
    }

    public function update(UpdateMerchantRequest $request, Merchant $merchant): RedirectResponse
    {
        $this->authorize('update', $merchant);

        $merchant->update($this->merchantProfileAttributes($request->validated()));

        return redirect()
            ->route('admin.merchants.show', $merchant)
            ->with('status', 'Merchant updated.');
    }

    public function toggleActive(Merchant $merchant): RedirectResponse
    {
        $this->authorize('update', $merchant);

        $merchant->update(['is_active' => ! $merchant->is_active]);

        $merchant->users()->update(['is_active' => $merchant->is_active]);

        return redirect()->route('admin.merchants.index')
            ->with('status', $merchant->is_active ? 'Merchant activated.' : 'Merchant suspended.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{name: string, email: string, theme_color: ?string, qr_display_name: ?string, webhook_url: ?string}
     */
    private function merchantProfileAttributes(array $validated): array
    {
        return [
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'theme_color' => $this->blankToNull($validated['theme_color'] ?? null),
            'qr_display_name' => $this->blankToNull($validated['qr_display_name'] ?? null),
            'webhook_url' => $this->blankToNull($validated['webhook_url'] ?? null),
        ];
    }

    private function blankToNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Enabled gateway names only. Credential columns stay out of the query.
     *
     * @return Collection<int, MerchantGateway>
     */
    private function enabledGateways(Merchant $merchant): Collection
    {
        return $merchant->merchantGateways()
            ->where('is_enabled', true)
            ->with('gateway:id,name,code')
            ->get(['id', 'merchant_id', 'gateway_id', 'is_enabled']);
    }
}
