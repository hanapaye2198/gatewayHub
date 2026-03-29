<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Header Section with Gradient --}}
    <div class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
        {{-- Animated Background Pattern --}}
        <div class="absolute inset-0 bg-gradient-to-br from-blue-50/60 via-transparent to-transparent dark:from-blue-950/20 dark:via-transparent"></div>
        <div class="absolute right-0 top-0 -mt-10 -mr-10 h-40 w-40 rounded-full bg-blue-500/5 blur-3xl"></div>
        <div class="absolute bottom-0 left-0 -mb-10 -ml-10 h-40 w-40 rounded-full bg-emerald-500/5 blur-3xl"></div>

        <div class="relative px-8 py-7">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 shadow-lg shadow-blue-600/25">
                        <flux:icon name="building-storefront" class="size-6 text-white" />
                    </div>
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Merchants</h1>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                <span class="relative flex h-1.5 w-1.5">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75"></span>
                                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                </span>
                                {{ $merchants->total() }} total
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Manage merchant accounts, activate or deactivate access to the platform.</p>
                    </div>
                </div>

                {{-- Quick Stats --}}
                <div class="flex gap-2">
                    <div class="rounded-lg bg-emerald-50 px-4 py-2 dark:bg-emerald-950/30">
                        <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Active</span>
                        <p class="text-lg font-semibold text-emerald-700 dark:text-emerald-300">{{ $activeCount }}</p>
                    </div>
                    <div class="rounded-lg bg-red-50 px-4 py-2 dark:bg-red-950/30">
                        <span class="text-xs font-medium text-red-600 dark:text-red-400">Inactive</span>
                        <p class="text-lg font-semibold text-red-700 dark:text-red-300">{{ $inactiveCount }}</p>
                    </div>
                </div>
            </div>

            @if (session('status'))
                <div class="relative mt-5 animate-slide-down">
                    <flux:callout variant="success" icon="check-circle" class="rounded-xl">
                        {{ session('status') }}
                    </flux:callout>
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
                    <flux:icon name="magnifying-glass" class="size-4 text-zinc-400" />
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
                        <flux:icon name="x-mark" class="size-4" />
                    </button>
                @endif
            </div>

            {{-- Filter Buttons --}}
            <div class="flex items-center gap-2">
                {{-- Status Filter --}}
                <div class="relative" x-data="{ open: false }">
                    <button
                        type="button"
                        @click="open = !open"
                        class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm font-medium text-zinc-700 transition-all hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                    >
                        <flux:icon name="funnel" class="size-4" />
                        Status:
                        <span class="font-semibold">
                            @if($statusFilter === 'all') All
                            @elseif($statusFilter === 'active') Active
                            @else Inactive
                            @endif
                        </span>
                        <flux:icon name="chevron-down" class="size-4 transition-transform" ::class="{ 'rotate-180': open }" />
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
                        class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
                    >
                        <div class="p-2">
                            <button type="button" wire:click="$set('statusFilter', 'all')" @click="open = false" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                <span class="h-2 w-2 rounded-full bg-zinc-400"></span>
                                All Merchants
                            </button>
                            <button type="button" wire:click="$set('statusFilter', 'active')" @click="open = false" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                Active Only
                            </button>
                            <button type="button" wire:click="$set('statusFilter', 'inactive')" @click="open = false" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                <span class="h-2 w-2 rounded-full bg-red-500"></span>
                                Inactive Only
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Sort Dropdown --}}
                <div class="relative" x-data="{ open: false }">
                    <button
                        type="button"
                        @click="open = !open"
                        class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm font-medium text-zinc-700 transition-all hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                    >
                        <flux:icon name="bars-arrow-down" class="size-4" />
                        Sort
                        <flux:icon name="chevron-down" class="size-4 transition-transform" ::class="{ 'rotate-180': open }" />
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
                        class="absolute right-0 z-10 mt-2 w-56 origin-top-right rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
                    >
                        <div class="p-2">
                            <button type="button" wire:click="$set('sortField', 'name'); $set('sortDirection', 'asc')" @click="open = false" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                <span>Name (A-Z)</span>
                                @if($sortField === 'name' && $sortDirection === 'asc')
                                    <flux:icon name="check" class="size-4 text-blue-500" />
                                @endif
                            </button>
                            <button type="button" wire:click="$set('sortField', 'name'); $set('sortDirection', 'desc')" @click="open = false" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                <span>Name (Z-A)</span>
                                @if($sortField === 'name' && $sortDirection === 'desc')
                                    <flux:icon name="check" class="size-4 text-blue-500" />
                                @endif
                            </button>
                            <button type="button" wire:click="$set('sortField', 'created_at'); $set('sortDirection', 'desc')" @click="open = false" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                <span>Newest First</span>
                                @if($sortField === 'created_at' && $sortDirection === 'desc')
                                    <flux:icon name="check" class="size-4 text-blue-500" />
                                @endif
                            </button>
                            <button type="button" wire:click="$set('sortField', 'created_at'); $set('sortDirection', 'asc')" @click="open = false" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                <span>Oldest First</span>
                                @if($sortField === 'created_at' && $sortDirection === 'asc')
                                    <flux:icon name="check" class="size-4 text-blue-500" />
                                @endif
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Reset Filters --}}
                @if(strlen($search) > 0 || $statusFilter !== 'all' || $sortField !== 'name' || $sortDirection !== 'asc')
                    <button
                        type="button"
                        wire:click="resetFilters"
                        class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm font-medium text-red-600 transition-all hover:bg-red-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-red-400 dark:hover:bg-red-950/30"
                    >
                        <flux:icon name="x-mark" class="size-4" />
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
                        <button type="button" wire:click="$set('search', '')" class="ml-1 hover:text-blue-900 dark:hover:text-blue-300">
                            <flux:icon name="x-mark" class="size-3" />
                        </button>
                    </span>
                @endif
                @if($statusFilter !== 'all')
                    <span class="inline-flex items-center gap-1 rounded-full {{ $statusFilter === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400' : 'bg-red-50 text-red-700 dark:bg-red-950/30 dark:text-red-400' }} px-3 py-1 text-xs font-medium">
                        Status: {{ ucfirst($statusFilter) }}
                        <button type="button" wire:click="$set('statusFilter', 'all')" class="ml-1 hover:opacity-80">
                            <flux:icon name="x-mark" class="size-3" />
                        </button>
                    </span>
                @endif
            </div>
        @endif
    </div>

    {{-- Table Card --}}
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
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
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-sm font-semibold text-white shadow-sm shadow-blue-600/20">
                                        {{ strtoupper(mb_substr($merchant->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $merchant->name }}</span>
                                        @if($merchant->users->first()?->email_verified_at)
                                            <span class="ml-2 inline-flex items-center gap-0.5 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">
                                                <flux:icon name="check-circle" class="size-3" />
                                                Verified
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-zinc-500 dark:text-zinc-400">{{ $merchant->email }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $merchant->created_at->format('M d, Y') }}
                                    <span class="text-xs text-zinc-400 dark:text-zinc-500">
                                        ({{ $merchant->created_at->diffForHumans() }})
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="relative flex h-2.5 w-2.5">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75 {{ $merchant->is_active ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full {{ $merchant->is_active ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                    </span>
                                    <span class="text-sm font-medium {{ $merchant->is_active ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }}">
                                        {{ $merchant->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form action="{{ route('admin.merchants.toggle', ['merchant' => $merchant]) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    @if ($merchant->is_active)
                                        <flux:button type="submit" variant="danger" size="sm">
                                            Deactivate
                                        </flux:button>
                                    @else
                                        <flux:button type="submit" variant="primary" size="sm">
                                            Activate
                                        </flux:button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="rounded-full bg-zinc-100 p-4 dark:bg-zinc-800">
                                        <flux:icon name="building-storefront" class="size-8 text-zinc-400" />
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
                                        <flux:button wire:click="resetFilters" variant="ghost" size="sm" class="mt-4">
                                            Clear all filters
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($merchants->hasPages())
            <div class="border-t border-zinc-100 px-6 py-4 dark:border-zinc-800">
                {{ $merchants->links() }}
            </div>
        @endif
    </div>
    <style>
        @keyframes slide-down { from { opacity: 0; transform: translateY(-0.5rem); } to { opacity: 1; transform: translateY(0); } }
        .animate-slide-down { animation: slide-down 0.2s ease-out; }
    </style>
</div>
