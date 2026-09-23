@extends('layouts.admin')

@section('content')
    <div class="flex h-full w-full flex-1 flex-col gap-6 px-4 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-zinc-200 bg-white px-8 py-7 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
            <flux:button variant="ghost" icon="arrow-left" :href="route('admin.administrators.index')" wire:navigate class="-ms-2">
                {{ __('Administrators') }}
            </flux:button>
            <h1 class="mt-4 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Create administrator') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('This account can operate the platform. It cannot manage administrators or the platform fee.') }}
            </p>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
            @include('admin.administrators._form')
        </div>
    </div>
@endsection
