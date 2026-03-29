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

<div class="gh-page flex h-full w-full min-w-0 flex-1 flex-col gap-5 font-sans text-zinc-900 sm:gap-6 dark:text-zinc-100">

    {{-- ── Page Header ── --}}
    <div class="rounded-xl border border-zinc-200/90 bg-white p-4 shadow-sm ring-1 ring-black/[0.03] dark:border-zinc-600 dark:bg-zinc-900 dark:shadow-none dark:ring-white/[0.06] sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between sm:gap-4">
            <div class="min-w-0 flex-1">
                <div class="mb-1 flex items-center gap-2.5">
                    {{-- Icon --}}
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-950/60 dark:ring-1 dark:ring-indigo-500/20">
                        <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-300" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5M5.25 3h13.5A2.25 2.25 0 0121 5.25v13.5A2.25 2.25 0 0118.75 21H5.25A2.25 2.25 0 013 18.75V5.25A2.25 2.25 0 015.25 3z"/>
                        </svg>
                    </div>
                    <h1 class="text-lg font-bold tracking-tight text-zinc-900 sm:text-xl dark:text-zinc-50">{{ __('Payment Gateways') }}</h1>
                </div>
                <p class="mt-1 max-w-lg text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">
                    {{ __('Enable or disable payment gateways for your merchant account. Credentials are managed at the platform level.') }}
                </p>
            </div>

            {{-- Summary badges + Ping button --}}
            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
                @php
                    $enabledCount  = collect($gatewayStates)->filter(fn($s) => ($s['global_enabled'] ?? false) && ($s['enabled'] ?? false))->count();
                    $totalCount    = count($gatewayStates);
                @endphp
                <div class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 sm:w-auto sm:justify-start dark:border-zinc-600 dark:bg-zinc-800/90">
                    <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ __('Active') }}</span>
                    <span class="text-sm font-bold tabular-nums text-zinc-900 dark:text-zinc-50">{{ $enabledCount }}<span class="font-normal text-zinc-500 dark:text-zinc-400">/{{ $totalCount }}</span></span>
                </div>

                <button
                    type="button"
                    class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-800 shadow-sm transition hover:bg-zinc-50 hover:border-zinc-400 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto dark:border-zinc-500 dark:bg-zinc-800 dark:text-zinc-100 dark:shadow-none dark:hover:border-zinc-400 dark:hover:bg-zinc-700"
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
                    'mt-4 flex items-start gap-2.5 rounded-xl border px-3 py-3 text-sm font-medium transition-colors duration-200 sm:items-center sm:px-4',
                    'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-400/35 dark:bg-emerald-950/50 dark:text-emerald-100' => $coinsPingMessage['type'] === 'success',
                    'border-red-200 bg-red-50 text-red-900 dark:border-red-400/35 dark:bg-red-950/45 dark:text-red-100' => $coinsPingMessage['type'] !== 'success',
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
    <div class="w-full max-w-3xl space-y-3 sm:space-y-4">
        @foreach($this->gateways as $gateway)
            @php
                $state             = $gatewayStates[$gateway->id] ?? ['enabled' => false, 'global_enabled' => false];
                $isGloballyEnabled = (bool) ($state['global_enabled'] ?? false);
                $isEnabled         = $isGloballyEnabled && (bool) ($state['enabled'] ?? false);

                $statusBorder = $isEnabled
                    ? 'border-l-emerald-500 dark:border-l-emerald-400'
                    : ($isGloballyEnabled ? 'border-l-amber-500 dark:border-l-amber-400' : 'border-l-slate-300 dark:border-l-slate-600');

                $gatewayLogoFilenames = [
                    'coins' => 'coins.png',
                    'gcash' => 'gcash.png',
                    'maya' => 'maya.png',
                    'paypal' => 'paypal.png',
                    'qrph' => 'qrph.svg',
                ];
                $gatewayLogoFile = $gatewayLogoFilenames[strtolower((string) $gateway->code)] ?? null;
            @endphp

            <div
                wire:key="gateway-card-{{ $gateway->id }}"
                class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm ring-1 ring-black/[0.02] transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-zinc-600 dark:bg-zinc-900 dark:ring-white/[0.06] dark:shadow-zinc-950/40 dark:hover:shadow-lg/20 {{ $statusBorder }} border-l-4"
            >

                <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:gap-4 sm:px-5 sm:py-4 sm:ps-6 sm:pe-5">
                    {{-- Logo + title (top on mobile) --}}
                    <div class="flex min-w-0 flex-1 items-start gap-3 sm:items-center sm:gap-4">
                        {{-- Brand logo or letter fallback --}}
                        @if ($gatewayLogoFile !== null)
                            <div class="flex h-12 w-14 shrink-0 items-center justify-center rounded-xl bg-white p-1.5 shadow-sm ring-1 ring-zinc-200/90 dark:bg-zinc-950 dark:ring-zinc-500/40">
                                <img
                                    src="{{ asset('images/logos/'.$gatewayLogoFile) }}"
                                    alt="{{ $gateway->name }}"
                                    class="max-h-9 w-full object-contain object-center"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </div>
                        @else
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-sm font-bold tracking-tight text-white shadow-md shadow-indigo-500/25 dark:from-indigo-500 dark:to-violet-600 dark:shadow-indigo-950/50">
                                {{ strtoupper(mb_substr($gateway->name, 0, 2)) }}
                            </div>
                        @endif

                        {{-- Name + code --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <h2 class="text-base font-bold leading-snug text-zinc-900 dark:text-zinc-50">{{ $gateway->name }}</h2>
                                <span class="inline-flex items-center rounded-md bg-zinc-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-widest text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:ring-1 dark:ring-zinc-600/80">
                                    {{ $gateway->code }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs leading-relaxed text-zinc-600 dark:text-zinc-300">
                                {{ $isEnabled ? __('Accepting payments') : ($isGloballyEnabled ? __('Currently inactive for your account') : __('Unavailable — contact SurePay admin')) }}
                            </p>
                        </div>
                    </div>

                    {{-- Controls: full-width row on mobile, inline on sm+ --}}
                    <div class="flex w-full flex-wrap items-center gap-2 border-t border-zinc-100 pt-3 dark:border-zinc-700/90 sm:w-auto sm:gap-3 sm:justify-end sm:border-0 sm:pt-0">

                        {{-- Status badge --}}
                        @if (! $isGloballyEnabled)
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200/80 bg-slate-100 px-2.5 py-1 text-[0.7rem] font-semibold uppercase tracking-wide text-slate-800 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                                <span class="h-1.5 w-1.5 rounded-full bg-slate-500 dark:bg-slate-400"></span>{{ __('Globally Off') }}
                            </span>
                        @elseif ($isEnabled)
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200/90 bg-emerald-100 px-2.5 py-1 text-[0.7rem] font-semibold uppercase tracking-wide text-emerald-900 dark:border-emerald-500/40 dark:bg-emerald-950/60 dark:text-emerald-200">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500 shadow-[0_0_0_2px_rgba(16,185,129,0.3)] dark:bg-emerald-400"></span>{{ __('Active') }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200/90 bg-amber-100 px-2.5 py-1 text-[0.7rem] font-semibold uppercase tracking-wide text-amber-950 dark:border-amber-500/35 dark:bg-amber-950/50 dark:text-amber-100">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500 dark:bg-amber-400"></span>{{ __('Inactive') }}
                            </span>
                        @endif

                        {{-- Toggle button --}}
                        <button
                            type="button"
                            @class([
                                'inline-flex min-h-[2.5rem] items-center gap-1 rounded-lg border px-4 py-2 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50 sm:min-h-0 sm:py-1.5',
                                'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-500/40 dark:bg-emerald-950/50 dark:text-emerald-200 dark:hover:bg-emerald-900/60' => ! $isEnabled,
                                'border-orange-200 bg-orange-50 text-orange-900 hover:bg-orange-100 dark:border-red-500/40 dark:bg-red-950/45 dark:text-red-100 dark:hover:bg-red-950/70' => $isEnabled,
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
                        <flux:field variant="inline" class="mb-0 ms-auto sm:ms-0">
                            <flux:switch
                                wire:model.live="gatewayStates.{{ $gateway->id }}.enabled"
                                wire:change="toggleEnabled({{ $gateway->id }})"
                            />
                        </flux:field>
                    </div>
                </div>

                {{-- Error message --}}
                @error('gateway.'.$gateway->id)
                    <div class="flex items-start gap-2 border-t border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-500/30 dark:bg-red-950/50 dark:text-red-100 sm:items-center sm:px-5 sm:ps-6">
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
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 bg-zinc-50/80 px-4 py-14 text-center dark:border-zinc-500 dark:bg-zinc-900/60 dark:ring-1 dark:ring-white/[0.05] sm:py-16">
            <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-800 dark:ring-1 dark:ring-zinc-600">
                <svg class="h-7 w-7 text-zinc-500 dark:text-zinc-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5M5.25 3h13.5A2.25 2.25 0 0121 5.25v13.5A2.25 2.25 0 0118.75 21H5.25A2.25 2.25 0 013 18.75V5.25A2.25 2.25 0 015.25 3z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ __('No gateways available') }}</p>
            <p class="mt-1 max-w-sm text-xs leading-relaxed text-zinc-600 dark:text-zinc-300">{{ __('Contact your SurePay administrator to set up payment gateways.') }}</p>
        </div>
    @endif

</div>
