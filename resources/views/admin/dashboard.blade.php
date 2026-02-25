@extends('layouts.admin')

@section('content')
<div class="flex h-full w-full flex-1 flex-col gap-8">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Admin Dashboard</h1>
        <p class="mt-1 text-zinc-600 dark:text-zinc-400">Platform overview. Use the sidebar to manage merchants, gateways, and view payments.</p>
    </div>

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-start justify-between">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Payments</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($totalPayments) }}</p>
                    <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">All time</p>
                </div>
                <div class="ml-4 flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700/50">
                    <flux:icon name="banknotes" class="size-5 text-zinc-600 dark:text-zinc-400" />
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-start justify-between">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Gross Processed</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">₱{{ number_format($totalGrossProcessed, 2) }}</p>
                    <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">Paid payments only</p>
                </div>
                <div class="ml-4 flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700/50">
                    <flux:icon name="currency-dollar" class="size-5 text-zinc-600 dark:text-zinc-400" />
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-start justify-between">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Platform Revenue</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">₱{{ number_format($platformRevenue, 2) }}</p>
                    <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">Posted fees only</p>
                </div>
                <div class="ml-4 flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700/50">
                    <flux:icon name="chart-bar" class="size-5 text-zinc-600 dark:text-zinc-400" />
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-start justify-between">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Net Volume</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">₱{{ number_format($totalNetVolume, 2) }}</p>
                    <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">To merchants (paid only)</p>
                </div>
                <div class="ml-4 flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700/50">
                    <flux:icon name="currency-dollar" class="size-5 text-zinc-600 dark:text-zinc-400" />
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-start justify-between">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Active Merchants</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($activeMerchants) }}</p>
                    <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">Can use API & dashboard</p>
                </div>
                <div class="ml-4 flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700/50">
                    <flux:icon name="users" class="size-5 text-zinc-600 dark:text-zinc-400" />
                </div>
            </div>
        </div>
    </div>

    @if (! empty($revenueByGateway))
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Revenue by Gateway</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Platform fees collected per gateway</p>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Gateway</th>
                            <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($revenueByGateway as $gateway => $total)
                            <tr>
                                <td class="px-4 py-2 text-sm text-zinc-900 dark:text-zinc-100">{{ ucfirst($gateway) }}</td>
                                <td class="px-4 py-2 text-right text-sm tabular-nums text-zinc-900 dark:text-zinc-100">₱{{ number_format($total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
