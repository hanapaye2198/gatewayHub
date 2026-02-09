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
            ->with('gateway')
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

<div wire:poll.12s class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <flux:heading>{{ __('Payments') }}</flux:heading>
    <flux:subheading>{{ __('View your payment history') }}</flux:subheading>

    <div class="mt-4 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <flux:table>
            <flux:table.columns :sticky="true">
                <flux:table.cell variant="strong">{{ __('Reference') }}</flux:table.cell>
                <flux:table.cell variant="strong">{{ __('Gateway') }}</flux:table.cell>
                <flux:table.cell variant="strong">{{ __('Amount') }}</flux:table.cell>
                <flux:table.cell variant="strong">{{ __('Status') }}</flux:table.cell>
                <flux:table.cell variant="strong">{{ __('Created') }}</flux:table.cell>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->payments as $payment)
                    <flux:table.row
                        wire:key="payment-{{ $payment->id }}"
                        wire:click="selectPayment('{{ $payment->id }}')"
                        class="cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50"
                    >
                        <flux:table.cell>{{ $payment->reference_id }}</flux:table.cell>
                        <flux:table.cell>{{ $payment->gateway?->name ?? ucfirst($payment->gateway_code) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</flux:table.cell>
                        <flux:table.cell>
                            @php
                                $badgeColor = match ($payment->status) {
                                    'paid' => 'green',
                                    'failed' => 'red',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:badge :color="$badgeColor">{{ ucfirst($payment->status) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $payment->created_at->format('M j, Y g:i A') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row wire:key="payment-empty">
                        <flux:table.cell colspan="5" class="text-center text-zinc-500 dark:text-zinc-400 py-8">
                            {{ __('No payments yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    @if ($this->selectedPayment)
        <flux:modal wire:model="showPaymentDetail" wire:close="closeDetail" class="max-w-md" dismissible>
            <flux:heading size="lg">{{ $this->selectedPayment->reference_id }}</flux:heading>
            <flux:subheading class="mt-1">
                {{ number_format($this->selectedPayment->amount, 2) }} {{ $this->selectedPayment->currency }}
                · {{ $this->selectedPayment->gateway?->name ?? ucfirst($this->selectedPayment->gateway_code) }}
            </flux:subheading>

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
                        <flux:badge color="zinc">{{ ucfirst($this->selectedPayment->status) }}</flux:badge>
                    @endif
                @else
                    <flux:badge :color="match ($this->selectedPayment->status) {
                        'paid' => 'green',
                        'failed' => 'red',
                        default => 'zinc',
                    }">{{ ucfirst($this->selectedPayment->status) }}</flux:badge>
                @endif
            </div>
        </flux:modal>
    @endif
</div>
