<?php

use App\Models\Payment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component {
    #[Layout('layouts.app', ['title' => 'SurePay Settlement Logs'])]

    #[Computed]
    public function logRows()
    {
        $mid = auth()->user()?->merchant_id;
        if ($mid === null || $mid === '') {
            return collect();
        }

        $payments = Payment::query()
            ->where('merchant_id', (int) $mid)
            ->select(['id', 'reference_id', 'gateway_code', 'raw_response', 'created_at'])
            ->latest('created_at')
            ->limit(250)
            ->get();

        $rows = collect();

        foreach ($payments as $payment) {
            $raw = $payment->raw_response;
            if (! is_array($raw)) {
                continue;
            }

            $surepayLogs = $raw['surepay_sending_logs'] ?? [];
            if (is_array($surepayLogs)) {
                foreach ($surepayLogs as $log) {
                    if (! is_array($log)) {
                        continue;
                    }

                    $rows->push([
                        'payment_id' => $payment->id,
                        'reference_id' => $payment->reference_id,
                        'gateway' => $payment->gateway_code,
                        'type' => 'surepay_sending',
                        'status' => (string) ($log['status'] ?? 'queued'),
                        'stage' => (string) ($log['stage'] ?? 'unknown_stage'),
                        'message' => (string) ($log['error'] ?? ''),
                        'amount' => isset($log['amount']) ? (float) $log['amount'] : null,
                        'currency' => isset($log['currency']) ? (string) $log['currency'] : null,
                        'logged_at' => (string) ($log['logged_at'] ?? $payment->created_at?->toIso8601String()),
                    ]);
                }
            }

            $surepayErrors = $raw['surepay_wallet_errors'] ?? ($raw['tunnel_wallet_errors'] ?? []);
            if (is_array($surepayErrors)) {
                foreach ($surepayErrors as $error) {
                    if (! is_array($error)) {
                        continue;
                    }

                    $rows->push([
                        'payment_id' => $payment->id,
                        'reference_id' => $payment->reference_id,
                        'gateway' => $payment->gateway_code,
                        'type' => 'surepay_error',
                        'status' => 'failed',
                        'stage' => 'surepay_processing',
                        'message' => (string) ($error['message'] ?? 'Unknown SurePay error'),
                        'amount' => null,
                        'currency' => null,
                        'logged_at' => (string) ($error['logged_at'] ?? $payment->created_at?->toIso8601String()),
                    ]);
                }
            }
        }

        return $rows
            ->sortByDesc(fn (array $row) => strtotime($row['logged_at']))
            ->values();
    }

    #[Computed]
    public function summary(): array
    {
        $rows = $this->logRows;

        return [
            'total' => $rows->count(),
            'success' => $rows->where('status', 'success')->count(),
            'failed' => $rows->where('status', 'failed')->count(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('SurePay Settlement Logs') }}</h1>
        <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('View SurePay sending logs and SurePay processing failures for your payments.') }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Logs') }}</p>
            <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format((int) $this->summary['total']) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Successful Sends') }}</p>
            <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format((int) $this->summary['success']) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Failed Logs') }}</p>
            <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-red-700 dark:text-red-300">{{ number_format((int) $this->summary['failed']) }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 [&_tbody_tr:hover]:bg-zinc-50 dark:[&_tbody_tr:hover]:bg-zinc-700/50 [&_tbody_tr]:border-b [&_tbody_tr]:border-zinc-200/60 [&_tbody_tr:last-child]:border-b-0 dark:[&_tbody_tr]:border-zinc-600/40">
        <flux:table>
            <flux:table.columns>
                <flux:table.cell variant="strong">{{ __('Time') }}</flux:table.cell>
                <flux:table.cell variant="strong">{{ __('Type') }}</flux:table.cell>
                <flux:table.cell variant="strong">{{ __('Status') }}</flux:table.cell>
                <flux:table.cell variant="strong">{{ __('Reference') }}</flux:table.cell>
                <flux:table.cell variant="strong" align="end">{{ __('Amount') }}</flux:table.cell>
                <flux:table.cell variant="strong">{{ __('Details') }}</flux:table.cell>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->logRows as $row)
                    <flux:table.row>
                        <flux:table.cell>{{ \Illuminate\Support\Carbon::parse($row['logged_at'])->format('M j, Y g:i:s A') }}</flux:table.cell>
                        <flux:table.cell>{{ $row['type'] }}</flux:table.cell>
                        <flux:table.cell>
                            <x-status-badge :status="$row['status']" :label="strtoupper($row['status'])" />
                        </flux:table.cell>
                        <flux:table.cell>
                            <a href="{{ route('dashboard.payments.show', ['payment' => $row['payment_id']]) }}" class="text-zinc-700 underline decoration-dotted underline-offset-2 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-zinc-100" wire:navigate>
                                {{ $row['reference_id'] }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">
                            @if ($row['amount'] !== null && $row['currency'] !== null)
                                {{ number_format((float) $row['amount'], 2) }} {{ $row['currency'] }}
                            @else
                                -
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="space-y-1">
                                <div class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $row['stage'] }}</div>
                                @if ($row['message'] !== '')
                                    <div class="text-xs text-red-600 dark:text-red-400">{{ $row['message'] }}</div>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500 dark:text-zinc-400">{{ __('No settlement logs yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
