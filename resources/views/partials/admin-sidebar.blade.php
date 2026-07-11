<flux:sidebar
    sticky
    collapsible
    data-app-shell-sidebar
    class="app-shell-sidebar"
>
    <flux:sidebar.header class="app-shell-sidebar-header">
        <a
            href="{{ route('admin.index') }}"
            wire:navigate
            class="flex min-w-0 flex-1 items-center gap-3"
            data-flux-sidebar-brand
        >
            <div class="app-shell-brand-icon">
                <x-app-logo-icon class="size-5 fill-emerald-500 dark:fill-emerald-400" />
            </div>
            <div class="min-w-0 in-data-flux-sidebar-collapsed-desktop:hidden">
                <div class="truncate text-sm font-semibold tracking-tight text-zinc-900 dark:text-white">
                    {{ config('app.name') }}
                </div>
                <div class="truncate text-xs font-medium text-zinc-500 dark:text-zinc-400">
                    {{ __('Admin Panel') }}
                </div>
            </div>
        </a>
    </flux:sidebar.header>

    <flux:sidebar.nav class="app-shell-sidebar-nav">
        <flux:sidebar.group class="grid gap-0.5">
            <flux:sidebar.item icon="home" :href="route('admin.index')" :current="request()->routeIs('admin.index')" wire:navigate>
                {{ __('Dashboard') }}
            </flux:sidebar.item>
            <flux:sidebar.item icon="users" :href="route('admin.merchants.index')" :current="request()->routeIs('admin.merchants.*')" wire:navigate>
                {{ __('Merchants') }}
            </flux:sidebar.item>
            <flux:sidebar.item icon="credit-card" :href="route('admin.gateways.index')" :current="request()->routeIs('admin.gateways.*')" wire:navigate>
                {{ __('Gateways') }}
            </flux:sidebar.item>
            <flux:sidebar.item icon="currency-dollar" :href="route('admin.payments.index')" :current="request()->routeIs('admin.payments.*')" wire:navigate>
                {{ __('Payments') }}
            </flux:sidebar.item>
            @if (config('surepay.features.wallet_settlement', false))
                <flux:sidebar.item icon="wallet" :href="route('admin.surepay-wallets.index')" :current="request()->routeIs('admin.surepay-wallets.*') || request()->routeIs('admin.tunnel-wallets.*')" wire:navigate>
                    {{ __('Settlement Controls') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="banknotes" :href="route('admin.surepay-wallets.dashboard')" :current="request()->routeIs('admin.surepay-wallets.dashboard') || request()->routeIs('admin.tunnel-wallets.dashboard')" wire:navigate>
                    {{ __('Settlement Dashboard') }}
                </flux:sidebar.item>
            @endif
        </flux:sidebar.group>
    </flux:sidebar.nav>
</flux:sidebar>
