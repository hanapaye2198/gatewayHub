@extends('layouts.admin')
@section('content')
<div class="flex h-full w-full flex-1 flex-col gap-6 px-4 sm:px-6 lg:px-8">

    {{-- Page Header --}}
    <div class="rounded-2xl border border-zinc-100 bg-gradient-to-br from-white to-zinc-50/60 px-8 py-7 shadow-sm dark:border-zinc-700/60 dark:from-zinc-800 dark:to-zinc-800/80">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-zinc-900 shadow-md dark:bg-zinc-100">
                    <flux:icon name="banknotes" class="size-6 text-white dark:text-zinc-900" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">Payments</h1>
                    <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">Read-only list of Coins-orchestrated payments across all merchants.</p>
                </div>
            </div>
            <div class="flex flex-shrink-0 items-center gap-2.5">
                <div class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2 dark:border-zinc-600 dark:bg-zinc-700/50">
                    <flux:icon name="arrow-trending-up" class="size-4 text-emerald-500" />
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">Platform revenue</span>
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100">PHP {{ number_format($totalPlatformRevenue ?? 0, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="group rounded-2xl border border-zinc-100 bg-white p-5 shadow-sm transition-shadow duration-150 hover:shadow-md dark:border-zinc-700/60 dark:bg-zinc-800">
            <div class="flex items-start justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Total Transactions</p>
                <div class="flex size-8 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                    <flux:icon name="queue-list" class="size-4 text-zinc-500 dark:text-zinc-400" />
                </div>
            </div>
            <p class="mt-3 text-3xl font-bold tabular-nums tracking-tight text-zinc-900 dark:text-zinc-100">{{ number_format((int) ($summary['total_transactions'] ?? 0)) }}</p>
        </div>
        <div class="group rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm transition-shadow duration-150 hover:shadow-md dark:border-emerald-900/30 dark:bg-zinc-800">
            <div class="flex items-start justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Paid Collections</p>
                <div class="flex size-8 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-900/20">
                    <flux:icon name="check-circle" class="size-4 text-emerald-500" />
                </div>
            </div>
            <p class="mt-3 text-2xl font-bold tabular-nums tracking-tight text-zinc-900 dark:text-zinc-100">PHP {{ number_format((float) ($summary['paid_collections'] ?? 0), 2) }}</p>
        </div>
        <div class="group rounded-2xl border border-amber-100 bg-white p-5 shadow-sm transition-shadow duration-150 hover:shadow-md dark:border-amber-900/30 dark:bg-zinc-800">
            <div class="flex items-start justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Pending</p>
                <div class="flex size-8 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-900/20">
                    <flux:icon name="clock" class="size-4 text-amber-500" />
                </div>
            </div>
            <p class="mt-3 text-3xl font-bold tabular-nums tracking-tight text-zinc-900 dark:text-zinc-100">{{ number_format((int) ($summary['pending_count'] ?? 0)) }}</p>
        </div>
        <div class="group rounded-2xl border border-red-100 bg-white p-5 shadow-sm transition-shadow duration-150 hover:shadow-md dark:border-red-900/30 dark:bg-zinc-800">
            <div class="flex items-start justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Failed / Refunded</p>
                <div class="flex size-8 items-center justify-center rounded-lg bg-red-50 dark:bg-red-900/20">
                    <flux:icon name="x-circle" class="size-4 text-red-500" />
                </div>
            </div>
            <p class="mt-3 text-3xl font-bold tabular-nums tracking-tight text-zinc-900 dark:text-zinc-100">{{ number_format((int) ($summary['failed_refunded_count'] ?? 0)) }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="overflow-hidden rounded-2xl border border-zinc-100 bg-white shadow-sm dark:border-zinc-700/60 dark:bg-zinc-800">
        <div class="flex items-center gap-3 border-b border-zinc-100 px-7 py-4 dark:border-zinc-700/60">
            <div class="flex size-7 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                <flux:icon name="funnel" class="size-3.5 text-zinc-500 dark:text-zinc-400" />
            </div>
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Filters</h2>
        </div>
        <div class="px-7 py-5">
            <form method="GET" action="{{ route('admin.payments.index') }}" class="grid gap-4 md:grid-cols-3 lg:grid-cols-6">
                <div class="flex flex-col gap-1.5">
                    <label for="merchant_id" class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Merchant</label>
                    <select id="merchant_id" name="merchant_id" class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm transition-colors focus:border-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-200 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:ring-zinc-700">
                        <option value="">All merchants</option>
                        @foreach ($merchants as $merchant)
                            <option value="{{ $merchant->id }}" @selected((string) $activeFilters['merchant_id'] === (string) $merchant->id)>{{ $merchant->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="gateway_code" class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Gateway</label>
                    <select id="gateway_code" name="gateway_code" class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm transition-colors focus:border-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-200 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:ring-zinc-700">
                        <option value="">All gateways</option>
                        @foreach ($gateways as $gateway)
                            <option value="{{ $gateway->code }}" @selected((string) $activeFilters['gateway_code'] === (string) $gateway->code)>{{ $gateway->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="status" class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Status</label>
                    <select id="status" name="status" class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm transition-colors focus:border-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-200 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:ring-zinc-700">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected((string) $activeFilters['status'] === (string) $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="reference" class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Reference</label>
                    <input id="reference" name="reference" type="text" value="{{ (string) ($activeFilters['reference'] ?? '') }}" placeholder="Search reference…" class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm placeholder:text-zinc-400 transition-colors focus:border-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-200 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-600 dark:focus:ring-zinc-700">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="from_date" class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">From date</label>
                    <input id="from_date" name="from_date" type="date" value="{{ (string) ($activeFilters['from_date'] ?? '') }}" class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm transition-colors focus:border-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-200 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:ring-zinc-700">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="to_date" class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">To date</label>
                    <input id="to_date" name="to_date" type="date" value="{{ (string) ($activeFilters['to_date'] ?? '') }}" class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm transition-colors focus:border-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-200 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:ring-zinc-700">
                </div>

                {{-- Action buttons spanning full row --}}
                @php
                    $exportFilters = array_filter([
                        'merchant_id'  => $activeFilters['merchant_id']  ?? null,
                        'gateway_code' => $activeFilters['gateway_code'] ?? null,
                        'status'       => $activeFilters['status']       ?? null,
                        'reference'    => $activeFilters['reference']    ?? null,
                        'from_date'    => $activeFilters['from_date']    ?? null,
                        'to_date'      => $activeFilters['to_date']      ?? null,
                    ], static fn ($value): bool => $value !== null && $value !== '');
                @endphp
                <div class="flex items-center gap-2 md:col-span-3 lg:col-span-6">
                    <flux:button type="submit" variant="primary" size="sm">
                        Apply Filters
                    </flux:button>
                    <a href="{{ route('admin.payments.index') }}" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3.5 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                        Clear
                    </a>
                    <a href="{{ route('admin.payments.export', $exportFilters) }}" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3.5 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                        <flux:icon name="arrow-down-tray" class="size-4" />
                        Export CSV
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Error --}}
    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700 dark:border-red-800/40 dark:bg-red-900/20 dark:text-red-400">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Payments Table --}}
    <div class="overflow-hidden rounded-2xl border border-zinc-100 bg-white shadow-sm dark:border-zinc-700/60 dark:bg-zinc-800">
        <div class="flex items-center justify-between border-b border-zinc-100 px-7 py-4 dark:border-zinc-700/60">
            <div class="flex items-center gap-3">
                <div class="flex size-7 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                    <flux:icon name="table-cells" class="size-3.5 text-zinc-500 dark:text-zinc-400" />
                </div>
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Transaction Log</h2>
            </div>
            @if ($payments->total() ?? false)
                <span class="rounded-full border border-zinc-200 bg-zinc-50 px-2.5 py-1 text-xs font-semibold text-zinc-500 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">
                    {{ number_format($payments->total()) }} records
                </span>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px]">
                <thead>
                    <tr class="border-b border-zinc-100 bg-zinc-50/70 dark:border-zinc-700/60 dark:bg-zinc-900/30">
                        <th class="px-7 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Reference</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Merchant</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Gateway</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Amount</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Platform Fee</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Net</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Status</th>
                        <th class="px-7 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
                    @forelse ($payments as $payment)
                        <tr class="transition-colors duration-100 hover:bg-zinc-50/80 dark:hover:bg-zinc-700/25">
                            <td class="px-7 py-4">
                                <code class="rounded-md border border-zinc-200 bg-zinc-50 px-2 py-1 text-xs font-mono font-medium text-zinc-600 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">{{ $payment->reference_id }}</code>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex size-7 flex-shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-xs font-bold text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                                        {{ strtoupper(mb_substr($payment->user?->name ?? 'N', 0, 1)) }}
                                    </div>
                                    <span class="whitespace-nowrap text-sm text-zinc-700 dark:text-zinc-300">{{ $payment->user?->name ?? 'N/A' }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="whitespace-nowrap text-sm text-zinc-600 dark:text-zinc-400">{{ $payment->gateway?->name ?? $payment->gateway_code }}</span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <span class="font-mono text-sm tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($payment->amount, 2) }}</span>
                                <span class="ml-1 text-xs text-zinc-400">{{ $payment->currency }}</span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                @if ($payment->platformFee)
                                    <span class="font-mono text-sm tabular-nums text-zinc-700 dark:text-zinc-300">{{ number_format($payment->platformFee->fee_amount, 2) }}</span>
                                    <span class="ml-1 text-xs text-zinc-400">{{ $payment->currency }}</span>
                                @else
                                    <span class="text-sm text-zinc-400 dark:text-zinc-500">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                @if ($payment->platformFee)
                                    <span class="font-mono text-sm font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($payment->platformFee->net_amount, 2) }}</span>
                                    <span class="ml-1 text-xs text-zinc-400">{{ $payment->currency }}</span>
                                @else
                                    <span class="text-sm text-zinc-400 dark:text-zinc-500">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-center">
                                <x-status-badge :status="$payment->status" />
                            </td>
                            <td class="px-7 py-4 text-right">
                                <span class="font-mono text-xs tabular-nums text-zinc-500 dark:text-zinc-400">{{ $payment->created_at->format('Y-m-d') }}</span>
                                <span class="block font-mono text-xs tabular-nums text-zinc-400 dark:text-zinc-500">{{ $payment->created_at->format('H:i') }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-7 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="flex size-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-700/50">
                                        <flux:icon name="banknotes" class="size-7 text-zinc-400" />
                                    </div>
                                    <p class="font-semibold text-zinc-700 dark:text-zinc-300">No payments found</p>
                                    <p class="text-sm text-zinc-400 dark:text-zinc-500">Try adjusting your filters to see results.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    @if ($payments->hasPages())
        <div class="rounded-2xl border border-zinc-100 bg-white px-6 py-3.5 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-800">
            {{ $payments->links() }}
        </div>
    @endif

</div>
@endsection
