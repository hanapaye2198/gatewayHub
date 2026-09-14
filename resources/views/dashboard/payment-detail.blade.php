<x-layouts::app :title="__('Payment') . ': ' . $payment->reference_id">
    <div
        class="flex h-full w-full flex-1 flex-col gap-6"
        x-data="paymentDetail({
            paymentId: @js($payment->id),
            initialStatus: @js($displayStatus->value),
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
            @if ($merchantBranding)
                <div class="mb-4 flex items-center gap-3">
                    <img
                        src="{{ $merchantBranding['logo'] }}"
                        alt=""
                        width="40"
                        height="40"
                        class="size-10 rounded-md object-contain"
                    />
                    <span class="font-medium text-zinc-900 dark:text-white">{{ $merchantBranding['name'] }}</span>
                </div>
            @endif
            <flux:heading size="lg">{{ $payment->reference_id }}</flux:heading>
            <flux:subheading class="mt-1">
                {{ $payment->gateway?->name ?? ucfirst($payment->gateway_code) }} | {{ number_format($payment->amount, 2) }} {{ $payment->currency }}
            </flux:subheading>
            <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Status updates are recorded from Coins webhook events.') }}
            </flux:text>

            <div class="mt-6 flex flex-col gap-6 sm:flex-row sm:items-start sm:gap-8">
                @if ($qrImageUrl)
                    <div class="flex shrink-0 flex-col items-center gap-4 sm:flex-row sm:items-start">
                        @if ($merchantBranding)
                            <div class="flex flex-col items-center gap-2 sm:items-end sm:pt-2">
                                <img
                                    src="{{ $merchantBranding['logo'] }}"
                                    alt=""
                                    width="56"
                                    height="56"
                                    class="size-14 rounded-lg object-contain"
                                />
                                <span class="max-w-[8rem] text-center text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ $merchantBranding['name'] }}</span>
                            </div>
                        @endif
                        <div class="flex flex-col items-center gap-3">
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
                            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Gross Amount') }}</flux:text>
                            <flux:text class="mt-1 block">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</flux:text>
                        </div>
                        @if ($payment->status === 'paid' && $payment->platform_fee !== null)
                            <div>
                                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('GatewayHub Platform Fee') }}</flux:text>
                                <flux:text class="mt-1 block">-{{ number_format($payment->platform_fee, 2) }} {{ $payment->currency }}</flux:text>
                            </div>
                            <div>
                                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Net After GatewayHub Fee') }}</flux:text>
                                <flux:text class="mt-1 block">{{ number_format($payment->net_amount, 2) }} {{ $payment->currency }}</flux:text>
                            </div>
                        @endif
                        <div>
                            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</flux:text>
                            <div class="mt-1">
                                <x-status-badge :status="$displayStatus->value" :label="$displayStatus->label()" />
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
                        @if ($expiresAt && $payment->status === 'pending')
                            <div class="sm:col-span-2">
                                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Expires') }}</flux:text>
                                <p class="mt-1 text-sm" x-text="countdownText"></p>
                            </div>
                        @endif
                    </dl>

                    @if ($displayStatus === \App\Enums\PaymentDisplayStatus::Expired)
                        <div class="mt-6 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                            <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $displayStatus->label() }}</p>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $displayStatus->explanation() }}</p>
                            <p class="mt-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $displayStatus->recommendedAction() }}</p>
                            <flux:button variant="primary" :href="route('dashboard.payments.create')" wire:navigate class="mt-4" icon="plus">
                                {{ __('Create Payment') }}
                            </flux:button>
                        </div>
                    @elseif ($displayStatus === \App\Enums\PaymentDisplayStatus::Failed)
                        <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-900/40 dark:bg-rose-900/20">
                            <p class="font-semibold text-rose-800 dark:text-rose-300">{{ $displayStatus->label() }}</p>
                            <p class="mt-2 text-sm text-rose-700 dark:text-rose-300">{{ $displayStatus->explanation() }}</p>
                        </div>
                    @endif
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
                displayStatus: config.initialStatus === 'paid' ? 'success' : (config.initialStatus || 'pending'),
                countdownText: '',
                pollInterval: null,

                init() {
                    this.updateCountdown();
                    if (this.displayStatus === 'pending' && this.expiresAt) {
                        setInterval(() => this.updateCountdown(), 1000);
                        this.startPolling();
                    } else if (this.displayStatus === 'pending') {
                        this.startPolling();
                    }
                },

                updateCountdown() {
                    if (!this.expiresAt || this.displayStatus !== 'pending') return;
                    const now = new Date();
                    if (now >= this.expiresAt) {
                        this.countdownText = '{{ __('QR time has ended. Waiting for confirmation.') }}';
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
                    if (this.displayStatus !== 'pending') return;
                    try {
                        const res = await fetch(this.statusUrl, { headers: { Accept: 'application/json' } });
                        const data = await res.json();
                        if (data.status === 'success') {
                            this.displayStatus = 'success';
                            this.stopPolling();
                            window.location.href = this.paymentsUrl;
                        } else if (data.status === 'failed') {
                            this.stopPolling();
                            window.location.reload();
                        }
                    } catch (_) {}
                },
            }));
        });
    </script>
</x-layouts::app>
