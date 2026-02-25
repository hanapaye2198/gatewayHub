<?php

use App\Models\Payment;
use App\Services\QrCodeGenerator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component {
    #[Layout('layouts.app', ['title' => 'Payments'])]

    public ?string $selectedPaymentId = null;

    public bool $showPaymentDetail = false;

    #[Computed]
    public function payments()
    {
        return Payment::query()
            ->where('user_id', auth()->id())
            ->with(['gateway', 'platformFee'])
            ->latest('created_at')
            ->get();
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
}; ?>

<div wire:poll.12s class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Payments') }}</h1>
                <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('View your payment history') }}</p>
            </div>
            <flux:button variant="primary" :href="route('dashboard.payments.create')" wire:navigate icon="plus">
                {{ __('Create Payment') }}
            </flux:button>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 [&_tbody_tr:hover]:bg-zinc-50 dark:[&_tbody_tr:hover]:bg-zinc-700/50 [&_tbody_tr]:border-b [&_tbody_tr]:border-zinc-200/60 [&_tbody_tr:last-child]:border-b-0 dark:[&_tbody_tr]:border-zinc-600/40 [&_td]:py-4 [&_th]:font-semibold [&_th]:text-zinc-900 dark:[&_th]:text-zinc-100">
        <flux:table>
            <flux:table.columns :sticky="true">
                <flux:table.cell variant="strong">{{ __('Reference') }}</flux:table.cell>
                <flux:table.cell variant="strong">{{ __('Gateway') }}</flux:table.cell>
                <flux:table.cell variant="strong" align="end">{{ __('Amount') }}</flux:table.cell>
                <flux:table.cell variant="strong" align="end">{{ __('Platform fee') }}</flux:table.cell>
                <flux:table.cell variant="strong" align="end">{{ __('Net') }}</flux:table.cell>
                <flux:table.cell variant="strong">{{ __('Status') }}</flux:table.cell>
                <flux:table.cell variant="strong">{{ __('Created') }}</flux:table.cell>
                <flux:table.cell variant="strong" class="w-0"></flux:table.cell>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->payments as $payment)
                    <flux:table.row
                        wire:key="payment-{{ $payment->id }}"
                        wire:click="selectPayment('{{ $payment->id }}')"
                        class="cursor-pointer"
                    >
                        <flux:table.cell>{{ $payment->reference_id }}</flux:table.cell>
                        <flux:table.cell>{{ $payment->gateway?->name ?? ucfirst($payment->gateway_code) }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ $payment->platformFee ? '−' . number_format($payment->platformFee->fee_amount, 2) . ' ' . $payment->currency : '—' }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ $payment->platformFee ? number_format($payment->platformFee->net_amount, 2) . ' ' . $payment->currency : '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <x-status-badge :status="$payment->status" />
                        </flux:table.cell>
                        <flux:table.cell>{{ $payment->created_at->format('M j, Y g:i A') }}</flux:table.cell>
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
                · {{ $this->selectedPayment->gateway?->name ?? ucfirst($this->selectedPayment->gateway_code) }}
            </flux:subheading>
            @if ($this->selectedPayment->status === 'paid' && $this->selectedPayment->platform_fee !== null)
                <flux:text class="mt-2 block text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Gross') }}: {{ number_format($this->selectedPayment->amount, 2) }} · {{ __('Platform fee') }}: −{{ number_format($this->selectedPayment->platform_fee, 2) }} · {{ __('Net') }}: {{ number_format($this->selectedPayment->net_amount, 2) }} {{ $this->selectedPayment->currency }}
                </flux:text>
            @endif

            <div class="mt-6 min-h-[220px] flex flex-col items-center justify-center">
                @if ($this->selectedPayment->isQrBased())
                    @if ($this->selectedPayment->status === 'pending')
                        @php $qrSrc = $this->getQrImageSrc($this->selectedPayment); @endphp
                        @if ($qrSrc)
                            <div class="flex flex-col items-center gap-4">
                                <img src="{{ $qrSrc }}" alt="QR Code" class="size-48 object-contain" />
                                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('Waiting for payment…') }}</flux:text>
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
                @else
                    <x-status-badge :status="$this->selectedPayment->status" />
                @endif
            </div>
            </div>
        </flux:modal>
    @endif
</div>
