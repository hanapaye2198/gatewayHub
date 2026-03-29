@extends('layouts.admin')

@section('content')
<div class="flex h-full w-full flex-1 flex-col gap-6 px-4 sm:px-6 lg:px-8">
    {{-- Modern Header with Stats --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-zinc-900 via-zinc-800 to-indigo-900 px-8 py-8 shadow-xl">
        {{-- Animated Background --}}
        <div class="absolute inset-0 opacity-10">
            <div class="absolute -right-20 -top-20 h-60 w-60 rounded-full bg-white blur-3xl"></div>
            <div class="absolute -bottom-20 -left-20 h-60 w-60 rounded-full bg-indigo-500 blur-3xl"></div>
        </div>

        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-5">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 backdrop-blur-sm">
                    <flux:icon name="credit-card" class="h-8 w-8 text-white" />
                </div>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-3xl font-bold text-white">Gateway Hub</h1>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-medium text-emerald-300 ring-1 ring-inset ring-emerald-500/30">
                            <span class="relative flex h-1.5 w-1.5">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            </span>
                            Live
                        </span>
                    </div>
                    <p class="mt-1 text-base text-zinc-300">Centralized payment gateway management with real-time controls</p>
                </div>
            </div>

            {{-- Quick Stats --}}
            <div class="flex flex-wrap gap-3">
                <div class="rounded-xl bg-white/5 px-5 py-3 backdrop-blur-sm">
                    <p class="text-xs font-medium text-zinc-400">Global Gateways</p>
                    <p class="text-xl font-bold text-white">{{ $gateways->where('is_global_enabled', true)->count() }}/{{ $gateways->count() }}</p>
                </div>
                <div class="rounded-xl bg-white/5 px-5 py-3 backdrop-blur-sm">
                    <p class="text-xs font-medium text-zinc-400">Active Merchants</p>
                    <p class="text-xl font-bold text-white">{{ $merchants->where('is_active', true)->count() }}</p>
                </div>
                <div class="rounded-xl bg-white/5 px-5 py-3 backdrop-blur-sm">
                    <p class="text-xs font-medium text-zinc-400">Total Connections</p>
                    <p class="text-xl font-bold text-white">{{ $gateways->sum(fn($g) => $g->merchantGateways->count()) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Success Message Toast --}}
    <div x-data="{ show: @entangle('showSuccessMessage') }"
         x-show="show"
         x-init="$watch('show', value => { if (value) setTimeout(() => show = false, 3000) })"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-4 right-4 z-50 flex items-center gap-3 rounded-xl bg-emerald-50 px-5 py-3 shadow-lg dark:bg-emerald-950"
         style="display: none;">
        <div class="rounded-full bg-emerald-500 p-1">
            <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>
        <p class="text-sm font-medium text-emerald-800 dark:text-emerald-200" x-text="$wire.successMessage"></p>
    </div>

    {{-- Confirmation Modal --}}
    <div x-data="{ show: @entangle('showConfirmation') }"
         x-show="show"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-zinc-800">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">Confirm Action</h3>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400" x-text="$wire.confirmationData.message"></p>
            <div class="mt-5 flex justify-end gap-2">
                <button @click="show = false"
                        class="rounded-lg border border-zinc-200 px-4 py-2 text-sm font-medium text-zinc-600 transition hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-700">
                    Cancel
                </button>
                <button wire:click="executeConfirmedAction"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                    Confirm
                </button>
            </div>
        </div>
    </div>

    {{-- Gateway Config Modal --}}
    <div x-data="{ show: @entangle('showConfigModal') }"
         x-show="show"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="relative w-full max-w-2xl rounded-2xl bg-white shadow-xl dark:bg-zinc-800">
            <div class="border-b border-zinc-100 px-6 py-4 dark:border-zinc-700">
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">
                    Configure Gateway: <span class="text-indigo-600 dark:text-indigo-400" x-text="$wire.editingGateway?.name"></span>
                </h3>
            </div>
            <form wire:submit.prevent="updateGatewayConfig($wire.editingGateway?.id)">
                <div class="max-h-[60vh] overflow-y-auto px-6 py-4">
                    <div class="space-y-4">
                        @foreach ($credentialFields['coins'] ?? [] as $field)
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ $field['label'] }}
                                    @if($field['required'] ?? false)
                                        <span class="text-red-500">*</span>
                                    @endif
                                </label>
                                @if(($field['type'] ?? 'text') === 'select')
                                    <select wire:model="config.{{ $field['key'] }}"
                                            class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900">
                                        @foreach(($field['options'] ?? []) as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="{{ $field['type'] ?? 'text' }}"
                                           wire:model="config.{{ $field['key'] }}"
                                           placeholder="{{ $field['masked'] ?? false ? '••••••••' : '' }}"
                                           class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900">
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-zinc-100 px-6 py-4 dark:border-zinc-700">
                    <button type="button" @click="show = false"
                            class="rounded-lg border border-zinc-200 px-4 py-2 text-sm font-medium text-zinc-600 transition hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-700">
                        Cancel
                    </button>
                    <button type="submit"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                        Save Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Main Dashboard Grid --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Global Gateways Section --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Search and Filters Bar --}}
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative flex-1">
                        <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <input type="text"
                               wire:model.live.debounce.300ms="search"
                               placeholder="Search gateways and merchants..."
                               class="w-full rounded-lg border border-zinc-200 bg-white py-2 pl-10 pr-4 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900">
                    </div>
                    <div class="flex gap-2">
                        <select wire:model.live="statusFilter"
                                class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <option value="all">All Status</option>
                            <option value="enabled">Enabled</option>
                            <option value="disabled">Disabled</option>
                        </select>
                        <select wire:model.live="gatewayTypeFilter"
                                class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <option value="all">All Types</option>
                            <option value="coins">Coins.ph</option>
                            <option value="gcash">GCash</option>
                            <option value="paymaya">PayMaya</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Gateways Table --}}
            <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="border-b border-zinc-100 px-6 py-4 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-zinc-900 dark:text-white">Global Gateway Control</h2>
                        <div class="flex items-center gap-2">
                            <input type="checkbox"
                                   wire:model.live="selectAll"
                                   class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-zinc-500 dark:text-zinc-400">Select All</span>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                            <tr>
                                <th class="w-10 px-6 py-3"></th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">Gateway</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">Merchants</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-zinc-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                            @foreach($gateways as $gateway)
                                <tr class="group transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="px-6 py-4">
                                        <input type="checkbox"
                                               wire:model.live="selectedGateways"
                                               value="{{ $gateway->id }}"
                                               class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500">
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 text-sm font-bold text-white shadow-lg">
                                                {{ strtoupper(substr($gateway->name, 0, 2)) }}
                                            </div>
                                            <span class="font-medium text-zinc-900 dark:text-white">{{ $gateway->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <code class="rounded bg-zinc-100 px-2 py-1 text-xs font-mono text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                            {{ $gateway->code }}
                                        </code>
                                    </td>
                                    <td class="px-6 py-4">
                                        <button wire:click="toggleGatewayGlobal({{ $gateway->id }})"
                                                class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium transition-all"
                                                :class="$wire.gateways.find(g => g.id === {{ $gateway->id }})?.is_global_enabled
                                                    ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                    : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400'">
                                            <span class="relative flex h-2 w-2">
                                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75"
                                                      :class="$wire.gateways.find(g => g.id === {{ $gateway->id }})?.is_global_enabled ? 'bg-emerald-400' : 'bg-zinc-400'"></span>
                                                <span class="relative inline-flex h-2 w-2 rounded-full"
                                                      :class="$wire.gateways.find(g => g.id === {{ $gateway->id }})?.is_global_enabled ? 'bg-emerald-500' : 'bg-zinc-500'"></span>
                                            </span>
                                            <span x-text="$wire.gateways.find(g => g.id === {{ $gateway->id }})?.is_global_enabled ? 'Enabled' : 'Disabled'"></span>
                                        </button>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-1">
                                            <span class="text-sm font-medium text-zinc-900 dark:text-white">
                                                {{ $gateway->merchantGateways->where('is_enabled', true)->count() }}
                                            </span>
                                            <span class="text-xs text-zinc-400">/ {{ $merchants->count() }}</span>
                                        </div>
                                        <div class="mt-1 h-1.5 w-24 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                            @php
                                                $percentage = $merchants->count() > 0
                                                    ? ($gateway->merchantGateways->where('is_enabled', true)->count() / $merchants->count()) * 100
                                                    : 0;
                                            @endphp
                                            <div class="h-full rounded-full bg-indigo-500" style="width: {{ $percentage }}%"></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button wire:click="editConfig({{ $gateway->id }})"
                                                    class="rounded-lg p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-700">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Bulk Actions --}}
                @if(!empty($selectedGateways))
                    <div class="border-t border-zinc-100 bg-zinc-50 px-6 py-4 dark:border-zinc-700 dark:bg-zinc-900/50">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ count($selectedGateways) }} gateway(s) selected
                            </span>
                            <select wire:model="bulkAction"
                                    class="rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <option value="">Bulk Actions</option>
                                <option value="enable_global">Enable Globally</option>
                                <option value="disable_global">Disable Globally</option>
                                <option value="enable_for_merchants">Enable for Merchants</option>
                                <option value="disable_for_merchants">Disable for Merchants</option>
                            </select>

                            @if(in_array($bulkAction, ['enable_for_merchants', 'disable_for_merchants']))
                                <select wire:model="selectedMerchants" multiple
                                        class="min-w-[200px] rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                    @foreach($merchants as $merchant)
                                        <option value="{{ $merchant->id }}">{{ $merchant->name }}</option>
                                    @endforeach
                                </select>
                            @endif

                            <button wire:click="bulkAction"
                                    class="rounded-lg bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                                Apply
                            </button>
                            <button wire:click="resetBulkSelection"
                                    class="rounded-lg border border-zinc-200 bg-white px-4 py-1.5 text-sm font-medium text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                                Clear
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Per-Merchant Access Table --}}
            <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="border-b border-zinc-100 px-6 py-4 dark:border-zinc-700">
                    <h2 class="font-semibold text-zinc-900 dark:text-white">Merchant Gateway Access</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">Merchant</th>
                                @foreach($gateways->where('is_global_enabled', true) as $gateway)
                                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-zinc-500">
                                        <div class="flex flex-col items-center">
                                            <span>{{ $gateway->name }}</span>
                                            @if(!$gateway->is_global_enabled)
                                                <span class="mt-1 text-[10px] text-red-500">(global off)</span>
                                            @endif
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                            @foreach($merchants->take(5) as $merchant)
                                <tr class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-100 to-purple-100 text-xs font-bold text-indigo-700 dark:from-indigo-900 dark:to-purple-900 dark:text-indigo-300">
                                                {{ strtoupper(substr($merchant->name, 0, 2)) }}
                                            </div>
                                            <div>
                                                <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $merchant->name }}</span>
                                                @if($merchant->is_active)
                                                    <span class="ml-2 inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    @foreach($gateways->where('is_global_enabled', true) as $gateway)
                                        @php
                                            $isEnabled = $gateway->merchantGateways
                                                ->where('merchant_id', $merchant->id)
                                                ->first()?->is_enabled ?? false;
                                        @endphp
                                        <td class="px-4 py-4 text-center">
                                            <button wire:click="toggleMerchantGateway({{ $gateway->id }}, {{ $merchant->id }})"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg transition-all hover:scale-110"
                                                    :class="$isEnabled
                                                        ? 'bg-emerald-100 text-emerald-600 hover:bg-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                        : 'bg-zinc-100 text-zinc-400 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-500'">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M{{ $isEnabled ? '9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' : '5 13l4 4L19 7' }}"></path>
                                                </svg>
                                            </button>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if($merchants->count() > 5)
                        <div class="border-t border-zinc-100 px-6 py-3 text-center dark:border-zinc-700">
                            <a href="#" class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                                View all {{ $merchants->count() }} merchants →
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right Sidebar - Activity & Quick Actions --}}
        <div class="space-y-6">
            {{-- Quick Actions Card --}}
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <h3 class="mb-3 font-semibold text-zinc-900 dark:text-white">Quick Actions</h3>
                <div class="space-y-2">
                    <button class="flex w-full items-center gap-3 rounded-lg border border-zinc-100 p-3 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/50">
                        <div class="rounded-lg bg-indigo-100 p-2 dark:bg-indigo-900/30">
                            <svg class="h-4 w-4 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Add New Gateway</span>
                    </button>
                    <button class="flex w-full items-center gap-3 rounded-lg border border-zinc-100 p-3 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/50">
                        <div class="rounded-lg bg-emerald-100 p-2 dark:bg-emerald-900/30">
                            <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Enable All Gateways</span>
                    </button>
                    <button class="flex w-full items-center gap-3 rounded-lg border border-zinc-100 p-3 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/50">
                        <div class="rounded-lg bg-amber-100 p-2 dark:bg-amber-900/30">
                            <svg class="h-4 w-4 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Schedule Maintenance</span>
                    </button>
                </div>
            </div>

            {{-- Activity Log Card --}}
            <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="border-b border-zinc-100 px-5 py-4 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-zinc-900 dark:text-white">Recent Activity</h3>
                        <button wire:click="clearActivityLog" class="text-xs text-red-600 hover:text-red-700 dark:text-red-400">
                            Clear
                        </button>
                    </div>
                </div>
                <div class="max-h-80 overflow-y-auto">
                    @forelse($activityLog as $log)
                        <div class="border-b border-zinc-100 px-5 py-3 last:border-0 dark:border-zinc-700">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0">
                                    @if(str_contains($log['action'], 'enable'))
                                        <div class="rounded-full bg-emerald-100 p-1.5 dark:bg-emerald-900/30">
                                            <svg class="h-3 w-3 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                    @elseif(str_contains($log['action'], 'disable'))
                                        <div class="rounded-full bg-red-100 p-1.5 dark:bg-red-900/30">
                                            <svg class="h-3 w-3 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </div>
                                    @else
                                        <div class="rounded-full bg-blue-100 p-1.5 dark:bg-blue-900/30">
                                            <svg class="h-3 w-3 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <p class="text-xs font-medium text-zinc-900 dark:text-white">
                                        {{ $log['action'] }}: {{ $log['subject'] }}
                                    </p>
                                    <p class="mt-0.5 text-[10px] text-zinc-500 dark:text-zinc-400">
                                        {{ $log['user'] }} • {{ $log['timestamp']->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center">
                            <svg class="mx-auto h-8 w-8 text-zinc-300 dark:text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="mt-2 text-xs text-zinc-400 dark:text-zinc-500">No recent activity</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Gateway Health Card --}}
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <h3 class="mb-3 font-semibold text-zinc-900 dark:text-white">Gateway Health</h3>
                <div class="space-y-3">
                    @foreach($gateways as $gateway)
                        <div>
                            <div class="mb-1 flex items-center justify-between">
                                <span class="text-xs text-zinc-600 dark:text-zinc-400">{{ $gateway->name }}</span>
                                <span class="text-xs font-medium text-zinc-900 dark:text-white">
                                    {{ $gateway->merchantGateways->where('is_enabled', true)->count() }}/{{ $merchants->count() }}
                                </span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                @php
                                    $percentage = $merchants->count() > 0
                                        ? ($gateway->merchantGateways->where('is_enabled', true)->count() / $merchants->count()) * 100
                                        : 0;
                                @endphp
                                <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-purple-500" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush
@endsection
