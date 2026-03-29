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
                    'merchant_id' => $user->merchant_id,
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
            ->where('merchant_id', $user->merchant_id)
            ->where('gateway_id', $gatewayId)
            ->first();

        MerchantGateway::query()->updateOrCreate(
            [
                'merchant_id' => $user->merchant_id,
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

<div class="gh-page flex h-full w-full flex-1 flex-col gap-6 font-sans text-zinc-900 dark:text-zinc-100">

    {{-- ── Page Header ── --}}
    <div class="rounded-xl border border-zinc-200/80 bg-white p-6 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-800/80 dark:shadow-zinc-950/20">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="mb-1 flex items-center gap-2.5">
                    {{-- Icon --}}
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-950/50">
                        <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5M5.25 3h13.5A2.25 2.25 0 0121 5.25v13.5A2.25 2.25 0 0118.75 21H5.25A2.25 2.25 0 013 18.75V5.25A2.25 2.25 0 015.25 3z"/>
                        </svg>
                    </div>
                    <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">{{ __('Payment Gateways') }}</h1>
                </div>
                <p class="max-w-lg text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Enable or disable payment gateways for your merchant account. Credentials are managed at the platform level.') }}
                </p>
            </div>

            {{-- Summary badges + Ping button --}}
            <div class="flex flex-wrap items-center gap-3">
                @php
                    $enabledCount  = collect($gatewayStates)->filter(fn($s) => ($s['global_enabled'] ?? false) && ($s['enabled'] ?? false))->count();
                    $totalCount    = count($gatewayStates);
                @endphp
                <div class="hidden items-center gap-2 rounded-lg border border-zinc-200/80 bg-zinc-50 px-3 py-1.5 sm:flex dark:border-zinc-600 dark:bg-zinc-800/80">
                    <span class="text-xs text-zinc-600 dark:text-zinc-400">{{ __('Active') }}</span>
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ $enabledCount }}<span class="font-normal text-zinc-500 dark:text-zinc-500">/{{ $totalCount }}</span></span>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-800 shadow-sm transition hover:bg-zinc-50 hover:border-zinc-400 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:shadow-none dark:hover:border-zinc-500 dark:hover:bg-zinc-700"
                    wire:click="pingCoinsPublicApi"
                    wire:loading.attr="disabled"
                    wire:target="pingCoinsPublicApi"
                >
                    <span wire:loading wire:target="pingCoinsPublicApi">
                        <svg class="h-4 w-4 animate-spin text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                    </span>
                    <span wire:loading.remove wire:target="pingCoinsPublicApi">
                        <svg class="h-4 w-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z"/>
                        </svg>
                    </span>
                    {{ __('Ping Coins API') }}
                </button>
            </div>
        </div>

        {{-- Ping result banner --}}
        @if($coinsPingMessage)
            <div
                @class([
                    'mt-4 flex items-center gap-2.5 rounded-xl border px-4 py-3 text-sm font-medium transition-colors duration-200',
                    'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/25 dark:bg-emerald-950/40 dark:text-emerald-200' => $coinsPingMessage['type'] === 'success',
                    'border-red-200 bg-red-50 text-red-900 dark:border-red-500/25 dark:bg-red-950/40 dark:text-red-200' => $coinsPingMessage['type'] !== 'success',
                ])
            >
                @if($coinsPingMessage['type'] === 'success')
                    <svg class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                @else
                    <svg class="h-4 w-4 shrink-0 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                @endif
                <span>{{ $coinsPingMessage['message'] }}</span>
            </div>
        @endif
    </div>

    {{-- ── Gateway Cards ── --}}
    <div class="w-full max-w-3xl space-y-3">
        @foreach($this->gateways as $gateway)
            @php
                $state             = $gatewayStates[$gateway->id] ?? ['enabled' => false, 'global_enabled' => false];
                $isGloballyEnabled = (bool) ($state['global_enabled'] ?? false);
                $isEnabled         = $isGloballyEnabled && (bool) ($state['enabled'] ?? false);

                $statusBorder = $isEnabled
                    ? 'border-l-emerald-500 dark:border-l-emerald-400'
                    : ($isGloballyEnabled ? 'border-l-amber-500 dark:border-l-amber-400' : 'border-l-slate-300 dark:border-l-slate-600');
            @endphp

            <div
                wire:key="gateway-card-{{ $gateway->id }}"
                class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900/90 dark:shadow-zinc-950/30 dark:hover:shadow-lg/30 {{ $statusBorder }} border-l-4"
            >

                {{-- Main row --}}
                <div class="flex flex-wrap items-center gap-4 px-6 py-4 ps-7">

                    {{-- Avatar --}}
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-sm font-bold tracking-tight text-white shadow-lg shadow-indigo-500/30 dark:from-indigo-600 dark:to-violet-700 dark:shadow-indigo-900/40">
                        {{ strtoupper(mb_substr($gateway->name, 0, 2)) }}
                    </div>

                    {{-- Name + code --}}
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-bold leading-tight text-zinc-900 dark:text-zinc-50">{{ $gateway->name }}</h2>
                            <span class="rounded-md bg-zinc-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-widest text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                {{ $gateway->code }}
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $isEnabled ? __('Accepting payments') : ($isGloballyEnabled ? __('Currently inactive for your account') : __('Unavailable — contact SurePay admin')) }}
                        </p>
                    </div>

                    {{-- Controls --}}
                    <div class="flex flex-wrap items-center gap-3">

                        {{-- Status badge --}}
                        @if (! $isGloballyEnabled)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-0.5 text-[0.7rem] font-semibold uppercase tracking-wide text-slate-700 dark:bg-slate-800/80 dark:text-slate-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-slate-400 dark:bg-slate-500"></span>{{ __('Globally Off') }}
                            </span>
                        @elseif ($isEnabled)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[0.7rem] font-semibold uppercase tracking-wide text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500 shadow-[0_0_0_2px_rgba(16,185,129,0.25)] dark:bg-emerald-400"></span>{{ __('Active') }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-0.5 text-[0.7rem] font-semibold uppercase tracking-wide text-amber-900 dark:bg-amber-500/15 dark:text-amber-200">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500 dark:bg-amber-400"></span>{{ __('Inactive') }}
                            </span>
                        @endif

                        {{-- Toggle button --}}
                        <button
                            type="button"
                            @class([
                                'inline-flex items-center gap-1 rounded-lg border px-4 py-1.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50',
                                'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/15' => ! $isEnabled,
                                'border-orange-200 bg-orange-50 text-orange-900 hover:bg-orange-100 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200 dark:hover:bg-red-500/15' => $isEnabled,
                            ])
                            wire:click="toggleFromButton({{ $gateway->id }})"
                            wire:loading.attr="disabled"
                            wire:target="toggleFromButton({{ $gateway->id }})"
                        >
                            <span wire:loading wire:target="toggleFromButton({{ $gateway->id }})">
                                <svg class="-mt-0.5 me-1 inline-block h-3.5 w-3.5 animate-spin text-current" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                </svg>
                            </span>
                            <span wire:loading.remove wire:target="toggleFromButton({{ $gateway->id }})">
                                @if($isEnabled)
                                    <svg class="-mt-0.5 me-1 inline-block h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                @else
                                    <svg class="-mt-0.5 me-1 inline-block h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                @endif
                            </span>
                            {{ $isEnabled ? __('Disable') : __('Enable') }}
                        </button>

                        {{-- Flux switch --}}
                        <flux:field variant="inline" class="mb-0">
                            <flux:switch
                                wire:model.live="gatewayStates.{{ $gateway->id }}.enabled"
                                wire:change="toggleEnabled({{ $gateway->id }})"
                            />
                        </flux:field>
                    </div>
                </div>

                {{-- Error message --}}
                @error('gateway.'.$gateway->id)
                    <div class="flex items-center gap-2 border-t border-red-200 bg-red-50 px-4 py-2.5 ps-6 text-sm text-red-800 dark:border-red-500/20 dark:bg-red-950/35 dark:text-red-200">
                        <svg class="h-4 w-4 shrink-0 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                        </svg>
                        <span>{{ $message }}</span>
                    </div>
                @enderror
            </div>
        @endforeach
    </div>

    {{-- ── Empty state ── --}}
    @if($this->gateways->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 bg-zinc-50/50 py-16 text-center dark:border-zinc-600 dark:bg-zinc-900/40">
            <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-800">
                <svg class="h-7 w-7 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5M5.25 3h13.5A2.25 2.25 0 0121 5.25v13.5A2.25 2.25 0 0118.75 21H5.25A2.25 2.25 0 013 18.75V5.25A2.25 2.25 0 015.25 3z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ __('No gateways available') }}</p>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-500">{{ __('Contact your SurePay administrator to set up payment gateways.') }}</p>
        </div>
    @endif

</div>
