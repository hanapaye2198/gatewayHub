@extends('errors.layout')

@section('title', __('Page not found'))

@section('content')
    <div class="flex flex-col items-center gap-6 text-center">
        <div class="flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
            <flux:icon name="magnifying-glass" class="size-8 text-zinc-500 dark:text-zinc-400" />
        </div>

        <div class="space-y-2">
            <p class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                {{ __('404 Not Found') }}
            </p>
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Page not found') }}</h1>
            <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                {{ __('The page you are looking for does not exist or may have been moved.') }}
            </p>
        </div>

        <div class="flex w-full flex-col gap-3 sm:flex-row sm:justify-center">
            @auth
                @if (auth()->user()->role === \App\Models\User::ROLE_ADMIN && Route::has('admin.index'))
                    <flux:button variant="primary" :href="route('admin.index')" wire:navigate>
                        {{ __('Go to admin panel') }}
                    </flux:button>
                @elseif (Route::has('dashboard'))
                    <flux:button variant="primary" :href="route('dashboard')" wire:navigate>
                        {{ __('Go to dashboard') }}
                    </flux:button>
                @endif
            @else
                <flux:button variant="primary" :href="route('login')" wire:navigate>
                    {{ __('Sign in') }}
                </flux:button>
            @endauth

            <flux:button variant="ghost" :href="route('home')" wire:navigate>
                {{ __('Back to home') }}
            </flux:button>
        </div>
    </div>
@endsection
