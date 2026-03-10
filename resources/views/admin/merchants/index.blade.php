@extends('layouts.admin')

@section('content')
<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Header Section with Gradient (matches Gateway Hub: dark charcoal → deep purple-blue, left to right) --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-zinc-900 via-zinc-800 to-indigo-900 px-8 py-8 shadow-xl">
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-5">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 backdrop-blur-sm">
                    <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016 2.993 2.993 0 0 0 2.25-1.016 3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-3xl font-bold text-white">Merchants</h1>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-medium text-emerald-300 ring-1 ring-inset ring-emerald-500/30">
                            <span class="relative flex h-1.5 w-1.5">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            </span>
                            {{ $merchants->total() }} total
                        </span>
                    </div>
                    <p class="mt-1 text-base text-zinc-300">Manage merchant accounts, activate or deactivate access to the platform.</p>
                </div>
            </div>

            {{-- Quick Stats --}}
            <div class="flex flex-wrap gap-3">
                <div class="rounded-xl bg-white/5 px-5 py-3 backdrop-blur-sm">
                    <p class="text-xs font-medium text-zinc-400">Active</p>
                    <p class="text-xl font-bold text-white">{{ $merchants->where('is_active', true)->count() }}</p>
                </div>
                <div class="rounded-xl bg-white/5 px-5 py-3 backdrop-blur-sm">
                    <p class="text-xs font-medium text-zinc-400">Inactive</p>
                    <p class="text-xl font-bold text-white">{{ $merchants->where('is_active', false)->count() }}</p>
                </div>
            </div>
        </div>

            @if (session('status'))
                <div class="relative mt-5 animate-slide-down">
                    <div class="rounded-lg border-l-4 border-emerald-500 bg-emerald-500/20 px-4 py-3 backdrop-blur-sm">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-sm font-medium text-emerald-200">{{ session('status') }}</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Search and Filter Bar --}}
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            {{-- Search Input --}}
            <div class="relative flex-1">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                    <svg class="h-4 w-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                </div>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search merchants by name or email..."
                    class="w-full rounded-xl border border-zinc-200 bg-white py-3 pl-11 pr-4 text-sm text-zinc-900 placeholder-zinc-400 transition-all focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                >
                @if(strlen($search) > 0)
                    <button
                        wire:click="$set('search', '')"
                        class="absolute inset-y-0 right-0 flex items-center pr-4 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>

            {{-- Filter Buttons --}}
            <div class="flex items-center gap-2">
                {{-- Status Filter --}}
                <div class="relative" x-data="{ open: false }">
                    <button
                        @click="open = !open"
                        class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm font-medium text-zinc-700 transition-all hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                        </svg>
                        Status:
                        <span class="font-semibold">
                            @if($statusFilter === 'all') All
                            @elseif($statusFilter === 'active') Active
                            @else Inactive
                            @endif
                        </span>
                        <svg class="h-4 w-4" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <div
                        x-show="open"
                        @click.away="open = false"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="transform opacity-0 scale-95"
                        x-transition:enter-end="transform opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="transform opacity-100 scale-100"
                        x-transition:leave-end="transform opacity-0 scale-95"
                        class="absolute right-0 mt-2 w-48 origin-top-right rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800 z-10"
                    >
                        <div class="p-2">
                            <button
                                wire:click="$set('statusFilter', 'all')"
                                @click="open = false"
                                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
                            >
                                <span class="h-2 w-2 rounded-full bg-zinc-400"></span>
                                All Merchants
                            </button>
                            <button
                                wire:click="$set('statusFilter', 'active')"
                                @click="open = false"
                                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
                            >
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                Active Only
                            </button>
                            <button
                                wire:click="$set('statusFilter', 'inactive')"
                                @click="open = false"
                                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
                            >
                                <span class="h-2 w-2 rounded-full bg-red-500"></span>
                                Inactive Only
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Sort Dropdown --}}
                <div class="relative" x-data="{ open: false }">
                    <button
                        @click="open = !open"
                        class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm font-medium text-zinc-700 transition-all hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                        </svg>
                        Sort
                        <svg class="h-4 w-4" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <div
                        x-show="open"
                        @click.away="open = false"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="transform opacity-0 scale-95"
                        x-transition:enter-end="transform opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="transform opacity-100 scale-100"
                        x-transition:leave-end="transform opacity-0 scale-95"
                        class="absolute right-0 mt-2 w-56 origin-top-right rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800 z-10"
                    >
                        <div class="p-2">
                            <button
                                wire:click="$set('sortField', 'name'); $set('sortDirection', 'asc')"
                                @click="open = false"
                                class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
                            >
                                <span>Name (A-Z)</span>
                                @if($sortField === 'name' && $sortDirection === 'asc')
                                    <svg class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                @endif
                            </button>
                            <button
                                wire:click="$set('sortField', 'name'); $set('sortDirection', 'desc')"
                                @click="open = false"
                                class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
                            >
                                <span>Name (Z-A)</span>
                                @if($sortField === 'name' && $sortDirection === 'desc')
                                    <svg class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                @endif
                            </button>
                            <button
                                wire:click="$set('sortField', 'created_at'); $set('sortDirection', 'desc')"
                                @click="open = false"
                                class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
                            >
                                <span>Newest First</span>
                                @if($sortField === 'created_at' && $sortDirection === 'desc')
                                    <svg class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                @endif
                            </button>
                            <button
                                wire:click="$set('sortField', 'created_at'); $set('sortDirection', 'asc')"
                                @click="open = false"
                                class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
                            >
                                <span>Oldest First</span>
                                @if($sortField === 'created_at' && $sortDirection === 'asc')
                                    <svg class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                @endif
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Reset Filters --}}
                @if(strlen($search) > 0 || $statusFilter !== 'all' || $sortField !== 'name' || $sortDirection !== 'asc')
                    <button
                        wire:click="resetFilters"
                        class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm font-medium text-red-600 transition-all hover:bg-red-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-red-400 dark:hover:bg-red-950/30"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Reset
                    </button>
                @endif
            </div>
        </div>

        {{-- Active Filters Display --}}
        @if(strlen($search) > 0 || $statusFilter !== 'all')
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="text-xs text-zinc-500 dark:text-zinc-400">Active filters:</span>
                @if(strlen($search) > 0)
                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 dark:bg-blue-950/30 dark:text-blue-400">
                        Search: "{{ $search }}"
                        <button wire:click="$set('search', '')" class="ml-1 hover:text-blue-900 dark:hover:text-blue-300">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </span>
                @endif
                @if($statusFilter !== 'all')
                    <span class="inline-flex items-center gap-1 rounded-full bg-{{ $statusFilter === 'active' ? 'emerald' : 'red' }}-50 px-3 py-1 text-xs font-medium text-{{ $statusFilter === 'active' ? 'emerald' : 'red' }}-700 dark:bg-{{ $statusFilter === 'active' ? 'emerald' : 'red' }}-950/30 dark:text-{{ $statusFilter === 'active' ? 'emerald' : 'red' }}-400">
                        Status: {{ ucfirst($statusFilter) }}
                        <button wire:click="$set('statusFilter', 'all')" class="ml-1 hover:text-{{ $statusFilter === 'active' ? 'emerald' : 'red' }}-900">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </span>
                @endif
            </div>
        @endif
    </div>

    {{-- Table Card --}}
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
        {{-- Table Header with Results Count --}}
        <div class="border-b border-zinc-100 px-6 py-4 dark:border-zinc-800">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Results</span>
                    <span class="rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                        {{ $merchants->total() }}
                    </span>
                </div>
                <div class="text-xs text-zinc-400">
                    Showing {{ $merchants->firstItem() ?? 0 }} - {{ $merchants->lastItem() ?? 0 }} of {{ $merchants->total() }}
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-100 bg-zinc-50/50 dark:border-zinc-800 dark:bg-zinc-900/50">
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Merchant</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Email</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Joined</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($merchants as $merchant)
                        <tr wire:key="merchant-{{ $merchant->id }}" class="group transition-colors duration-150 hover:bg-zinc-50/80 dark:hover:bg-zinc-800/50">

                            {{-- Name + Avatar --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-sm font-semibold text-white shadow-sm shadow-blue-600/20">
                                        {{ strtoupper(substr($merchant->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $merchant->name }}</span>
                                        @if($merchant->email_verified_at)
                                            <span class="ml-2 inline-flex items-center gap-0.5 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Verified
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Email --}}
                            <td class="px-6 py-4">
                                <span class="text-zinc-500 dark:text-zinc-400">{{ $merchant->email }}</span>
                            </td>

                            {{-- Joined Date --}}
                            <td class="px-6 py-4">
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $merchant->created_at->format('M d, Y') }}
                                    <span class="text-xs text-zinc-400 dark:text-zinc-500">
                                        ({{ $merchant->created_at->diffForHumans() }})
                                    </span>
                                </div>
                            </td>

                            {{-- Status --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="relative inline-flex">
                                        <span class="flex h-2.5 w-2.5">
                                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75 {{ $merchant->is_active ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                                            <span class="relative inline-flex h-2.5 w-2.5 rounded-full {{ $merchant->is_active ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                        </span>
                                    </div>
                                    <span class="text-sm font-medium {{ $merchant->is_active ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }}">
                                        {{ $merchant->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </td>

                            {{-- Actions --}}
                            <td class="px-6 py-4 text-right">
                                <form action="{{ route('admin.merchants.toggle', ['user' => $merchant]) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    @if ($merchant->is_active)
                                        <button type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-600 transition-all duration-150 hover:bg-red-100 hover:border-red-300 hover:shadow-sm dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-400 dark:hover:bg-red-950/50">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                                            </svg>
                                            Deactivate
                                        </button>
                                    @else
                                        <button type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700 transition-all duration-150 hover:bg-emerald-100 hover:border-emerald-300 hover:shadow-sm dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-400 dark:hover:bg-emerald-950/50">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                            </svg>
                                            Activate
                                        </button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="rounded-full bg-zinc-100 p-4 dark:bg-zinc-800">
                                        <svg class="h-8 w-8 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                        </svg>
                                    </div>
                                    <p class="mt-4 font-semibold text-zinc-700 dark:text-zinc-300">No merchants found</p>
                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        @if(strlen($search) > 0 || $statusFilter !== 'all')
                                            Try adjusting your search or filter criteria.
                                        @else
                                            Merchants will appear here once they register.
                                        @endif
                                    </p>
                                    @if(strlen($search) > 0 || $statusFilter !== 'all')
                                        <button
                                            wire:click="resetFilters"
                                            class="mt-4 inline-flex items-center gap-2 rounded-lg bg-zinc-100 px-4 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            Clear all filters
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($merchants->hasPages())
            <div class="border-t border-zinc-100 px-6 py-4 dark:border-zinc-800">
                {{ $merchants->links() }}
            </div>
        @endif
    </div>
</div>

@push('styles')
<style>
    @keyframes slide-down {
        from {
            opacity: 0;
            transform: translateY(-0.5rem);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-slide-down {
        animation: slide-down 0.2s ease-out;
    }
</style>
@endpush
@endsection
