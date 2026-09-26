<?php

use App\Enums\PaymentDisplayStatus;
use App\Models\Gateway;
use App\Models\Payment;
use App\Services\PaymentStatusSyncService;
use App\Services\Payments\MerchantPaymentQuery;
use App\Services\Payments\PaymentDisplayStatusResolver;
use App\Services\QrCodeGenerator;
use App\Support\MerchantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

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
            ->with([
                'gateway',
                'platformFee',
                'webhookEvents' => static fn (HasMany $query) => $query->orderByDesc('received_at'),
            ])
            ->latest('created_at')
            ->paginate(25);
    }

    #[Computed]
    public function summary(): array
    {
        return $this->displayStatusResolver()->summarize($this->buildFilteredPaymentsQuery());
    }

    #[Computed]
    public function shouldPollPendingSync(): bool
    {
        return $this->summary['pending_count'] > 0;
    }

    #[Computed]
    public function gatewayOptions()
    {
        $merchantId = $this->scopedMerchantId();
        if ($merchantId === null) {
            return collect();
        }

        $codes = Payment::query()
            ->where('merchant_id', (int) $merchantId)
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
    public function reportUrl(): string
    {
        return route('dashboard.payments.report', $this->activeFilterParams());
    }

    #[Computed]
    public function selectedPayment(): ?Payment
    {
        if ($this->selectedPaymentId === null) {
            return null;
        }

        $merchantId = $this->scopedMerchantId();
        if ($merchantId === null) {
            return null;
        }

        $payment = Payment::query()
            ->where('merchant_id', (int) $merchantId)
            ->with([
                'gateway',
                'webhookEvents' => static fn (HasMany $query) => $query->orderByDesc('received_at'),
            ])
            ->find($this->selectedPaymentId);

        return $payment instanceof Payment ? $payment : null;
    }

    public function selectPayment(string $id, PaymentStatusSyncService $paymentStatusSyncService): void
    {
        $this->selectedPaymentId = $id;
        $this->showPaymentDetail = true;

        $payment = $this->selectedPayment;
        if (! $payment instanceof Payment || $payment->getQrData() !== null) {
            return;
        }

        $paymentStatusSyncService->syncPendingPayment($payment);
        unset($this->selectedPayment);
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

    public function displayStatus(Payment $payment): PaymentDisplayStatus
    {
        return $this->displayStatusResolver()->resolve($payment);
    }

    private function displayStatusResolver(): PaymentDisplayStatusResolver
    {
        return app(PaymentDisplayStatusResolver::class);
    }

    /**
     * @return Builder<Payment>
     */
    private function buildFilteredPaymentsQuery(): Builder
    {
        $merchantId = $this->scopedMerchantId();
        if ($merchantId === null) {
            return Payment::query()->whereRaw('1 = 0');
        }

        return app(MerchantPaymentQuery::class)->forMerchant((int) $merchantId, [
            'gateway_code' => $this->gatewayCode,
            'status' => $this->status,
            'reference' => $this->reference,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
        ]);
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

        return in_array($status, array_map(
            static fn (PaymentDisplayStatus $displayStatus): string => $displayStatus->value,
            PaymentDisplayStatus::filterOptions()
        ), true)
            ? $status
            : null;
    }

    private function scopedMerchantId(): ?int
    {
        return app(MerchantContext::class)->id();
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

    /**
     * Reconcile pending Coins-orchestrated payments from Coins get_qr_code (fallback when webhook is delayed or fails).
     */
    public function syncPendingFromProvider(PaymentStatusSyncService $paymentStatusSyncService): void
    {
        if (auth()->user()?->isPlatformOperator()) {
            return;
        }

        $merchantId = $this->scopedMerchantId();
        if ($merchantId === null) {
            return;
        }

        $paymentStatusSyncService->syncPendingPaymentsForMerchant((int) $merchantId, 10);
    }
}; ?>

<div @if ($this->shouldPollPendingSync) wire:poll.12s="syncPendingFromProvider" @endif class="flex h-full w-full flex-1 flex-col gap-6">
    {{-- Header Section with Stats Overview --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-white to-zinc-50/50 p-6 shadow-sm dark:from-zinc-800 dark:to-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
        <div class="absolute -right-20 -top-20 size-40 rounded-full bg-gradient-to-br from-blue-500/5 to-purple-500/5 blur-3xl"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Payments') }}</h1>
                <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Track and manage all payment transactions from your gateways.') }}</p>
            </div>
            @unless (auth()->user()?->isPlatformOperator())
                <flux:button variant="primary" :href="route('dashboard.payments.create')" wire:navigate icon="plus" class="shadow-sm">
                    {{ __('Create Payment') }}
                </flux:button>
            @endunless
        </div>
    </div>

    {{-- Enhanced Filter Section --}}
    <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
        <div class="border-b border-zinc-200 px-5 py-3 dark:border-zinc-700">
            <div class="flex items-center gap-2">
                <flux:icon name="funnel" class="size-4 text-zinc-500" />
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Filter Transactions') }}</span>
            </div>
        </div>
        <div class="p-5">
            <form method="GET" action="{{ route('dashboard') }}" class="space-y-5">
                {{-- Search Row --}}
                <div class="rounded-lg bg-zinc-50/70 p-4 dark:bg-zinc-900/40">
                    <label for="reference_filter" class="mb-2 block text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Search by Reference') }}</label>
                    <div class="relative">
                        <flux:icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                        <input id="reference_filter" name="reference" type="text" value="{{ $reference ?? '' }}" placeholder="{{ __('Reference ID or Provider Reference...') }}" class="w-full rounded-lg border border-zinc-300 bg-white pl-9 pr-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
                    </div>
                </div>

                {{-- Filters Grid --}}
                <div class="grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label for="gateway_code" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Gateway') }}</label>
                        <select id="gateway_code" name="gateway_code" class="h-10 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
                            <option value="">{{ __('All gateways') }}</option>
                            @foreach ($this->gatewayOptions as $gatewayOption)
                                <option value="{{ $gatewayOption->code }}" @selected($gatewayCode === $gatewayOption->code)>
                                    {{ $gatewayOption->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="status_filter" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</label>
                        <select id="status_filter" name="status" class="h-10 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
                            <option value="">{{ __('All statuses') }}</option>
                            @foreach (PaymentDisplayStatus::filterOptions() as $statusOption)
                                <option value="{{ $statusOption->value }}" @selected($status === $statusOption->value)>
                                    {{ $statusOption->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="from_date" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('From') }}</label>
                        <div class="relative">
                            <flux:icon name="calendar" class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                            <input id="from_date" name="from_date" type="date" value="{{ $fromDate ?? '' }}" class="h-10 w-full rounded-lg border border-zinc-300 bg-white ps-9 pe-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
                        </div>
                    </div>

                    <div>
                        <label for="to_date" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('To') }}</label>
                        <div class="relative">
                            <flux:icon name="calendar" class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                            <input id="to_date" name="to_date" type="date" value="{{ $toDate ?? '' }}" class="h-10 w-full rounded-lg border border-zinc-300 bg-white ps-9 pe-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:button type="submit" variant="primary" icon="funnel" class="shadow-sm">
                            {{ __('Apply') }}
                        </flux:button>
                        <flux:button variant="ghost" icon="x-mark" :href="route('dashboard')">
                            {{ __('Clear') }}
                        </flux:button>
                    </div>
                    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                        <flux:button variant="outline" icon="document-text" :href="$this->reportUrl" class="w-full sm:w-auto">
                            {{ __('Download Payment Report') }}
                        </flux:button>
                        <flux:button variant="outline" icon="arrow-down-tray" :href="$this->exportUrl" class="w-full sm:w-auto">
                            {{ __('Download Transactions') }}
                        </flux:button>
                    </div>
                </div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Payment Report and Download Transactions include every matching payment for these filters, not only this page.') }}
                </p>
            </form>
        </div>
    </div>

    {{-- Enhanced Stats Cards --}}
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm transition-all hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/50">
            <div class="absolute -right-8 -top-8 size-20 rounded-full bg-gradient-to-br from-blue-500/10 to-blue-500/5 blur-2xl"></div>
            <div class="relative flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total Transactions') }}</p>
                    <p class="mt-1 text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format((int) $this->summary['total_transactions']) }}</p>
                </div>
                <div class="flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-blue-100 to-blue-50 dark:from-blue-900/30 dark:to-blue-800/20">
                    <flux:icon name="document-text" class="size-6 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
        </div>

        <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm transition-all hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/50">
            <div class="absolute -right-8 -top-8 size-20 rounded-full bg-gradient-to-br from-emerald-500/10 to-emerald-500/5 blur-2xl"></div>
            <div class="relative flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Paid Collections') }}</p>
                    <p class="mt-1 text-3xl font-bold text-zinc-900 dark:text-zinc-100">PHP {{ number_format((float) $this->summary['paid_collections'], 2) }}</p>
                </div>
                <div class="flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-100 to-emerald-50 dark:from-emerald-900/30 dark:to-emerald-800/20">
                    <flux:icon name="banknotes" class="size-6 text-emerald-600 dark:text-emerald-400" />
                </div>
            </div>
        </div>

        <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm transition-all hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/50">
            <div class="absolute -right-8 -top-8 size-20 rounded-full bg-gradient-to-br from-amber-500/10 to-amber-500/5 blur-2xl"></div>
            <div class="relative flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Pending') }}</p>
                    <p class="mt-1 text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format((int) $this->summary['pending_count']) }}</p>
                </div>
                <div class="flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-amber-100 to-amber-50 dark:from-amber-900/30 dark:to-amber-800/20">
                    <flux:icon name="clock" class="size-6 text-amber-600 dark:text-amber-400" />
                </div>
            </div>
        </div>

        <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm transition-all hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/50">
            <div class="absolute -right-8 -top-8 size-20 rounded-full bg-gradient-to-br from-zinc-500/10 to-zinc-500/5 blur-2xl"></div>
            <div class="relative flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Expired') }}</p>
                    <p class="mt-1 text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format((int) $this->summary['expired_count']) }}</p>
                </div>
                <div class="flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-zinc-100 to-zinc-50 dark:from-zinc-700/40 dark:to-zinc-800/20">
                    <flux:icon name="clock" class="size-6 text-zinc-600 dark:text-zinc-400" />
                </div>
            </div>
        </div>

        <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm transition-all hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/50">
            <div class="absolute -right-8 -top-8 size-20 rounded-full bg-gradient-to-br from-rose-500/10 to-rose-500/5 blur-2xl"></div>
            <div class="relative flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Failed') }}</p>
                    <p class="mt-1 text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format((int) $this->summary['failed_count']) }}</p>
                </div>
                <div class="flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-rose-100 to-rose-50 dark:from-rose-900/30 dark:to-rose-800/20">
                    <flux:icon name="x-circle" class="size-6 text-rose-600 dark:text-rose-400" />
                </div>
            </div>
        </div>

        <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm transition-all hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/50">
            <div class="absolute -right-8 -top-8 size-20 rounded-full bg-gradient-to-br from-orange-500/10 to-orange-500/5 blur-2xl"></div>
            <div class="relative flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Provisioning Failed') }}</p>
                    <p class="mt-1 text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format((int) $this->summary['provisioning_failed_count']) }}</p>
                </div>
                <div class="flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-orange-100 to-orange-50 dark:from-orange-900/30 dark:to-orange-800/20">
                    <flux:icon name="exclamation-triangle" class="size-6 text-orange-600 dark:text-orange-400" />
                </div>
            </div>
        </div>
    </div>

    {{-- Enhanced Transactions Table --}}
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
        <div class="border-b border-zinc-200 px-5 py-3 dark:border-zinc-700">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <flux:icon name="table-cells" class="size-4 text-zinc-500" />
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Transaction History') }}</span>
                </div>
                @if ($this->shouldPollPendingSync)
                    <span class="text-xs text-zinc-400">{{ __('Auto-refreshes every 12 seconds while payments are pending') }}</span>
                @endif
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Reference</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Gateway</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Transaction Amount</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Convenience Fee</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Total</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Status</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Created</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-transparent">
                    @forelse ($this->payments as $payment)
                        <tr wire:key="payment-{{ $payment->id }}" class="group transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="whitespace-nowrap px-5 py-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                <div class="flex items-center gap-2">
                                    <flux:icon name="document" class="size-4 text-zinc-400" />
                                    {{ $payment->reference_id }}
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-zinc-600 dark:text-zinc-300">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs dark:bg-zinc-700">
                                    {{ $payment->gateway?->name ?? ucfirst($payment->gateway_code) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-right font-mono text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ number_format($payment->amountIncludingPlatformFee(), 2) }} {{ $payment->currency }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-right font-mono text-sm text-zinc-500 dark:text-zinc-400">
                                @if ($payment->usesAdditivePricing())
                                    {{ number_format((float) $payment->convenience_fee, 2) }} {{ $payment->currency }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-right font-mono text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                @if ($payment->usesAdditivePricing())
                                    {{ number_format((float) $payment->customer_total, 2) }} {{ $payment->currency }}
                                @elseif ($payment->platformFee)
                                    {{ number_format($payment->platformFee->net_amount, 2) }} {{ $payment->currency }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-center">
                                @php $rowDisplayStatus = $this->displayStatus($payment); @endphp
                                <x-status-badge :status="$rowDisplayStatus->value" :label="$rowDisplayStatus->label()" />
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-right font-mono text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $payment->created_at->format('Y-m-d H:i') }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="selectPayment('{{ $payment->id }}')" class="rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-300">
                                        <flux:icon name="eye" class="size-4" />
                                    </button>
                                    <flux:button variant="ghost" size="sm" :href="route('dashboard.payments.show', $payment)" wire:navigate icon="arrow-top-right-on-square">
                                        {{ __('View') }}
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-700/50">
                                        <flux:icon name="document-text" class="size-8 text-zinc-400" />
                                    </div>
                                    <p class="text-zinc-500 dark:text-zinc-400">{{ __('No payments found.') }}</p>
                                    <flux:button variant="primary" :href="route('dashboard.payments.create')" wire:navigate size="sm" icon="plus">
                                        {{ __('Create your first payment') }}
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->payments->hasPages())
            <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">
                {{ $this->payments->links() }}
            </div>
        @endif
    </div>

    {{-- Enhanced Payment Detail Modal --}}
    @if ($this->selectedPayment)
        <flux:modal wire:model="showPaymentDetail" wire:close="closeDetail" class="max-w-md" dismissible>
            <div class="space-y-5">
                <div class="flex items-start justify-between">
                    <div>
                        <flux:heading size="lg" class="flex items-center gap-2">
                            <flux:icon name="document" class="size-5 text-zinc-500" />
                            {{ $this->selectedPayment->reference_id }}
                        </flux:heading>
                        <flux:subheading class="mt-1 text-zinc-600 dark:text-zinc-400">
                            {{ number_format($this->selectedPayment->usesAdditivePricing() ? (float) $this->selectedPayment->customer_total : $this->selectedPayment->amountIncludingPlatformFee(), 2) }} {{ $this->selectedPayment->currency }}
                            • {{ $this->selectedPayment->gateway?->name ?? ucfirst($this->selectedPayment->gateway_code) }}
                        </flux:subheading>
                    </div>
                    @php $modalDisplayStatus = $this->displayStatus($this->selectedPayment); @endphp
                    <x-status-badge :status="$modalDisplayStatus->value" :label="$modalDisplayStatus->label()" class="px-3 py-1" />
                </div>

                @include('partials.payment-amount-breakdown', ['payment' => $this->selectedPayment])

                <div class="min-h-[220px] flex flex-col items-center justify-center rounded-xl bg-zinc-50 p-6 dark:bg-zinc-900/40">
                    @if ($this->selectedPayment->status === 'pending')
                        @php $qrSrc = $this->getQrImageSrc($this->selectedPayment); @endphp
                        @if ($qrSrc)
                            <div class="flex flex-col items-center gap-4">
                                <div class="rounded-2xl bg-white p-3 shadow-md dark:bg-zinc-800">
                                    <img src="{{ $qrSrc }}" alt="QR Code" class="size-48 object-contain" />
                                </div>
                                <div class="text-center">
                                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Scan to Pay') }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">{{ __('Use GCash, Maya, Coins, or any QRPH-compatible app') }}</p>
                                </div>
                            </div>
                        @else
                            <div class="flex flex-col items-center gap-3">
                                <div class="flex size-16 items-center justify-center rounded-full bg-zinc-200 dark:bg-zinc-700">
                                    <flux:icon name="qr-code" class="size-8 text-zinc-500" />
                                </div>
                                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('QR code unavailable.') }}</flux:text>
                            </div>
                        @endif
                    @elseif ($this->selectedPayment->status === 'paid')
                        <div class="flex flex-col items-center gap-3">
                            <div class="flex size-20 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                                <flux:icon name="check" class="size-10 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <flux:text class="font-semibold text-emerald-700 dark:text-emerald-300">{{ __('Payment Successful') }}</flux:text>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('This transaction has been completed.') }}</flux:text>
                        </div>
                    @elseif ($modalDisplayStatus === \App\Enums\PaymentDisplayStatus::Expired)
                        <div class="flex flex-col items-center gap-3 text-center">
                            <div class="flex size-20 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-700/50">
                                <flux:icon name="clock" class="size-10 text-zinc-600 dark:text-zinc-300" />
                            </div>
                            <flux:text class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $modalDisplayStatus->label() }}</flux:text>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $modalDisplayStatus->explanation() }}</flux:text>
                            <flux:text class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $modalDisplayStatus->recommendedAction() }}</flux:text>
                        </div>
                    @elseif ($modalDisplayStatus === \App\Enums\PaymentDisplayStatus::Failed)
                        <div class="flex flex-col items-center gap-3 text-center">
                            <div class="flex size-20 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-900/30">
                                <flux:icon name="x-circle" class="size-10 text-rose-600 dark:text-rose-400" />
                            </div>
                            <flux:text class="font-semibold text-rose-700 dark:text-rose-300">{{ $modalDisplayStatus->label() }}</flux:text>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $modalDisplayStatus->explanation() }}</flux:text>
                        </div>
                    @elseif ($this->selectedPayment->status === 'failed_after_paid')
                        <div class="flex flex-col items-center gap-3">
                            <div class="flex size-20 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900/30">
                                <flux:icon name="exclamation-triangle" class="size-10 text-orange-600 dark:text-orange-400" />
                            </div>
                            <flux:text class="font-semibold text-orange-700 dark:text-orange-300">{{ __('Failed After Paid') }}</flux:text>
                        </div>
                    @elseif ($this->selectedPayment->status === 'refunded')
                        <div class="flex flex-col items-center gap-3">
                            <div class="flex size-20 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/30">
                                <flux:icon name="arrow-path" class="size-10 text-purple-600 dark:text-purple-400" />
                            </div>
                            <flux:text class="font-semibold text-purple-700 dark:text-purple-300">{{ __('Refunded') }}</flux:text>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('This payment has been refunded.') }}</flux:text>
                        </div>
                    @else
                        <x-status-badge :status="$modalDisplayStatus->value" :label="$modalDisplayStatus->label()" />
                    @endif
                </div>

                @if ($this->selectedPayment->provider_reference)
                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-900/40">
                        <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Provider Reference') }}</p>
                        <p class="mt-0.5 text-sm font-mono text-zinc-700 dark:text-zinc-300">{{ $this->selectedPayment->provider_reference }}</p>
                    </div>
                @endif
            </div>
        </flux:modal>
    @endif
</div>
