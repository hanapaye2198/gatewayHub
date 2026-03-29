<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <div
            class="rounded-2xl border border-zinc-200/90 bg-white/80 p-6 shadow-lg shadow-zinc-950/5 ring-1 ring-zinc-950/[0.04] backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/50 dark:shadow-black/30 dark:ring-white/10 sm:p-8"
        >
            @include('pages::onboarding._step-indicator', ['step' => $step, 'totalSteps' => $totalSteps])

            <div class="flex flex-col gap-6">
                <x-auth-header
                    :title="__('Payment gateways')"
                    :description="__('Choose which gateways to enable for your account. You can change this later.')"
                />

                <form method="POST" action="{{ route('onboarding.gateways.store') }}" class="flex flex-col gap-5">
                    @csrf

                    <div class="max-h-72 space-y-3 overflow-y-auto rounded-xl border border-zinc-200 bg-zinc-50/80 p-4 dark:border-zinc-700 dark:bg-zinc-950/40">
                        @forelse ($gateways as $gateway)
                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-lg border border-transparent p-2 transition hover:border-zinc-300 hover:bg-white dark:hover:border-zinc-600 dark:hover:bg-zinc-900"
                            >
                                <input
                                    type="checkbox"
                                    name="gateway_ids[]"
                                    value="{{ $gateway->id }}"
                                    class="mt-1 size-4 rounded border-zinc-300 text-blue-600 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800"
                                    @checked(in_array($gateway->id, $selectedIds, true))
                                />
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium text-zinc-900 dark:text-zinc-100">{{ $gateway->name }}</span>
                                    <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $gateway->code }}</span>
                                </span>
                            </label>
                        @empty
                            <p class="text-center text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('No gateways are available yet. You can continue and enable them later.') }}
                            </p>
                        @endforelse
                    </div>

                    <flux:button type="submit" variant="primary" class="w-full">
                        {{ __('Continue') }}
                    </flux:button>
                </form>
            </div>
        </div>

        <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            <flux:link :href="route('onboarding.business')" wire:navigate class="font-semibold text-zinc-900 dark:text-zinc-100">
                {{ __('Back') }}
            </flux:link>
        </p>
    </div>
</x-layouts::auth>
