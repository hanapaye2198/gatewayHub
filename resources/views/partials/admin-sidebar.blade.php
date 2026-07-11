<flux:sidebar
    sticky
    collapsible
    data-app-shell-sidebar
    class="app-shell-sidebar app-shell-sidebar--admin"
>
    <flux:sidebar.header class="app-shell-sidebar-header">
        <a
            href="{{ route('admin.index') }}"
            wire:navigate
            class="flex min-w-0 flex-1 items-center gap-3"
            data-flux-sidebar-brand
        >
            <div class="app-shell-brand-logo">
                <x-app-logo-icon class="size-full" />
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
        <flux:sidebar.group :heading="__('Administration')" class="grid gap-1">
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

    <div class="app-shell-sidebar-footer in-data-flux-sidebar-collapsed-desktop:hidden">
        <p class="text-[0.65rem] font-medium text-zinc-500 dark:text-zinc-500">{{ config('app.name') }}</p>
        <p class="mt-0.5 text-[0.6rem] text-zinc-400 dark:text-zinc-600">{{ __('Admin Panel') }}</p>
    </div>
</flux:sidebar>
