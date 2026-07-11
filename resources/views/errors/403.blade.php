@php
    $message = trim((string) ($exception->getMessage() ?? ''));
    if ($message === '') {
        $message = __('You do not have permission to access this page.');
    }

    $user = auth()->user();
    $isAdmin = $user?->role === \App\Models\User::ROLE_ADMIN;
    $isMerchant = $user?->role === \App\Models\User::ROLE_MERCHANT_USER;
    $onAdminPath = request()->is('admin', 'admin/*');
@endphp

@extends('errors.layout')

@section('title', __('Access denied'))

@section('content')
    <div class="flex flex-col items-center gap-6 text-center">
        <div class="flex size-16 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-950/50">
            <flux:icon name="shield-exclamation" class="size-8 text-rose-600 dark:text-rose-400" />
        </div>

        <div class="space-y-2">
            <p class="text-sm font-semibold uppercase tracking-wider text-rose-600 dark:text-rose-400">
                {{ __('403 Forbidden') }}
            </p>
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Access denied') }}</h1>
            <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $message }}</p>
        </div>

        <div class="flex w-full flex-col gap-3 sm:flex-row sm:justify-center">
            @if ($isAdmin && ! $onAdminPath && Route::has('admin.index'))
                <flux:button variant="primary" :href="route('admin.index')" wire:navigate>
                    {{ __('Go to admin panel') }}
                </flux:button>
            @endif

            @if ($isMerchant && Route::has('dashboard'))
                <flux:button variant="{{ $isAdmin ? 'ghost' : 'primary' }}" :href="route('dashboard')" wire:navigate>
                    {{ __('Go to merchant dashboard') }}
                </flux:button>
            @endif

            @if ($user === null)
                <flux:button variant="primary" :href="route('login')" wire:navigate>
                    {{ __('Sign in') }}
                </flux:button>
            @endif

            <flux:button variant="ghost" :href="route('home')" wire:navigate>
                {{ __('Back to home') }}
            </flux:button>
        </div>
    </div>
@endsection
