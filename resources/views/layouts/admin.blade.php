<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        @isset($title)
            <title>{{ $title }} - {{ config('app.name') }} Admin</title>
        @endisset
    </head>
    <body class="min-h-screen bg-zinc-100 dark:bg-zinc-900">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <a href="{{ route('admin.index') }}" class="flex items-center gap-2 px-2 py-2" wire:navigate>
                    <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ config('app.name') }} Admin</span>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group heading="Admin" class="grid">
                    <flux:sidebar.item icon="home" :href="route('admin.index')" :current="request()->routeIs('admin.index')" wire:navigate>
                        Dashboard
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="users" :href="route('admin.merchants.index')" :current="request()->routeIs('admin.merchants.*')" wire:navigate>
                        Merchants
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="credit-card" :href="route('admin.gateways.index')" :current="request()->routeIs('admin.gateways.*')" wire:navigate>
                        Gateways
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="currency-dollar" :href="route('admin.payments.index')" :current="request()->routeIs('admin.payments.*')" wire:navigate>
                        Payments
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="arrow-left" :href="route('home')" wire:navigate>
                    Back to site
                </flux:sidebar.item>
                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:sidebar.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer text-start">
                        Log out
                    </flux:sidebar.item>
                </form>
            </flux:sidebar.nav>
        </flux:sidebar>

        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <flux:spacer />
        </flux:header>

        <flux:main class="p-4 lg:p-6 lg:p-8">
            @yield('content')
        </flux:main>

        @fluxScripts
    </body>
</html>
