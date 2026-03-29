<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <div
            class="rounded-2xl border border-zinc-200/90 bg-white/80 p-6 shadow-lg shadow-zinc-950/5 ring-1 ring-zinc-950/[0.04] backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/50 dark:shadow-black/30 dark:ring-white/10 sm:p-8"
        >
            @include('pages::onboarding._step-indicator', ['step' => $step, 'totalSteps' => $totalSteps])

            <div class="flex flex-col gap-6">
                <x-auth-header
                    :title="__('Business profile')"
                    :description="__('Tell us about your business to create your merchant account')"
                />

                <form method="POST" action="{{ route('onboarding.business.store') }}" class="flex flex-col gap-5">
                    @csrf

                    @if ($errors->any())
                        <div
                            class="rounded-lg border border-red-200 bg-red-50/90 px-3 py-2.5 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200"
                            role="alert"
                        >
                            <ul class="list-inside list-disc space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <flux:input
                        name="business_name"
                        :label="__('Business name')"
                        :value="old('business_name')"
                        type="text"
                        required
                        autofocus
                        autocomplete="organization"
                        :placeholder="__('Your company or brand name')"
                        icon="building-office-2"
                    />

                    <flux:input
                        name="business_email"
                        :label="__('Business email')"
                        :value="old('business_email')"
                        type="email"
                        required
                        autocomplete="email"
                        placeholder="billing@example.com"
                        icon="envelope"
                    />

                    <flux:button type="submit" variant="primary" class="w-full">
                        {{ __('Continue') }}
                    </flux:button>
                </form>
            </div>
        </div>

        <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Need help?') }}
            <flux:link :href="route('home')" wire:navigate class="font-semibold text-zinc-900 dark:text-zinc-100">
                {{ __('Back to home') }}
            </flux:link>
        </p>
    </div>
</x-layouts::auth>
