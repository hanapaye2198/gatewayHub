@extends('layouts.admin')

@section('content')
<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Payments</h1>
        <p class="mt-1 text-zinc-600 dark:text-zinc-400">Read-only list of all payments across merchants. Total platform revenue: {{ number_format($totalPlatformRevenue ?? 0, 2) }} (from posted fees).</p>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 [&_tbody_tr:hover]:bg-zinc-50 dark:[&_tbody_tr:hover]:bg-zinc-700/50 [&_tbody_tr]:border-b [&_tbody_tr]:border-zinc-200/60 [&_tbody_tr:last-child]:border-b-0 dark:[&_tbody_tr]:border-zinc-600/40 [&_td]:py-4 [&_th]:font-semibold [&_th]:text-zinc-900 dark:[&_th]:text-zinc-100">
        <flux:table>
            <flux:table.columns :sticky="true">
                <flux:table.cell variant="strong">Reference</flux:table.cell>
                <flux:table.cell variant="strong">Merchant</flux:table.cell>
                <flux:table.cell variant="strong">Gateway</flux:table.cell>
                <flux:table.cell variant="strong" align="end">Amount</flux:table.cell>
                <flux:table.cell variant="strong" align="end">Platform fee</flux:table.cell>
                <flux:table.cell variant="strong" align="end">Net</flux:table.cell>
                <flux:table.cell variant="strong">Status</flux:table.cell>
                <flux:table.cell variant="strong">Created</flux:table.cell>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($payments as $payment)
                    <flux:table.row wire:key="payment-{{ $payment->id }}">
                        <flux:table.cell>{{ $payment->reference_id }}</flux:table.cell>
                        <flux:table.cell>{{ $payment->user?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $payment->gateway?->name ?? $payment->gateway_code }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ $payment->platformFee ? number_format($payment->platformFee->fee_amount, 2) . ' ' . $payment->currency : '—' }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ $payment->platformFee ? number_format($payment->platformFee->net_amount, 2) . ' ' . $payment->currency : '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <x-status-badge :status="$payment->status" />
                        </flux:table.cell>
                        <flux:table.cell>{{ $payment->created_at->format('M j, Y g:i A') }}</flux:table.cell>
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
