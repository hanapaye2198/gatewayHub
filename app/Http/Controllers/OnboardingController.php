<?php

namespace App\Http\Controllers;

use App\Http\Requests\Onboarding\StoreOnboardingBusinessRequest;
use App\Http\Requests\Onboarding\StoreOnboardingGatewaysRequest;
use App\Models\Gateway;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function business(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->onboarding_completed_at !== null) {
            return redirect()->route('dashboard');
        }

        if ($user->merchant_id !== null) {
            return redirect()->route('onboarding.gateways');
        }

        return view('pages::onboarding.business', [
            'step' => 2,
            'totalSteps' => 4,
        ]);
    }

    public function storeBusiness(StoreOnboardingBusinessRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->merchant_id !== null) {
            return redirect()->route('onboarding.gateways');
        }

        $validated = $request->validated();

        $apiKey = Str::random(64);
        $apiSecretPlain = Str::random(64);

        DB::transaction(function () use ($user, $validated, $apiKey, $apiSecretPlain): void {
            $merchant = Merchant::create([
                'name' => $validated['business_name'],
                'email' => $validated['business_email'],
                'is_active' => true,
                'api_key' => $apiKey,
                'api_key_generated_at' => now(),
                'api_secret' => Hash::make($apiSecretPlain),
            ]);

            $user->forceFill(['merchant_id' => $merchant->id])->save();
        });

        $request->session()->put([
            'onboarding.api_key' => $apiKey,
            'onboarding.api_secret' => $apiSecretPlain,
        ]);

        return redirect()->route('onboarding.gateways');
    }

    public function gateways(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->onboarding_completed_at !== null) {
            return redirect()->route('dashboard');
        }

        if ($user->merchant_id === null) {
            return redirect()->route('onboarding.business');
        }

        $gateways = Gateway::query()->orderBy('name')->get();

        $selectedIds = $user->merchant?->merchantGateways()->pluck('gateway_id')->all() ?? [];

        return view('pages::onboarding.gateways', [
            'step' => 3,
            'totalSteps' => 4,
            'gateways' => $gateways,
            'selectedIds' => $selectedIds,
        ]);
    }

    public function storeGateways(StoreOnboardingGatewaysRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->merchant_id === null) {
            return redirect()->route('onboarding.business');
        }

        $merchant = $user->merchant;
        if ($merchant === null) {
            return redirect()->route('onboarding.business');
        }

        $ids = $request->validated()['gateway_ids'] ?? [];

        DB::transaction(function () use ($merchant, $ids): void {
            $sync = [];
            foreach ($ids as $gid) {
                $sync[(int) $gid] = ['is_enabled' => true];
            }
            $merchant->gateways()->sync($sync);
        });

        $user->forceFill(['onboarding_gateways_at' => now()])->save();

        return redirect()->route('onboarding.api-keys');
    }

    public function apiKeys(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->onboarding_completed_at !== null) {
            return redirect()->route('dashboard');
        }

        if ($user->merchant_id === null) {
            return redirect()->route('onboarding.business');
        }

        if ($user->onboarding_gateways_at === null) {
            return redirect()->route('onboarding.gateways');
        }

        $apiKey = $request->session()->get('onboarding.api_key');
        $apiSecret = $request->session()->get('onboarding.api_secret');

        $merchant = $user->merchant;
        $keysMissing = $apiKey === null || $apiSecret === null;

        return view('pages::onboarding.api-keys', [
            'step' => 4,
            'totalSteps' => 4,
            'apiKey' => $apiKey,
            'apiSecret' => $apiSecret,
            'keysMissing' => $keysMissing,
            'merchantHasCredentials' => $merchant?->hasApiKey() ?? false,
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->merchant_id === null) {
            return redirect()->route('onboarding.business');
        }

        if ($user->onboarding_gateways_at === null) {
            return redirect()->route('onboarding.gateways');
        }

        $merchant = $user->merchant;
        if ($merchant === null || ! $merchant->hasApiKey()) {
            return redirect()->route('onboarding.business')
                ->withErrors(['merchant' => __('Unable to complete onboarding. Please contact support.')]);
        }

        $user->forceFill(['onboarding_completed_at' => now()])->save();

        $request->session()->forget(['onboarding.api_key', 'onboarding.api_secret']);

        return redirect()->route('dashboard');
    }
}
