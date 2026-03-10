@extends('layouts.admin')

@section('content')
<div class="flex h-full w-full flex-1 flex-col gap-6 px-4 sm:px-6 lg:px-8">
    {{-- Welcome Section --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-zinc-900 via-zinc-800 to-zinc-900 px-8 py-8 shadow-xl dark:from-zinc-950 dark:via-zinc-900 dark:to-zinc-950">
        {{-- Animated Background Pattern --}}
        <div class="absolute inset-0 opacity-10">
            <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/20 blur-3xl"></div>
            <div class="absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-emerald-500/20 blur-3xl"></div>
        </div>

        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-medium text-emerald-400 ring-1 ring-inset ring-emerald-500/20">
                        <span class="relative flex h-1.5 w-1.5">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        </span>
                        Live Dashboard
                    </span>
                </div>
                <h1 class="mt-3 text-3xl font-bold tracking-tight text-white">Welcome back, {{ auth()->user()->name }}!</h1>
                <p class="mt-1 text-base text-zinc-400">Here's what's happening with your collections today.</p>
            </div>
            <div class="flex items-center gap-3">
                <flux:button variant="outline" class="bg-white/10 text-white border-white/20 hover:bg-white/20" icon="arrow-path" size="sm">
                    Refresh Data
                </flux:button>
                <flux:button variant="primary" icon="document-arrow-down" size="sm">
                    Export Report
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Flash Messages with Animation --}}
    @if (session('status'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="relative animate-slide-down rounded-xl border-l-4 border-emerald-500 bg-gradient-to-r from-emerald-50/90 to-white px-5 py-4 shadow-lg dark:from-emerald-950/30 dark:to-zinc-800">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    <flux:icon name="check-circle" class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <p class="text-sm font-medium text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
                <button @click="show = false" class="ml-auto text-emerald-600 hover:text-emerald-800 dark:text-emerald-400">
                    <flux:icon name="x-mark" class="h-4 w-4" />
                </button>
            </div>
        </div>
    @endif

    {{-- Stats Grid --}}
    <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
        {{-- Total Collections Card --}}
        <div class="group relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 transition-all hover:shadow-lg hover:shadow-emerald-500/5 dark:border-zinc-700 dark:bg-zinc-800">
            <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-emerald-500/10 transition-transform group-hover:scale-150"></div>
            <div class="relative">
                <div class="flex items-center justify-between">
                    <div class="rounded-lg bg-emerald-100 p-2.5 dark:bg-emerald-900/30">
                        <flux:icon name="banknotes" class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">+12.5% from last month</span>
                </div>
                <p class="mt-4 text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Collections</p>
                <p class="text-2xl font-bold text-zinc-900 dark:text-white">PHP {{ number_format($totalCollections, 2) }}</p>
                <p class="mt-2 text-xs text-zinc-400">Across all clients</p>
            </div>
        </div>

        {{-- Active Clients Card --}}
        <div class="group relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 transition-all hover:shadow-lg hover:shadow-blue-500/5 dark:border-zinc-700 dark:bg-zinc-800">
            <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-blue-500/10 transition-transform group-hover:scale-150"></div>
            <div class="relative">
                <div class="flex items-center justify-between">
                    <div class="rounded-lg bg-blue-100 p-2.5 dark:bg-blue-900/30">
                        <flux:icon name="users" class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <span class="text-xs font-medium text-blue-600 dark:text-blue-400">{{ count($clientRows) }} total</span>
                </div>
                <p class="mt-4 text-sm font-medium text-zinc-500 dark:text-zinc-400">Active Clients</p>
                <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ count($clientRows) }}</p>
                <p class="mt-2 text-xs text-zinc-400">{{ count($clientRows) }} registered clients</p>
            </div>
        </div>

        {{-- Average Collection Card --}}
        <div class="group relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 transition-all hover:shadow-lg hover:shadow-purple-500/5 dark:border-zinc-700 dark:bg-zinc-800">
            <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-purple-500/10 transition-transform group-hover:scale-150"></div>
            <div class="relative">
                <div class="flex items-center justify-between">
                    <div class="rounded-lg bg-purple-100 p-2.5 dark:bg-purple-900/30">
                        <flux:icon name="chart-bar" class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                    </div>
                    <span class="text-xs font-medium text-purple-600 dark:text-purple-400">Per client avg.</span>
                </div>
                <p class="mt-4 text-sm font-medium text-zinc-500 dark:text-zinc-400">Average Collection</p>
                <p class="text-2xl font-bold text-zinc-900 dark:text-white">
                    PHP {{ count($clientRows) > 0 ? number_format($totalCollections / count($clientRows), 2) : '0.00' }}
                </p>
                <p class="mt-2 text-xs text-zinc-400">Average per client</p>
            </div>
        </div>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        {{-- Filter Section --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 lg:col-span-1">
            <div class="mb-5 flex items-center gap-2">
                <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-700">
                    <flux:icon name="adjustments-horizontal" class="h-5 w-5 text-zinc-600 dark:text-zinc-300" />
                </div>
                <h3 class="font-semibold text-zinc-900 dark:text-white">Filter Collections</h3>
            </div>

            <form method="GET" action="{{ route('admin.index') }}" class="space-y-4">
                <div>
                    <label for="client_id" class="mb-1.5 block text-sm font-medium text-zinc-600 dark:text-zinc-300">
                        Select Client
                    </label>
                    <select id="client_id" name="client_id"
                            class="w-full rounded-lg border border-zinc-200 bg-white px-4 py-2.5 text-sm text-zinc-900 transition focus:border-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-200 dark:border-zinc-600 dark:bg-zinc-900 dark:text-white dark:focus:ring-zinc-700">
                        <option value="">All Clients</option>
                        @foreach ($clientRows as $clientRow)
                            <option value="{{ $clientRow['id'] }}" @selected((string) $selectedClientId === (string) $clientRow['id'])>
                                {{ $clientRow['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit"
                            class="flex-1 rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100">
                        Apply Filter
                    </button>
                    <a href="{{ route('admin.index') }}"
                       class="flex-1 rounded-lg border border-zinc-200 bg-white px-4 py-2.5 text-center text-sm font-medium text-zinc-600 transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                        Clear
                    </a>
                </div>

                {{-- Current Filter Preview --}}
                <div class="mt-4 rounded-lg bg-zinc-50 p-4 dark:bg-zinc-900/50">
                    <p class="text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Current Selection</p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">
                        {{ $selectedClientName ?: 'All Clients' }}
                    </p>
                    <div class="mt-2 flex items-center justify-between border-t border-zinc-200 pt-2 dark:border-zinc-700">
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">Filtered Total:</span>
                        <span class="text-lg font-bold text-emerald-600 dark:text-emerald-400">
                            PHP {{ number_format($filteredCollections, 2) }}
                        </span>
                    </div>
                </div>
            </form>
        </div>

        {{-- Clients Table Section --}}
        <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800 lg:col-span-2">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <div class="flex items-center gap-2">
                    <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-700">
                        <flux:icon name="building-office-2" class="h-5 w-5 text-zinc-600 dark:text-zinc-300" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-zinc-900 dark:text-white">All Clients Overview</h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Collections per client</p>
                    </div>
                </div>
                <flux:button variant="outline" size="sm" :href="route('admin.gateways.index')" class="group">
                    <flux:icon name="cog" class="h-4 w-4 transition-transform group-hover:rotate-90" />
                    Configure Gateways
                </flux:button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-zinc-100 bg-zinc-50/50 dark:border-zinc-700 dark:bg-zinc-900/50">
                            <th class="px-6 py-3.5 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Client</th>
                            <th class="px-6 py-3.5 text-right text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Collections</th>
                            <th class="px-6 py-3.5 text-right text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Share</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                        @forelse ($clientRows as $clientRow)
                            <tr class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-700/25">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-zinc-100 to-zinc-200 text-sm font-bold text-zinc-600 dark:from-zinc-700 dark:to-zinc-800 dark:text-zinc-300">
                                            {{ strtoupper(mb_substr($clientRow['name'], 0, 2)) }}
                                        </div>
                                        <div>
                                            <p class="font-medium text-zinc-900 dark:text-white">{{ $clientRow['name'] }}</p>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Client ID: #{{ $clientRow['id'] }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="font-mono text-sm font-semibold text-zinc-900 dark:text-white">
                                        PHP {{ number_format($clientRow['total_collections'], 2) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @php
                                        $percentage = $totalCollections > 0 ? round(($clientRow['total_collections'] / $totalCollections) * 100, 1) : 0;
                                    @endphp
                                    <div class="flex items-center justify-end gap-2">
                                        <span class="text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ $percentage }}%</span>
                                        <div class="h-2 w-16 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                            <div class="h-full rounded-full bg-emerald-500" style="width: {{ $percentage }}%"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-16">
                                    <div class="flex flex-col items-center justify-center text-center">
                                        <div class="rounded-full bg-zinc-100 p-4 dark:bg-zinc-700">
                                            <flux:icon name="building-office-2" class="h-8 w-8 text-zinc-400" />
                                        </div>
                                        <p class="mt-4 font-semibold text-zinc-900 dark:text-white">No clients found</p>
                                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Client collection data will appear here.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Table Footer with Summary --}}
            @if(count($clientRows) > 0)
            <div class="border-t border-zinc-200 bg-zinc-50/50 px-6 py-4 dark:border-zinc-700 dark:bg-zinc-900/50">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-zinc-600 dark:text-zinc-400">
                        Showing {{ count($clientRows) }} {{ Str::plural('client', count($clientRows)) }}
                    </span>
                    <span class="text-sm font-semibold text-zinc-900 dark:text-white">
                        Total: PHP {{ number_format($totalCollections, 2) }}
                    </span>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Quick Actions Section --}}
    <div class="grid grid-cols-1 gap-5 md:grid-cols-4">
        <a href="#" class="group flex items-center gap-3 rounded-lg border border-zinc-200 bg-white p-4 transition-all hover:border-emerald-200 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-emerald-800">
            <div class="rounded-lg bg-emerald-100 p-2.5 group-hover:bg-emerald-200 dark:bg-emerald-900/30 dark:group-hover:bg-emerald-900/50">
                <flux:icon name="plus" class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div>
                <p class="text-sm font-medium text-zinc-900 dark:text-white">Add Client</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Register new client</p>
            </div>
        </a>

        <a href="{{ route('admin.gateways.index') }}" class="group flex items-center gap-3 rounded-lg border border-zinc-200 bg-white p-4 transition-all hover:border-blue-200 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-blue-800">
            <div class="rounded-lg bg-blue-100 p-2.5 group-hover:bg-blue-200 dark:bg-blue-900/30 dark:group-hover:bg-blue-900/50">
                <flux:icon name="credit-card" class="h-5 w-5 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
                <p class="text-sm font-medium text-zinc-900 dark:text-white">Gateways</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Configure payments</p>
            </div>
        </a>

        <a href="#" class="group flex items-center gap-3 rounded-lg border border-zinc-200 bg-white p-4 transition-all hover:border-purple-200 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-purple-800">
            <div class="rounded-lg bg-purple-100 p-2.5 group-hover:bg-purple-200 dark:bg-purple-900/30 dark:group-hover:bg-purple-900/50">
                <flux:icon name="document-text" class="h-5 w-5 text-purple-600 dark:text-purple-400" />
            </div>
            <div>
                <p class="text-sm font-medium text-zinc-900 dark:text-white">Reports</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">View analytics</p>
            </div>
        </a>

        <a href="#" class="group flex items-center gap-3 rounded-lg border border-zinc-200 bg-white p-4 transition-all hover:border-amber-200 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-amber-800">
            <div class="rounded-lg bg-amber-100 p-2.5 group-hover:bg-amber-200 dark:bg-amber-900/30 dark:group-hover:bg-amber-900/50">
                <flux:icon name="bell" class="h-5 w-5 text-amber-600 dark:text-amber-400" />
            </div>
            <div>
                <p class="text-sm font-medium text-zinc-900 dark:text-white">Notifications</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">3 unread</p>
            </div>
        </a>
    </div>
</div>

@push('styles')
<style>
    @keyframes slide-down {
        from {
            opacity: 0;
            transform: translateY(-1rem);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-slide-down {
        animation: slide-down 0.3s ease-out;
    }
</style>
@endpush
@endsection
