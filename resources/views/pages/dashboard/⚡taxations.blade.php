<?php

use App\Models\PlatformFee;
use App\Models\Payment;
use App\Models\WalletTransaction;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component {
    #[Layout('layouts.app', ['title' => 'Taxations'])]

    #[Computed]
    public function totalGross(): float
    {
        return (float) Payment::query()
            ->where('user_id', auth()->id())
            ->whereHas('walletTransactions', function ($query): void {
                $query->whereIn('entry_type', [
                    WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT,
                    WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT_DIRECT,
                ]);
            })
            ->sum('amount');
    }

    #[Computed]
    public function totalTax(): float
    {
        return (float) PlatformFee::query()
            ->where('merchant_id', auth()->id())
            ->sum('fee_amount');
    }

    #[Computed]
    public function totalNetSettled(): float
    {
        return (float) WalletTransaction::query()
            ->whereIn('entry_type', [
                WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT,
                WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT_DIRECT,
            ])
            ->whereHas('payment', fn ($query) => $query->where('user_id', auth()->id()))
            ->sum('amount');
    }

    #[Computed]
    public function taxationRows()
    {
        return PlatformFee::query()
            ->where('merchant_id', auth()->id())
            ->whereHas('payment.walletTransactions', function ($query): void {
                $query->whereIn('entry_type', [
                    WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT,
                    WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT_DIRECT,
                ]);
            })
            ->with('payment.walletTransactions')
            ->latest('created_at')
            ->get();
    }

    #[Computed]
    public function recentTaxWalletEntries()
    {
        return WalletTransaction::query()
            ->where('entry_type', 'surepay_tax_collected')
            ->whereHas('payment', fn ($query) => $query->where('user_id', auth()->id()))
            ->with('payment')
            ->latest('created_at')
            ->limit(8)
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Taxations') }}</h1>
        <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Track gross amount, deducted tax, and net settlement per payment.') }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Gross Processed') }}</p>
            <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($this->totalGross, 2) }} PHP</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Tax Deducted') }}</p>
            <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($this->totalTax, 2) }} PHP</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Net Settled') }}</p>
            <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($this->totalNetSettled, 2) }} PHP</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 [&_tbody_tr:hover]:bg-zinc-50 dark:[&_tbody_tr:hover]:bg-zinc-700/50 [&_tbody_tr]:border-b [&_tbody_tr]:border-zinc-200/60 [&_tbody_tr:last-child]:border-b-0 dark:[&_tbody_tr]:border-zinc-600/40">
        <h2 class="mb-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Tax Per Payment') }}</h2>
        <flux:table>
            <flux:table.columns>
                <flux:table.cell variant="strong">{{ __('Reference') }}</flux:table.cell>
                <flux:table.cell variant="strong" align="end">{{ __('Gross') }}</flux:table.cell>
                <flux:table.cell variant="strong" align="end">{{ __('Tax') }}</flux:table.cell>
                <flux:table.cell variant="strong" align="end">{{ __('Net') }}</flux:table.cell>
                <flux:table.cell variant="strong">{{ __('Date') }}</flux:table.cell>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->taxationRows as $row)
                    <flux:table.row>
                        <flux:table.cell>{{ $row->payment?->reference_id ?? 'N/A' }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ number_format($row->gross_amount, 2) }} {{ $row->payment?->currency ?? 'PHP' }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ number_format($row->fee_amount, 2) }} {{ $row->payment?->currency ?? 'PHP' }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ number_format($row->net_amount, 2) }} {{ $row->payment?->currency ?? 'PHP' }}</flux:table.cell>
                        <flux:table.cell>{{ $row->created_at->format('M j, Y g:i A') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500 dark:text-zinc-400">{{ __('No taxation records yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 [&_tbody_tr:hover]:bg-zinc-50 dark:[&_tbody_tr:hover]:bg-zinc-700/50 [&_tbody_tr]:border-b [&_tbody_tr]:border-zinc-200/60 [&_tbody_tr:last-child]:border-b-0 dark:[&_tbody_tr]:border-zinc-600/40">
        <h2 class="mb-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Recent Tax Wallet Entries') }}</h2>
        <flux:table>
            <flux:table.columns>
                <flux:table.cell variant="strong">{{ __('Reference') }}</flux:table.cell>
                <flux:table.cell variant="strong" align="end">{{ __('Amount') }}</flux:table.cell>
                <flux:table.cell variant="strong">{{ __('Entry') }}</flux:table.cell>
                <flux:table.cell variant="strong">{{ __('Date') }}</flux:table.cell>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->recentTaxWalletEntries as $entry)
                    <flux:table.row>
                        <flux:table.cell>{{ $entry->payment?->reference_id ?? 'N/A' }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ number_format($entry->amount, 2) }} {{ $entry->currency }}</flux:table.cell>
                        <flux:table.cell>{{ $entry->entry_type }}</flux:table.cell>
                        <flux:table.cell>{{ $entry->created_at->format('M j, Y g:i A') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500 dark:text-zinc-400">{{ __('No wallet tax entries yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
