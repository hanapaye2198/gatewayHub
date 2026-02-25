<x-layouts::app :title="__('Payment') . ': ' . $payment->reference_id">
    <div
        class="flex h-full w-full flex-1 flex-col gap-6"
        x-data="paymentDetail({
            paymentId: @js($payment->id),
            initialStatus: @js($payment->status),
            statusUrl: @js(route('dashboard.payments.status', $payment)),
            expiresAt: @js($expiresAt?->toIso8601String()),
        })"
        x-init="init()"
    >
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <flux:button variant="ghost" icon="arrow-left" :href="route('dashboard.payments')" wire:navigate class="-ms-2">
                {{ __('Back to payments') }}
            </flux:button>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <flux:heading size="lg">{{ $payment->reference_id }}</flux:heading>
            <flux:subheading class="mt-1">
                {{ $payment->gateway?->name ?? ucfirst($payment->gateway_code) }} · {{ number_format($payment->amount, 2) }} {{ $payment->currency }}
            </flux:subheading>

            <div class="mt-6 flex flex-col gap-6 sm:flex-row sm:items-start sm:gap-8">
                @if ($qrImageUrl)
                    <div class="flex shrink-0 flex-col items-center gap-3">
                        <img
                            src="{{ $qrImageUrl }}"
                            alt="{{ __('Payment QR Code') }}"
                            class="size-48 rounded-lg border border-zinc-200 dark:border-zinc-700"
                            width="192"
                            height="192"
                        />
                        <p class="text-center text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Scan with GCash, Maya, or bank app') }}
                        </p>
                    </div>
                @endif

                <div class="min-w-0 flex-1">
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Reference ID') }}</flux:text>
                            <flux:text class="mt-1 block">{{ $payment->reference_id }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Gateway') }}</flux:text>
                            <flux:text class="mt-1 block">{{ $payment->gateway?->name ?? ucfirst($payment->gateway_code) }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Amount (gross)') }}</flux:text>
                            <flux:text class="mt-1 block">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</flux:text>
                        </div>
                        @if ($payment->status === 'paid' && $payment->platform_fee !== null)
                            <div>
                                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Platform fee') }}</flux:text>
                                <flux:text class="mt-1 block">− {{ number_format($payment->platform_fee, 2) }} {{ $payment->currency }}</flux:text>
                            </div>
                            <div>
                                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Net amount') }}</flux:text>
                                <flux:text class="mt-1 block">{{ number_format($payment->net_amount, 2) }} {{ $payment->currency }}</flux:text>
                            </div>
                        @endif
                        <div>
                            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</flux:text>
                            <div class="mt-1">
                                <template x-if="displayStatus === 'expired'">
                                    <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                        {{ __('Payment expired') }}
                                    </span>
                                </template>
                                <template x-if="displayStatus !== 'expired'">
                                    <span :class="{
                                        'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300': displayStatus === 'success',
                                        'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300': displayStatus === 'pending',
                                        'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300': displayStatus === 'failed',
                                    }" x-text="displayLabel"></span>
                                </template>
                            </div>
                        </div>
                        <div>
                            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Created') }}</flux:text>
                            <flux:text class="mt-1 block">{{ $payment->created_at->format('M j, Y g:i A') }}</flux:text>
                        </div>
                        @if ($payment->paid_at)
                            <div>
                                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Paid') }}</flux:text>
                                <flux:text class="mt-1 block">{{ $payment->paid_at->format('M j, Y g:i A') }}</flux:text>
                            </div>
                        @endif
                        @if ($expiresAt && in_array($payment->status, ['pending'], true))
                            <div class="sm:col-span-2">
                                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Expires') }}</flux:text>
                                <p class="mt-1 text-sm" x-text="displayStatus === 'expired' ? '{{ __('Payment expired') }}' : countdownText"></p>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <flux:heading size="md">{{ __('Audit Timeline') }}</flux:heading>
            <flux:subheading class="mt-1">{{ __('Webhook events for this payment') }}</flux:subheading>

            @if ($payment->webhookEvents->isEmpty())
                <flux:text class="mt-6 block text-zinc-500 dark:text-zinc-400">{{ __('No webhook events recorded yet.') }}</flux:text>
            @else
                <div class="mt-6 space-y-0">
                    @foreach ($payment->webhookEvents as $event)
                        <div class="relative flex gap-4 pb-8 last:pb-0">
                            @if (!$loop->last)
                                <div class="absolute left-[11px] top-6 h-full w-px bg-zinc-200 dark:bg-zinc-700" aria-hidden="true"></div>
                            @endif
                            <div class="relative flex size-6 shrink-0 items-center justify-center rounded-full border-2 border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                                @if ($event->status === 'processed')
                                    <flux:icon name="check" class="size-3.5 text-green-600 dark:text-green-400" />
                                @elseif ($event->status === 'failed')
                                    <flux:icon name="x-circle" class="size-3.5 text-red-600 dark:text-red-400" />
                                @else
                                    <flux:icon name="clock" class="size-3.5 text-zinc-500 dark:text-zinc-400" />
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <flux:text class="font-medium">{{ __('Webhook received') }}</flux:text>
                                <flux:text class="block text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Received') }}: {{ $event->received_at->format('M j, Y g:i:s A') }}
                                </flux:text>
                                @if ($event->processed_at)
                                    <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('Processed') }}: {{ $event->processed_at->format('M j, Y g:i:s A') }}
                                    </flux:text>
                                @endif
                                <div class="mt-2">
                                    <x-status-badge
                                        :status="$event->status"
                                        :label="match ($event->status) {
                                            'processed' => __('Payment confirmed'),
                                            'failed' => __('Processing failed'),
                                            default => __('Received'),
                                        }"
                                    />
                                </div>
                                @if ($event->status === 'failed' && $event->error_message)
                                    <flux:text class="mt-2 block text-sm text-red-600 dark:text-red-400">{{ $event->error_message }}</flux:text>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('paymentDetail', (config) => ({
                paymentId: config.paymentId,
                statusUrl: config.statusUrl,
                expiresAt: config.expiresAt ? new Date(config.expiresAt) : null,
                displayStatus: config.initialStatus === 'paid' ? 'success' : (config.initialStatus === 'failed' ? 'failed' : 'pending'),
                countdownText: '',
                pollInterval: null,

                get displayLabel() {
                    return { success: '{{ __('Success') }}', pending: '{{ __('Pending') }}', failed: '{{ __('Failed') }}' }[this.displayStatus] ?? '{{ __('Pending') }}';
                },

                init() {
                    this.updateCountdown();
                    if (this.displayStatus === 'pending' && this.expiresAt) {
                        setInterval(() => this.updateCountdown(), 1000);
                        this.startPolling();
                    }
                },

                updateCountdown() {
                    if (!this.expiresAt || this.displayStatus !== 'pending') return;
                    const now = new Date();
                    if (now >= this.expiresAt) {
                        this.displayStatus = 'expired';
                        this.stopPolling();
                        this.countdownText = '';
                        return;
                    }
                    const s = Math.floor((this.expiresAt - now) / 1000);
                    const m = Math.floor(s / 60);
                    const sec = s % 60;
                    this.countdownText = `${m}:${String(sec).padStart(2, '0')} {{ __('remaining') }}`;
                },

                startPolling() {
                    this.pollInterval = setInterval(() => this.fetchStatus(), 5000);
                },

                stopPolling() {
                    if (this.pollInterval) {
                        clearInterval(this.pollInterval);
                        this.pollInterval = null;
                    }
                },

                async fetchStatus() {
                    if (this.displayStatus !== 'pending' && this.displayStatus !== 'expired') return;
                    try {
                        const res = await fetch(this.statusUrl, { headers: { Accept: 'application/json' } });
                        const data = await res.json();
                        if (data.status === 'success') {
                            this.displayStatus = 'success';
                            this.stopPolling();
                            window.location.reload();
                        } else if (data.status === 'failed') {
                            this.displayStatus = 'failed';
                            this.stopPolling();
                        }
                    } catch (_) {}
                },
            }));
        });
    </script>
</x-layouts::app>
