<?php

use App\Models\Gateway;
use App\Models\MerchantGateway;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component
{
    #[Layout('layouts.app', ['title' => 'Gateways'])]
    public array $gatewayStates = [];

    public ?array $coinsPingMessage = null;

    public function mount(): void
    {
        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $gateways = Gateway::query()->orderBy('name')->get();
        foreach ($gateways as $gateway) {
            $merchantGateway = MerchantGateway::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'gateway_id' => $gateway->id,
                ],
                [
                    'is_enabled' => false,
                    'config_json' => [],
                ]
            );

            $this->gatewayStates[$gateway->id] = [
                'enabled' => (bool) $merchantGateway->is_enabled,
                'global_enabled' => (bool) $gateway->is_global_enabled,
            ];
        }
    }

    #[Computed]
    public function gateways(): \Illuminate\Support\Collection
    {
        return Gateway::query()->orderBy('name')->get();
    }

    public function toggleEnabled(int $gatewayId): void
    {
        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $gateway = Gateway::query()->find($gatewayId);
        if (! $gateway instanceof Gateway) {
            return;
        }

        $this->resetErrorBag('gateway.'.$gatewayId);

        $targetEnabledState = (bool) ($this->gatewayStates[$gatewayId]['enabled'] ?? false);
        if ($targetEnabledState && ! $gateway->is_global_enabled) {
            $this->gatewayStates[$gatewayId]['enabled'] = false;
            $this->gatewayStates[$gatewayId]['global_enabled'] = false;
            $this->addError('gateway.'.$gatewayId, __('Gateway is currently disabled by SurePay admin.'));

            return;
        }

        $merchantGateway = MerchantGateway::query()
            ->where('user_id', $user->id)
            ->where('gateway_id', $gatewayId)
            ->first();

        MerchantGateway::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'gateway_id' => $gatewayId,
            ],
            [
                'is_enabled' => $targetEnabledState,
                'config_json' => is_array($merchantGateway?->config_json) ? $merchantGateway->config_json : [],
            ]
        );

        $this->gatewayStates[$gatewayId]['enabled'] = $targetEnabledState;
        $this->gatewayStates[$gatewayId]['global_enabled'] = (bool) $gateway->is_global_enabled;
    }

    public function toggleFromButton(int $gatewayId): void
    {
        $current = (bool) ($this->gatewayStates[$gatewayId]['enabled'] ?? false);
        $this->gatewayStates[$gatewayId]['enabled'] = ! $current;
        $this->toggleEnabled($gatewayId);
    }

    public function pingCoinsPublicApi(): void
    {
        $this->coinsPingMessage = null;

        $apiBase = (string) config('coins.gateway.api_base', 'sandbox');
        $baseUrl = $apiBase === 'prod'
            ? 'https://api.pro.coins.ph'
            : 'https://api.9001.pl-qa.coinsxyz.me';

        try {
            $response = Http::timeout(10)->acceptJson()->get($baseUrl.'/openapi/v1/ping');
        } catch (\Throwable $exception) {
            $this->coinsPingMessage = [
                'type' => 'error',
                'message' => __('Ping failed: :message', ['message' => $exception->getMessage()]),
            ];

            return;
        }

        if (! $response->successful()) {
            $this->coinsPingMessage = [
                'type' => 'error',
                'message' => __('Ping failed with HTTP :code.', ['code' => (string) $response->status()]),
            ];

            return;
        }

        $this->coinsPingMessage = [
            'type' => 'success',
            'message' => __('Coins API ping successful (HTTP :code).', ['code' => (string) $response->status()]),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-8">
    <div class="rounded-xl border border-zinc-200/80 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Gateways') }}</h1>
            <flux:button type="button" variant="outline" wire:click="pingCoinsPublicApi" wire:loading.attr="disabled" wire:target="pingCoinsPublicApi">
                <flux:icon.loading wire:loading wire:target="pingCoinsPublicApi" class="me-1.5 size-4" />
                {{ __('Ping Coins API') }}
            </flux:button>
        </div>
        <p class="mt-1.5 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Turn payment gateways on or off for your account. Gateway credentials are centralized at platform level.') }}</p>
        @if($coinsPingMessage)
            <div class="mt-4 rounded-lg p-3 text-sm {{ $coinsPingMessage['type'] === 'success' ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300' : 'bg-red-50 text-red-800 dark:bg-red-900/20 dark:text-red-300' }}">
                {{ $coinsPingMessage['message'] }}
            </div>
        @endif
    </div>

    <div class="max-w-2xl space-y-5">
        @foreach($this->gateways as $gateway)
            @php
                $state = $gatewayStates[$gateway->id] ?? ['enabled' => false, 'global_enabled' => false];
                $isGloballyEnabled = (bool) ($state['global_enabled'] ?? false);
                $isEnabledForMerchant = $isGloballyEnabled && (bool) ($state['enabled'] ?? false);
            @endphp

            <div wire:key="gateway-card-{{ $gateway->id }}" class="rounded-xl border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex flex-wrap items-center gap-4 p-5 sm:p-6">
                    <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-lg font-semibold text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                        {{ strtoupper(mb_substr($gateway->name, 0, 1)) }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $gateway->name }}</h2>
                        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ strtoupper($gateway->code) }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        @if (! $isGloballyEnabled)
                            <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ __('Globally Disabled') }}</span>
                        @elseif ($isEnabledForMerchant)
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">{{ __('Enabled') }}</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">{{ __('Disabled') }}</span>
                        @endif

                        <flux:button
                            type="button"
                            size="sm"
                            variant="{{ $isEnabledForMerchant ? 'danger' : 'primary' }}"
                            wire:click="toggleFromButton({{ $gateway->id }})"
                            wire:loading.attr="disabled"
                            wire:target="toggleFromButton({{ $gateway->id }})"
                        >
                            {{ $isEnabledForMerchant ? __('Turn Off') : __('Turn On') }}
                        </flux:button>

                        <flux:field variant="inline" class="mb-0">
                            <flux:switch wire:model.live="gatewayStates.{{ $gateway->id }}.enabled" wire:change="toggleEnabled({{ $gateway->id }})" />
                            <flux:label class="ms-2 font-medium text-zinc-600 dark:text-zinc-400">{{ $isEnabledForMerchant ? __('On') : __('Off') }}</flux:label>
                        </flux:field>
                    </div>
                </div>

                @error('gateway.'.$gateway->id)
                    <div class="border-t border-zinc-200/80 px-5 py-3 text-sm text-red-700 dark:border-zinc-700 dark:text-red-300 sm:px-6">
                        {{ $message }}
                    </div>
                @enderror
            </div>
        @endforeach
    </div>
</div>
