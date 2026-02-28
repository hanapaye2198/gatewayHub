<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-8">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Dashboard') }}</h1>
            <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Welcome to your merchant dashboard. Use the sidebar to view payments, manage API credentials, and review gateway availability.') }}</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-start justify-between">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total Collections') }}</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
                            PHP {{ number_format((float) ($totalCollections ?? 0), 2) }}
                        </p>
                        <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Paid transactions only') }}</p>
                    </div>
                    <div class="ml-4 flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700/50">
                        <flux:icon name="banknotes" class="size-5 text-zinc-600 dark:text-zinc-400" />
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-start justify-between">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Payments') }}</p>
                        <p class="mt-2 text-lg font-medium text-zinc-900 dark:text-zinc-100">{{ __('View payment history') }}</p>
                        <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Use the sidebar to navigate') }}</p>
                    </div>
                    <div class="ml-4 flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700/50">
                        <flux:icon name="currency-dollar" class="size-5 text-zinc-600 dark:text-zinc-400" />
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-start justify-between">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('API Credentials') }}</p>
                        <p class="mt-2 text-lg font-medium text-zinc-900 dark:text-zinc-100">{{ __('Manage your API key') }}</p>
                        <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Use the sidebar to navigate') }}</p>
                    </div>
                    <div class="ml-4 flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700/50">
                        <flux:icon name="key" class="size-5 text-zinc-600 dark:text-zinc-400" />
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-start justify-between">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Gateways') }}</p>
                        <p class="mt-2 text-lg font-medium text-zinc-900 dark:text-zinc-100">{{ __('Review gateway availability') }}</p>
                        <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Use the sidebar to navigate') }}</p>
                    </div>
                    <div class="ml-4 flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700/50">
                        <flux:icon name="credit-card" class="size-5 text-zinc-600 dark:text-zinc-400" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
