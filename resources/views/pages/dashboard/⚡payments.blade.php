<?php

use App\Models\Gateway;
use App\Models\Payment;
use App\Services\QrCodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component {
    #[Layout('layouts.app', ['title' => 'Payments'])]

    public ?string $selectedPaymentId = null;

    public bool $showPaymentDetail = false;

    public ?string $gatewayCode = null;

    public ?string $status = null;

    public ?string $reference = null;

    public ?string $fromDate = null;

    public ?string $toDate = null;

    public function mount(): void
    {
        $this->gatewayCode = $this->sanitizeString(request()->query('gateway_code'));
        $this->status = $this->sanitizeStatus(request()->query('status'));
        $this->reference = $this->sanitizeString(request()->query('reference'));
        $this->fromDate = $this->normalizeDate(request()->query('from_date'));
        $this->toDate = $this->normalizeDate(request()->query('to_date'));

        if ($this->fromDate !== null && $this->toDate !== null && $this->fromDate > $this->toDate) {
            $this->toDate = null;
        }
    }

    #[Computed]
    public function payments()
    {
        return $this->buildFilteredPaymentsQuery()
            ->with(['gateway', 'platformFee'])
            ->latest('created_at')
            ->get();
    }

    #[Computed]
    public function summary(): array
    {
        $query = $this->buildFilteredPaymentsQuery();

        return [
            'total_transactions' => (clone $query)->count(),
            'paid_collections' => (float) (clone $query)
                ->where('status', 'paid')
                ->sum('amount'),
            'pending_count' => (clone $query)
                ->where('status', 'pending')
                ->count(),
            'failed_refunded_count' => (clone $query)
                ->whereIn('status', ['failed', 'refunded', 'failed_after_paid'])
                ->count(),
        ];
    }

    #[Computed]
    public function gatewayOptions()
    {
        $merchantId = auth()->id();
        if (! is_int($merchantId)) {
            return collect();
        }

        $codes = Payment::query()
            ->where('user_id', $merchantId)
            ->select('gateway_code')
            ->distinct()
            ->pluck('gateway_code');

        return Gateway::query()
            ->whereIn('code', $codes)
            ->orderBy('name')
            ->get(['code', 'name']);
    }

    #[Computed]
    public function exportUrl(): string
    {
        return route('dashboard.payments.export', $this->activeFilterParams());
    }

    #[Computed]
    public function selectedPayment(): ?Payment
    {
        if ($this->selectedPaymentId === null) {
            return null;
        }

        $payment = Payment::query()
            ->where('user_id', auth()->id())
            ->with('gateway')
            ->find($this->selectedPaymentId);

        return $payment instanceof Payment ? $payment : null;
    }

    public function selectPayment(string $id): void
    {
        $this->selectedPaymentId = $id;
        $this->showPaymentDetail = true;
    }

    public function closeDetail(): void
    {
        $this->showPaymentDetail = false;
        $this->selectedPaymentId = null;
    }

    public function getQrImageSrc(Payment $payment): ?string
    {
        $qrData = $payment->getQrData();
        if ($qrData === null) {
            return null;
        }

        if ($qrData['type'] === 'image') {
            return $qrData['value'];
        }

        return app(QrCodeGenerator::class)->toDataUri($qrData['value']);
    }

    /**
     * @return Builder<Payment>
     */
    private function buildFilteredPaymentsQuery(): Builder
    {
        $merchantId = auth()->id();
        if (! is_int($merchantId)) {
            return Payment::query()->whereRaw('1 = 0');
        }

        return Payment::query()
            ->where('user_id', $merchantId)
            ->when($this->gatewayCode !== null, function (Builder $query): void {
                $query->where('gateway_code', $this->gatewayCode);
            })
            ->when($this->status !== null, function (Builder $query): void {
                $query->where('status', $this->status);
            })
            ->when($this->reference !== null, function (Builder $query): void {
                $reference = $this->reference;
                if ($reference === null) {
                    return;
                }

                $query->where(function (Builder $referenceQuery) use ($reference): void {
                    $referenceQuery
                        ->where('reference_id', 'like', '%'.$reference.'%')
                        ->orWhere('provider_reference', 'like', '%'.$reference.'%');
                });
            })
            ->when($this->fromDate !== null, function (Builder $query): void {
                $query->whereDate('created_at', '>=', $this->fromDate);
            })
            ->when($this->toDate !== null, function (Builder $query): void {
                $query->whereDate('created_at', '<=', $this->toDate);
            });
    }

    /**
     * @return array<string, string>
     */
    private function activeFilterParams(): array
    {
        $filters = [
            'gateway_code' => $this->gatewayCode,
            'status' => $this->status,
            'reference' => $this->reference,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
        ];

        return array_filter($filters, static function ($value): bool {
            return is_string($value) && $value !== '';
        });
    }

    private function sanitizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function sanitizeStatus(mixed $value): ?string
    {
        $status = $this->sanitizeString($value);
        if ($status === null) {
            return null;
        }

        return in_array($status, ['pending', 'paid', 'failed', 'refunded', 'failed_after_paid'], true)
            ? $status
            : null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', trim($value))->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}; ?>

<div wire:poll.12s class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Payments') }}</h1>
                <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Track payments collected through Coins dynamic QR.') }}</p>
            </div>
            <flux:button variant="primary" :href="route('dashboard.payments.create')" wire:navigate icon="plus">
                {{ __('Create Payment') }}
            </flux:button>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <form method="GET" action="{{ route('dashboard') }}" class="space-y-4">
            <div class="rounded-lg border border-zinc-200 bg-zinc-50/70 p-3 dark:border-zinc-700 dark:bg-zinc-900/40">
                <label for="reference_filter" class="block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Search') }}</label>
                <input id="reference_filter" name="reference" type="text" value="{{ $reference ?? '' }}" placeholder="{{ __('Search reference') }}" class="mt-2 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <div>
                    <label for="gateway_code" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Gateway') }}</label>
                    <select id="gateway_code" name="gateway_code" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
                        <option value="">{{ __('All gateways') }}</option>
                        @foreach ($this->gatewayOptions as $gatewayOption)
                            <option value="{{ $gatewayOption->code }}" @selected($gatewayCode === $gatewayOption->code)>
                                {{ $gatewayOption->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status_filter" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</label>
                    <select id="status_filter" name="status" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach (['pending', 'paid', 'failed', 'refunded', 'failed_after_paid'] as $statusOption)
                            <option value="{{ $statusOption }}" @selected($status === $statusOption)>
                                {{ ucfirst(str_replace('_', ' ', $statusOption)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2 xl:col-span-2">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Date range') }}</label>
                    <div class="grid grid-cols-2 gap-3">
                        <input id="from_date" name="from_date" type="date" value="{{ $fromDate ?? '' }}" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100" aria-label="{{ __('From date') }}">
                        <input id="to_date" name="to_date" type="date" value="{{ $toDate ?? '' }}" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100" aria-label="{{ __('To date') }}">
                    </div>
                </div>

                <div class="md:col-span-2 xl:col-span-1 flex flex-wrap items-end gap-2 xl:justify-end">
                    <flux:button type="submit" variant="primary" class="whitespace-nowrap">{{ __('Apply Filters') }}</flux:button>
                    <a href="{{ route('dashboard') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-zinc-300 px-3 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700 whitespace-nowrap">{{ __('Clear') }}</a>
                    <a href="{{ $this->exportUrl }}" class="inline-flex h-10 items-center justify-center rounded-md border border-zinc-300 px-3 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700 whitespace-nowrap">{{ __('Export CSV') }}</a>
                </div>
            </div>
        </form>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Total transactions') }}</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format((int) $this->summary['total_transactions']) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Paid collections') }}</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">PHP {{ number_format((float) $this->summary['paid_collections'], 2) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Pending count') }}</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format((int) $this->summary['pending_count']) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Failed or refunded') }}</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format((int) $this->summary['failed_refunded_count']) }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 [&_tbody_tr:hover]:bg-zinc-50 dark:[&_tbody_tr:hover]:bg-zinc-700/50 [&_tbody_tr]:border-b [&_tbody_tr]:border-zinc-200/60 [&_tbody_tr:last-child]:border-b-0 dark:[&_tbody_tr]:border-zinc-600/40 [&_td]:py-4 [&_th]:font-semibold [&_th]:text-zinc-900 dark:[&_th]:text-zinc-100">
        <flux:table>
            <flux:table.columns :sticky="true">
                <flux:table.cell variant="strong" class="w-[220px]">{{ __('Reference') }}</flux:table.cell>
                <flux:table.cell variant="strong" class="w-[140px]">{{ __('Gateway') }}</flux:table.cell>
                <flux:table.cell variant="strong" align="end" class="w-[140px]">{{ __('Amount') }}</flux:table.cell>
                <flux:table.cell variant="strong" align="end" class="w-[140px]">{{ __('Platform fee') }}</flux:table.cell>
                <flux:table.cell variant="strong" align="end" class="w-[140px]">{{ __('Net') }}</flux:table.cell>
                <flux:table.cell variant="strong" class="w-[130px] text-center">{{ __('Status') }}</flux:table.cell>
                <flux:table.cell variant="strong" align="end" class="w-[170px]">{{ __('Created') }}</flux:table.cell>
                <flux:table.cell variant="strong" class="w-0"></flux:table.cell>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->payments as $payment)
                    <flux:table.row
                        wire:key="payment-{{ $payment->id }}"
                        wire:click="selectPayment('{{ $payment->id }}')"
                        class="cursor-pointer"
                    >
                        <flux:table.cell class="whitespace-nowrap">{{ $payment->reference_id }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $payment->gateway?->name ?? ucfirst($payment->gateway_code) }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ $payment->platformFee ? '-' . number_format($payment->platformFee->fee_amount, 2) . ' ' . $payment->currency : 'N/A' }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ $payment->platformFee ? number_format($payment->platformFee->net_amount, 2) . ' ' . $payment->currency : 'N/A' }}</flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$payment->status" />
                        </flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap font-mono tabular-nums">{{ $payment->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button variant="ghost" size="sm" :href="route('dashboard.payments.show', $payment)" wire:navigate>
                                {{ __('View') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row wire:key="payment-empty">
                        <flux:table.cell colspan="8" class="text-center text-zinc-500 dark:text-zinc-400 py-8">
                            {{ __('No payments yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    @if ($this->selectedPayment)
        <flux:modal wire:model="showPaymentDetail" wire:close="closeDetail" class="max-w-md" dismissible>
            <div class="space-y-4">
            <flux:heading size="lg">{{ $this->selectedPayment->reference_id }}</flux:heading>
            <flux:subheading class="mt-1 block text-zinc-600 dark:text-zinc-400">
                {{ number_format($this->selectedPayment->amount, 2) }} {{ $this->selectedPayment->currency }}
                | {{ $this->selectedPayment->gateway?->name ?? ucfirst($this->selectedPayment->gateway_code) }}
            </flux:subheading>
            @if ($this->selectedPayment->status === 'paid' && $this->selectedPayment->platform_fee !== null)
                <flux:text class="mt-2 block text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Gross') }}: {{ number_format($this->selectedPayment->amount, 2) }} | {{ __('Platform fee') }}: -{{ number_format($this->selectedPayment->platform_fee, 2) }} | {{ __('Net') }}: {{ number_format($this->selectedPayment->net_amount, 2) }} {{ $this->selectedPayment->currency }}
                </flux:text>
            @endif

            <div class="mt-6 min-h-[220px] flex flex-col items-center justify-center">
                @if ($this->selectedPayment->status === 'pending')
                    @php $qrSrc = $this->getQrImageSrc($this->selectedPayment); @endphp
                    @if ($qrSrc)
                        <div class="flex flex-col items-center gap-4">
                            <img src="{{ $qrSrc }}" alt="QR Code" class="size-48 object-contain" />
                            <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('Scan with GCash, Maya, Coins wallet, or other QRPH-compatible apps.') }}</flux:text>
                        </div>
                    @else
                        <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('QR code unavailable.') }}</flux:text>
                    @endif
                @elseif ($this->selectedPayment->status === 'paid')
                    <div class="flex flex-col items-center gap-3">
                        <div class="flex size-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                            <flux:icon name="check" class="size-8 text-green-600 dark:text-green-400" />
                        </div>
                        <flux:text class="font-medium text-green-700 dark:text-green-300">{{ __('Paid') }}</flux:text>
                    </div>
                @elseif (in_array($this->selectedPayment->status, ['failed', 'expired'], true))
                    <div class="flex flex-col items-center gap-3">
                        <div class="flex size-16 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                            <flux:icon name="x-circle" class="size-8 text-red-600 dark:text-red-400" />
                        </div>
                        <flux:text class="font-medium text-red-700 dark:text-red-300">
                            {{ $this->selectedPayment->status === 'expired' ? __('Expired') : __('Failed') }}
                        </flux:text>
                    </div>
                @else
                    <x-status-badge :status="$this->selectedPayment->status" />
                @endif
            </div>
            </div>
        </flux:modal>
    @endif
</div>

