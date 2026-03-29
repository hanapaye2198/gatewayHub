<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <div
            class="rounded-2xl border border-zinc-200/90 bg-white/80 p-6 shadow-lg shadow-zinc-950/5 ring-1 ring-zinc-950/[0.04] backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/50 dark:shadow-black/30 dark:ring-white/10 sm:p-8"
        >
            @include('pages::onboarding._step-indicator', ['step' => $step, 'totalSteps' => $totalSteps])

            <div class="flex flex-col gap-6">
                <x-auth-header
                    :title="__('Your API credentials')"
                    :description="__('Save your API credentials. You won’t be able to see them again.')"
                />

                @if ($keysMissing && $merchantHasCredentials)
                    <div
                        class="rounded-xl border border-amber-200 bg-amber-50/90 p-4 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100"
                    >
                        {{ __('Your session expired before we could show the keys again. Your API key is still active — open API credentials in the dashboard if you need to rotate it.') }}
                    </div>
                @elseif (! $keysMissing)
                    <div
                        class="rounded-xl border border-amber-200 bg-amber-50 p-6 shadow-sm dark:border-amber-800 dark:bg-amber-950/30"
                    >
                        <flux:heading size="lg" class="flex items-center gap-2 text-amber-800 dark:text-amber-200">
                            <flux:icon name="exclamation-triangle" class="size-5 shrink-0" />
                            {{ __('Save these credentials now') }}
                        </flux:heading>
                        <flux:text class="mt-2 text-amber-700 dark:text-amber-300">
                            {{ __('This is the only time we will show the API key and secret in full. Copy them and store them securely.') }}
                        </flux:text>

                        <div class="mt-6 space-y-4">
                            <div>
                                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('API Key') }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-3" x-data>
                                    <input
                                        type="text"
                                        value="{{ e($apiKey) }}"
                                        readonly
                                        id="onboarding-api-key"
                                        class="min-w-0 flex-1 rounded-lg border border-zinc-300 bg-white px-3 py-2.5 font-mono text-sm dark:border-zinc-600 dark:bg-zinc-800"
                                    />
                                    <flux:button
                                        type="button"
                                        variant="primary"
                                        icon="clipboard-document"
                                        x-on:click="navigator.clipboard.writeText(document.getElementById('onboarding-api-key').value); $dispatch('flux-toast', { message: '{{ __('Copied to clipboard') }}', type: 'success' })"
                                    >
                                        {{ __('Copy') }}
                                    </flux:button>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('API Secret') }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-3" x-data>
                                    <input
                                        type="text"
                                        value="{{ e($apiSecret) }}"
                                        readonly
                                        id="onboarding-api-secret"
                                        class="min-w-0 flex-1 rounded-lg border border-zinc-300 bg-white px-3 py-2.5 font-mono text-sm dark:border-zinc-600 dark:bg-zinc-800"
                                    />
                                    <flux:button
                                        type="button"
                                        variant="primary"
                                        icon="clipboard-document"
                                        x-on:click="navigator.clipboard.writeText(document.getElementById('onboarding-api-secret').value); $dispatch('flux-toast', { message: '{{ __('Copied to clipboard') }}', type: 'success' })"
                                    >
                                        {{ __('Copy') }}
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('onboarding.complete') }}" class="flex flex-col gap-3">
                    @csrf
                    <flux:button type="submit" variant="primary" class="w-full">
                        {{ __('Continue to dashboard') }}
                    </flux:button>
                </form>
            </div>
        </div>

        <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            <flux:link :href="route('onboarding.gateways')" wire:navigate class="font-semibold text-zinc-900 dark:text-zinc-100">
                {{ __('Back') }}
            </flux:link>
        </p>
    </div>
</x-layouts::auth>
