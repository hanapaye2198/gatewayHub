@extends('layouts.admin')

@section('content')
    <div class="flex h-full w-full flex-1 flex-col gap-6 px-4 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-zinc-200 bg-white px-8 py-7 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
            <flux:button variant="ghost" icon="arrow-left" :href="route('admin.merchants.show', $merchant)" wire:navigate class="-ms-2">
                {{ $merchant->name }}
            </flux:button>
            <h1 class="mt-4 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Edit merchant') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Update the merchant profile. Status changes stay on the activate and suspend action.') }}
            </p>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
            @include('admin.merchants._form', ['merchant' => $merchant])
        </div>
    </div>
@endsection
