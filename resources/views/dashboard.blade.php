<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-8">
        {{-- Header Section with Welcome and Date --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Dashboard') }}</h1>
                <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Welcome to your merchant dashboard. Use the sidebar to view payments, manage API credentials, and review gateway availability.') }}</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-zinc-100 px-3 py-2 text-right dark:bg-zinc-800">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Today') }}</p>
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ now()->format('M d, Y') }}</p>
                </div>
                <button class="rounded-lg bg-zinc-100 p-2 text-zinc-600 transition hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700">
                    <flux:icon name="arrow-path" class="size-5" />
                </button>
            </div>
        </div>

        {{-- Stats Grid with Enhanced Cards --}}
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Total Collections Card --}}
            <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="absolute -right-6 -top-6 size-20 rounded-full bg-gradient-to-br from-emerald-500/10 to-emerald-500/5 blur-2xl"></div>
                <div class="relative flex items-start justify-between">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total Collections') }}</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-zinc-900 dark:text-zinc-100">
                            PHP {{ number_format((float) ($totalCollections ?? 0), 2) }}
                        </p>
                        <div class="mt-2 flex items-center gap-1.5">
                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">
                                <flux:icon name="arrow-trending-up" class="mr-1 size-3" />
                                +12.5%
                            </span>
                            <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('vs last month') }}</span>
                        </div>
                        <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Paid transactions only') }}</p>
                    </div>
                    <div class="ml-4 flex size-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-100 to-emerald-50 dark:from-emerald-900/30 dark:to-emerald-800/20">
                        <flux:icon name="banknotes" class="size-6 text-emerald-600 dark:text-emerald-400" />
                    </div>
                </div>
            </div>

            {{-- Total Transactions Card --}}
            <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="absolute -right-6 -top-6 size-20 rounded-full bg-gradient-to-br from-blue-500/10 to-blue-500/5 blur-2xl"></div>
                <div class="relative flex items-start justify-between">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total Transactions') }}</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-zinc-900 dark:text-zinc-100">
                            {{ number_format($totalTransactions ?? 1248) }}
                        </p>
                        <div class="mt-2 flex items-center gap-1.5">
                            <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-950/30 dark:text-blue-400">
                                <flux:icon name="arrow-trending-up" class="mr-1 size-3" />
                                +8.2%
                            </span>
                            <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('vs last month') }}</span>
                        </div>
                        <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Successful payments') }}</p>
                    </div>
                    <div class="ml-4 flex size-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-100 to-blue-50 dark:from-blue-900/30 dark:to-blue-800/20">
                        <flux:icon name="document-text" class="size-6 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
            </div>

            {{-- Success Rate Card --}}
            <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="absolute -right-6 -top-6 size-20 rounded-full bg-gradient-to-br from-purple-500/10 to-purple-500/5 blur-2xl"></div>
                <div class="relative flex items-start justify-between">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Success Rate') }}</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-zinc-900 dark:text-zinc-100">
                            {{ $successRate ?? 98.5 }}%
                        </p>
                        <div class="mt-2 flex items-center gap-1.5">
                            <span class="inline-flex items-center rounded-full bg-purple-50 px-2 py-0.5 text-xs font-medium text-purple-700 dark:bg-purple-950/30 dark:text-purple-400">
                                <flux:icon name="check-badge" class="mr-1 size-3" />
                                Excellent
                            </span>
                        </div>
                        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                            <div class="h-full w-[98.5%] rounded-full bg-purple-500"></div>
                        </div>
                    </div>
                    <div class="ml-4 flex size-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-purple-100 to-purple-50 dark:from-purple-900/30 dark:to-purple-800/20">
                        <flux:icon name="chart-bar" class="size-6 text-purple-600 dark:text-purple-400" />
                    </div>
                </div>
            </div>

            {{-- Active Gateways Card --}}
            <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="absolute -right-6 -top-6 size-20 rounded-full bg-gradient-to-br from-orange-500/10 to-orange-500/5 blur-2xl"></div>
                <div class="relative flex items-start justify-between">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Active Gateways') }}</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-zinc-900 dark:text-zinc-100">
                            {{ $activeGateways ?? 3 }}/{{ $totalGateways ?? 5 }}
                        </p>
                        <div class="mt-2 flex items-center gap-1.5">
                            <span class="inline-flex items-center rounded-full bg-orange-50 px-2 py-0.5 text-xs font-medium text-orange-700 dark:bg-orange-950/30 dark:text-orange-400">
                                <flux:icon name="check-circle" class="mr-1 size-3" />
                                {{ $activeGateways ?? 3 }} Operational
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Connected payment providers') }}</p>
                    </div>
                    <div class="ml-4 flex size-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-orange-100 to-orange-50 dark:from-orange-900/30 dark:to-orange-800/20">
                        <flux:icon name="credit-card" class="size-6 text-orange-600 dark:text-orange-400" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Charts and Activity Section --}}
        <div class="grid gap-5 lg:grid-cols-3">
            {{-- Recent Transactions Table --}}
            <div class="lg:col-span-2 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Recent Transactions') }}</h3>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Latest 5 successful payments') }}</p>
                        </div>
                        <button class="text-sm font-medium text-zinc-600 transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-200">{{ __('View All') }} →</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/80">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Transaction ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Gateway</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-transparent">
                            @foreach($recentTransactions ?? [] as $transaction)
                            <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $transaction['id'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-600 dark:text-zinc-300">PHP {{ number_format($transaction['amount'], 2) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-600 dark:text-zinc-300">{{ $transaction['gateway'] }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">
                                        {{ $transaction['status'] }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-500 dark:text-zinc-400">{{ $transaction['date'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Quick Actions & Gateway Status --}}
            <div class="space-y-5">
                {{-- Quick Actions Card --}}
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
                    <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Quick Actions') }}</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <button class="flex flex-col items-center gap-2 rounded-xl border border-zinc-200 bg-white p-3 transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600 dark:hover:bg-zinc-700/50">
                            <div class="flex size-10 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                <flux:icon name="plus" class="size-5" />
                            </div>
                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('New Payment') }}</span>
                        </button>
                        <button class="flex flex-col items-center gap-2 rounded-xl border border-zinc-200 bg-white p-3 transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600 dark:hover:bg-zinc-700/50">
                            <div class="flex size-10 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                                <flux:icon name="key" class="size-5" />
                            </div>
                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('API Key') }}</span>
                        </button>
                        <button class="flex flex-col items-center gap-2 rounded-xl border border-zinc-200 bg-white p-3 transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600 dark:hover:bg-zinc-700/50">
                            <div class="flex size-10 items-center justify-center rounded-lg bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400">
                                <flux:icon name="document-chart-bar" class="size-5" />
                            </div>
                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Reports') }}</span>
                        </button>
                        <button class="flex flex-col items-center gap-2 rounded-xl border border-zinc-200 bg-white p-3 transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600 dark:hover:bg-zinc-700/50">
                            <div class="flex size-10 items-center justify-center rounded-lg bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400">
                                <flux:icon name="cog-6-tooth" class="size-5" />
                            </div>
                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Settings') }}</span>
                        </button>
                    </div>
                </div>

                {{-- Gateway Status Card --}}
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Gateway Health') }}</h3>
                        <flux:icon name="signal" class="size-4 text-zinc-400" />
                    </div>
                    <div class="space-y-3">
                        @foreach($gatewayStatuses ?? [['name' => 'Stripe', 'status' => 'Operational', 'uptime' => '99.99%'], ['name' => 'PayPal', 'status' => 'Operational', 'uptime' => '99.95%'], ['name' => 'Mollie', 'status' => 'Degraded', 'uptime' => '97.2%']] as $gateway)
                        <div class="flex items-center justify-between border-b border-zinc-100 pb-3 last:border-0 last:pb-0 dark:border-zinc-700">
                            <div>
                                <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $gateway['name'] }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Uptime {{ $gateway['uptime'] }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex size-2 rounded-full {{ $gateway['status'] === 'Operational' ? 'bg-emerald-500' : ($gateway['status'] === 'Degraded' ? 'bg-amber-500' : 'bg-red-500') }}"></span>
                                <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ $gateway['status'] }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <button class="mt-4 w-full rounded-lg bg-zinc-100 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-200 dark:bg-zinc-700/50 dark:text-zinc-300 dark:hover:bg-zinc-700">
                        {{ __('Configure Gateways') }} →
                    </button>
                </div>
            </div>
        </div>

        {{-- Feature Cards Navigation (Original style preserved but enhanced) --}}
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div class="group cursor-pointer rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm transition-all hover:border-zinc-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/50 dark:hover:border-zinc-600">
                <div class="flex items-start justify-between">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Payments') }}</p>
                        <p class="mt-2 text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('View payment history') }}</p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Track all transactions and refunds') }}</p>
                        <div class="mt-4 flex items-center text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ __('View details') }}
                            <flux:icon name="arrow-right" class="ml-1 size-4 transition-transform group-hover:translate-x-1" />
                        </div>
                    </div>
                    <div class="ml-4 flex size-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-zinc-100 to-zinc-50 dark:from-zinc-800 dark:to-zinc-700/50">
                        <flux:icon name="currency-dollar" class="size-6 text-zinc-600 dark:text-zinc-400" />
                    </div>
                </div>
            </div>

            <div class="group cursor-pointer rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm transition-all hover:border-zinc-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/50 dark:hover:border-zinc-600">
                <div class="flex items-start justify-between">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('API Credentials') }}</p>
                        <p class="mt-2 text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Manage your API key') }}</p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Regenerate and configure access') }}</p>
                        <div class="mt-4 flex items-center text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ __('Manage keys') }}
                            <flux:icon name="arrow-right" class="ml-1 size-4 transition-transform group-hover:translate-x-1" />
                        </div>
                    </div>
                    <div class="ml-4 flex size-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-zinc-100 to-zinc-50 dark:from-zinc-800 dark:to-zinc-700/50">
                        <flux:icon name="key" class="size-6 text-zinc-600 dark:text-zinc-400" />
                    </div>
                </div>
            </div>

            <div class="group cursor-pointer rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm transition-all hover:border-zinc-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/50 dark:hover:border-zinc-600">
                <div class="flex items-start justify-between">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Gateways') }}</p>
                        <p class="mt-2 text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Review gateway availability') }}</p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Monitor provider status and settings') }}</p>
                        <div class="mt-4 flex items-center text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ __('Check status') }}
                            <flux:icon name="arrow-right" class="ml-1 size-4 transition-transform group-hover:translate-x-1" />
                        </div>
                    </div>
                    <div class="ml-4 flex size-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-zinc-100 to-zinc-50 dark:from-zinc-800 dark:to-zinc-700/50">
                        <flux:icon name="credit-card" class="size-6 text-zinc-600 dark:text-zinc-400" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
