<x-layouts::app :title="__('Payment') . ': ' . $payment->reference_id">
    <div
        class="flex h-full w-full flex-1 flex-col gap-6"
        x-data="paymentDetail({
            paymentId: @js($payment->id),
            initialStatus: @js($payment->status),
            statusUrl: @js(route('dashboard.payments.status', $payment)),
            paymentsUrl: @js(route('dashboard.payments')),
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
                {{ $payment->gateway?->name ?? ucfirst($payment->gateway_code) }} | {{ number_format($payment->amount, 2) }} {{ $payment->currency }}
            </flux:subheading>
            <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Status updates are recorded from Coins webhook events.') }}
            </flux:text>

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
                            {{ __('Scan with GCash, Maya, Coins wallet, or other QRPH-compatible apps.') }}
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
                                <flux:text class="mt-1 block">-{{ number_format($payment->platform_fee, 2) }} {{ $payment->currency }}</flux:text>
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

        @php
            $rawResponse = is_array($payment->raw_response) ? $payment->raw_response : [];
            $surepaySendingLogs = is_array($rawResponse['surepay_sending_logs'] ?? null) ? $rawResponse['surepay_sending_logs'] : [];
            $surepayErrorLogs = is_array($rawResponse['surepay_wallet_errors'] ?? null)
                ? $rawResponse['surepay_wallet_errors']
                : (is_array($rawResponse['tunnel_wallet_errors'] ?? null) ? $rawResponse['tunnel_wallet_errors'] : []);
        @endphp

        @if ($surepaySendingLogs !== [] || $surepayErrorLogs !== [])
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <flux:heading size="md">{{ __('SurePay Flow Logs') }}</flux:heading>
                <flux:subheading class="mt-1">{{ __('Per-payment orchestration logs and failure details.') }}</flux:subheading>

                <div class="mt-4 space-y-3">
                    @foreach ($surepaySendingLogs as $log)
                        @if (is_array($log))
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:border-emerald-700/40 dark:bg-emerald-900/20 dark:text-emerald-300">
                                <div class="font-medium">{{ strtoupper((string) ($log['status'] ?? 'success')) }} | {{ (string) ($log['stage'] ?? 'flow') }}</div>
                                <div class="mt-1 text-xs">{{ (string) ($log['logged_at'] ?? 'N/A') }}</div>
                                @if (is_string($log['error'] ?? null) && $log['error'] !== '')
                                    <div class="mt-1 text-xs">{{ $log['error'] }}</div>
                                @endif
                            </div>
                        @endif
                    @endforeach

                    @foreach ($surepayErrorLogs as $log)
                        @if (is_array($log))
                            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-700/40 dark:bg-red-900/20 dark:text-red-300">
                                <div class="font-medium">{{ __('FAILED') }}</div>
                                <div class="mt-1 text-xs">{{ (string) ($log['logged_at'] ?? 'N/A') }}</div>
                                <div class="mt-1 text-xs">{{ (string) ($log['message'] ?? __('Unknown error')) }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('paymentDetail', (config) => ({
                paymentId: config.paymentId,
                statusUrl: config.statusUrl,
                paymentsUrl: config.paymentsUrl,
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
                            window.location.href = this.paymentsUrl;
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

