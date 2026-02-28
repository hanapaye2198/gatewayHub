@extends('layouts.admin')

@section('content')
<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Payments</h1>
        <p class="mt-1 text-zinc-600 dark:text-zinc-400">Read-only list of Coins-orchestrated payments across merchants. Total platform revenue: PHP {{ number_format($totalPlatformRevenue ?? 0, 2) }}.</p>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <form method="GET" action="{{ route('admin.payments.index') }}" class="grid gap-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7">
            <div>
                <label for="merchant_id" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Merchant</label>
                <select id="merchant_id" name="merchant_id" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
                    <option value="">All merchants</option>
                    @foreach ($merchants as $merchant)
                        <option value="{{ $merchant->id }}" @selected((string) $activeFilters['merchant_id'] === (string) $merchant->id)>
                            {{ $merchant->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="gateway_code" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Gateway</label>
                <select id="gateway_code" name="gateway_code" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
                    <option value="">All gateways</option>
                    @foreach ($gateways as $gateway)
                        <option value="{{ $gateway->code }}" @selected((string) $activeFilters['gateway_code'] === (string) $gateway->code)>
                            {{ $gateway->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Status</label>
                <select id="status" name="status" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected((string) $activeFilters['status'] === (string) $status)>
                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="reference" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Reference</label>
                <input id="reference" name="reference" type="text" value="{{ (string) ($activeFilters['reference'] ?? '') }}" placeholder="Search reference" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
            </div>
            <div>
                <label for="from_date" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">From date</label>
                <input id="from_date" name="from_date" type="date" value="{{ (string) ($activeFilters['from_date'] ?? '') }}" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
            </div>
            <div>
                <label for="to_date" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">To date</label>
                <input id="to_date" name="to_date" type="date" value="{{ (string) ($activeFilters['to_date'] ?? '') }}" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
            </div>
            <div class="flex items-end gap-2 xl:col-span-2">
                @php
                    $exportFilters = array_filter([
                        'merchant_id' => $activeFilters['merchant_id'] ?? null,
                        'gateway_code' => $activeFilters['gateway_code'] ?? null,
                        'status' => $activeFilters['status'] ?? null,
                        'reference' => $activeFilters['reference'] ?? null,
                        'from_date' => $activeFilters['from_date'] ?? null,
                        'to_date' => $activeFilters['to_date'] ?? null,
                    ], static fn ($value): bool => $value !== null && $value !== '');
                @endphp
                <flux:button type="submit" variant="primary">Apply Filters</flux:button>
                <a href="{{ route('admin.payments.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-zinc-300 px-3 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700">Clear</a>
                <a href="{{ route('admin.payments.export', $exportFilters) }}" class="inline-flex h-10 items-center justify-center rounded-md border border-zinc-300 px-3 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700">Export CSV</a>
            </div>
        </form>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Total transactions</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format((int) ($summary['total_transactions'] ?? 0)) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Paid collections</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">PHP {{ number_format((float) ($summary['paid_collections'] ?? 0), 2) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Pending count</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format((int) ($summary['pending_count'] ?? 0)) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Failed or refunded</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format((int) ($summary['failed_refunded_count'] ?? 0)) }}</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 [&_tbody_tr:hover]:bg-zinc-50 dark:[&_tbody_tr:hover]:bg-zinc-700/50 [&_tbody_tr]:border-b [&_tbody_tr]:border-zinc-200/60 [&_tbody_tr:last-child]:border-b-0 dark:[&_tbody_tr]:border-zinc-600/40 [&_td]:py-4 [&_th]:font-semibold [&_th]:text-zinc-900 dark:[&_th]:text-zinc-100">
        <flux:table>
            <flux:table.columns :sticky="true">
                <flux:table.cell variant="strong" class="w-[220px]">Reference</flux:table.cell>
                <flux:table.cell variant="strong" class="w-[180px]">Merchant</flux:table.cell>
                <flux:table.cell variant="strong" class="w-[140px]">Gateway</flux:table.cell>
                <flux:table.cell variant="strong" align="end" class="w-[140px]">Amount</flux:table.cell>
                <flux:table.cell variant="strong" align="end" class="w-[140px]">Platform fee</flux:table.cell>
                <flux:table.cell variant="strong" align="end" class="w-[140px]">Net</flux:table.cell>
                <flux:table.cell variant="strong" class="w-[130px] text-center">Status</flux:table.cell>
                <flux:table.cell variant="strong" align="end" class="w-[170px]">Created</flux:table.cell>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($payments as $payment)
                    <flux:table.row wire:key="payment-{{ $payment->id }}">
                        <flux:table.cell class="whitespace-nowrap">{{ $payment->reference_id }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $payment->user?->name ?? 'N/A' }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $payment->gateway?->name ?? $payment->gateway_code }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ $payment->platformFee ? number_format($payment->platformFee->fee_amount, 2) . ' ' . $payment->currency : 'N/A' }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ $payment->platformFee ? number_format($payment->platformFee->net_amount, 2) . ' ' . $payment->currency : 'N/A' }}</flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$payment->status" />
                        </flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap font-mono tabular-nums">{{ $payment->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-8 text-center text-zinc-500 dark:text-zinc-400">No payments yet.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    @if ($payments->hasPages())
        <div class="rounded-xl border border-zinc-200 bg-white px-4 py-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            {{ $payments->links() }}
        </div>
    @endif
</div>
@endsection

