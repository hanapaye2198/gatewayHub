<x-layouts::auth.simple>
    @php
        $demoApiKey = config('demo.api_key');
        $apiUrl = url('/api/payments');
        $amount = 500;
    @endphp

    <div
        class="flex w-full max-w-sm flex-col gap-6"
        x-data="demoCheckout({
            apiKey: @js($demoApiKey),
            apiUrl: @js($apiUrl),
            amount: @js($amount),
        })"
        x-init="init()"
    >
        <template x-if="merchantBranding">
            <div class="flex items-center gap-4 rounded-xl border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-950">
                <img
                    x-bind:src="merchantBranding.logo"
                    alt=""
                    width="48"
                    height="48"
                    class="size-12 rounded-lg object-contain"
                />
                <div class="min-w-0">
                    <p class="text-xs text-stone-500 dark:text-stone-400">Demo merchant</p>
                    <p class="truncate font-semibold text-stone-900 dark:text-white" x-text="merchantBranding.name"></p>
                </div>
            </div>
        </template>

        <flux:heading size="lg">Demo Checkout</flux:heading>

        @if (blank($demoApiKey))
            <flux:callout variant="danger" icon="exclamation-triangle">
                Demo is not configured. Set <code class="text-xs">DEMO_API_KEY</code> in your <code class="text-xs">.env</code> with a test merchant's API key.
            </flux:callout>
        @else
            <div class="flex flex-col gap-6">
                <div class="rounded-xl border border-stone-200 bg-white p-6 dark:border-stone-800 dark:bg-stone-950">
                    <div class="mb-4 flex items-baseline justify-between">
                        <span class="text-stone-600 dark:text-stone-400">Amount</span>
                        <span class="text-xl font-semibold">₱{{ number_format($amount) }}</span>
                    </div>

                    <template x-if="state === 'idle'">
                        <flux:button
                            variant="primary"
                            class="w-full"
                            @click="createPayment()"
                            x-bind:disabled="loading"
                            x-bind:style="merchantBranding ? 'background-color: ' + merchantBranding.theme_color + '; border-color: ' + merchantBranding.theme_color + ';' : ''"
                        >
                            Pay Now
                        </flux:button>
                    </template>

                    <template x-if="state === 'loading'">
                        <flux:button variant="primary" class="w-full" disabled x-bind:style="merchantBranding ? 'background-color: ' + merchantBranding.theme_color + '; border-color: ' + merchantBranding.theme_color + ';' : ''">
                            <flux:icon name="arrow-path" class="size-5 animate-spin" />
                            Creating payment…
                        </flux:button>
                    </template>
                </div>

                <template x-if="state === 'success' && qrValue">
                    <div class="flex flex-col items-center gap-4 rounded-xl border border-stone-200 bg-white p-6 dark:border-stone-800 dark:bg-stone-950">
                        <img
                            x-bind:src="qrImageUrl"
                            alt="Payment QR Code"
                            class="size-48 rounded-lg border border-stone-200 dark:border-stone-700"
                            width="192"
                            height="192"
                        />
                        <p class="text-center text-sm text-stone-600 dark:text-stone-400">
                            Scan QR with GCash, Maya, or bank app
                        </p>
                        <p class="text-center text-xs text-stone-500 dark:text-stone-500" x-text="referenceId ? 'Reference: ' + referenceId : ''"></p>
                    </div>
                </template>

                <template x-if="state === 'success' && checkoutLink && !qrValue">
                    <div class="flex flex-col items-center gap-4 rounded-xl border border-stone-200 bg-white p-6 dark:border-stone-800 dark:bg-stone-950">
                        <flux:link x-bind:href="checkoutLink" target="_blank" variant="primary" class="inline-flex">
                            Open checkout link
                        </flux:link>
                        <p class="text-center text-sm text-stone-600 dark:text-stone-400">
                            Scan QR with GCash, Maya, or bank app
                        </p>
                    </div>
                </template>

                <template x-if="state === 'error'">
                    <flux:callout variant="danger" icon="exclamation-triangle">
                        <span x-text="errorMessage"></span>
                    </flux:callout>
                </template>
            </div>
        @endif

        <a href="{{ route('home') }}" class="text-center text-sm text-stone-500 hover:text-stone-700 dark:text-stone-400 dark:hover:text-stone-300" wire:navigate>
            ← Back to home
        </a>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('demoCheckout', (config) => ({
                apiKey: config.apiKey,
                apiUrl: config.apiUrl,
                amount: config.amount,
                state: 'idle',
                loading: false,
                qrValue: null,
                checkoutLink: null,
                referenceId: null,
                errorMessage: null,
                merchantBranding: null,

                get qrImageUrl() {
                    if (!this.qrValue) return '';
                    return 'https://api.qrserver.com/v1/create-qr-code/?size=192x192&data=' + encodeURIComponent(this.qrValue);
                },

                init() {
                    // No-op; config is passed
                },

                async createPayment() {
                    if (!this.apiKey || this.loading) return;
                    this.state = 'loading';
                    this.loading = true;
                    this.errorMessage = null;
                    this.qrValue = null;
                    this.checkoutLink = null;
                    this.referenceId = null;

                    const reference = 'DEMO-' + Date.now();

                    try {
                        const response = await fetch(this.apiUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'Authorization': 'Bearer ' + this.apiKey,
                            },
                            body: JSON.stringify({
                                amount: this.amount,
                                currency: 'PHP',
                                gateway: 'coins',
                                reference: reference,
                                checkout: true,
                            }),
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            throw new Error(data.error || data.message || 'Payment creation failed.');
                        }

                        const payment = data.data || data.payment || {};
                        this.merchantBranding = payment.merchant || null;
                        this.referenceId = payment.reference_id || payment.payment_id || reference;
                        const checkoutUrl = payment.checkout_url || payment.redirect_url || null;
                        if (checkoutUrl) {
                            window.location.assign(checkoutUrl);
                            return;
                        }
                        if (payment.qr_data) {
                            this.qrValue = payment.qr_data;
                        }
                        if (payment.checkout_url || payment.redirect_url) {
                            this.checkoutLink = payment.checkout_url || payment.redirect_url;
                        }
                        this.state = 'success';
                    } catch (err) {
                        this.errorMessage = err.message || 'An error occurred. Please try again.';
                        this.state = 'error';
                    } finally {
                        this.loading = false;
                    }
                },
            }));
        });
    </script>
</x-layouts::auth.simple>
