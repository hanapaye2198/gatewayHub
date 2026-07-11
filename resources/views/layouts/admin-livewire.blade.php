<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        @isset($title)
            <title>{{ $title }} - {{ config('app.name') }} Admin</title>
        @endisset
    </head>
    <body class="min-h-screen bg-zinc-100 dark:bg-zinc-950">
        @include('partials.admin-sidebar')

        <x-layout-topbar :title="$title ?? null" :context="__('Admin Panel')" />

        <flux:main class="app-shell-main">
            {{ $slot }}
        </flux:main>

        @fluxScripts
    </body>
</html>
