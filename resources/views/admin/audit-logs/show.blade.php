@extends('layouts.admin')

@section('content')
    <div class="flex h-full w-full flex-1 flex-col gap-6 px-4 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-zinc-200 bg-white px-8 py-7 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
            <flux:button variant="ghost" icon="arrow-left" :href="route('admin.audit-logs.index')" wire:navigate class="-ms-2">
                {{ __('Audit Logs') }}
            </flux:button>
            <h1 class="mt-4 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ $log->action }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $log->description }}</p>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Actor') }}</dt>
                    <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $log->actorLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Merchant') }}</dt>
                    <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $log->merchantLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Target') }}</dt>
                    <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $log->targetLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Date') }}</dt>
                    <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $log->created_at?->format('Y-m-d H:i:s') }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('IP address') }}</dt>
                    <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $log->ip_address ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('User agent') }}</dt>
                    <dd class="mt-1 break-all text-zinc-900 dark:text-zinc-100">{{ $log->user_agent ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Previous values') }}</h2>
                <pre class="mt-4 overflow-x-auto text-xs text-zinc-700 dark:text-zinc-300">{{ $log->old_values ? json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : __('None') }}</pre>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('New values') }}</h2>
                <pre class="mt-4 overflow-x-auto text-xs text-zinc-700 dark:text-zinc-300">{{ $log->new_values ? json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : __('None') }}</pre>
            </div>
        </div>
    </div>
@endsection
