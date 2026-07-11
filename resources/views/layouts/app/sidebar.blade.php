@props([
    'title' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        @if ($title)
            <title>{{ $title }} - {{ config('app.name') }}</title>
        @endif
    </head>
    <body class="min-h-screen bg-zinc-100 dark:bg-zinc-950">
        <flux:sidebar
            sticky
            collapsible
            data-app-shell-sidebar
            class="app-shell-sidebar"
        >
            <flux:sidebar.header class="app-shell-sidebar-header">
                @if ($merchantBranding)
                    <flux:sidebar.brand
                        :name="$merchantBranding['name']"
                        :href="route('dashboard')"
                        wire:navigate
                        class="min-w-0 flex-1"
                    >
                        <x-slot name="logo">
                            <img
                                src="{{ $merchantBranding['logo'] }}"
                                alt=""
                                class="size-8 rounded-lg object-contain ring-1 ring-zinc-200/80 dark:ring-white/10"
                            />
                        </x-slot>
                    </flux:sidebar.brand>
                @else
                    <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate class="min-w-0 flex-1" />
                @endif
            </flux:sidebar.header>

            <flux:sidebar.nav class="app-shell-sidebar-nav">
                <flux:sidebar.group class="grid gap-0.5">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard') || request()->routeIs('dashboard.payments')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="key" :href="route('dashboard.api-credentials')" :current="request()->routeIs('dashboard.api-credentials')" wire:navigate>
                        {{ __('API Credentials') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="credit-card" :href="route('dashboard.gateways')" :current="request()->routeIs('dashboard.gateways')" wire:navigate>
                        {{ __('Gateways') }}
                    </flux:sidebar.item>
                    @if (auth()->user()?->role === \App\Models\User::ROLE_MERCHANT_USER)
                        <flux:sidebar.item icon="book-open-text" :href="route('dashboard.docs')" :current="request()->routeIs('dashboard.docs')" wire:navigate>
                            {{ __('Docs') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>
        </flux:sidebar>

        <x-layout-topbar :title="$title" />

        {{ $slot }}

        @fluxScripts
    </body>
</html>
