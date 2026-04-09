<x-layouts::app :title="__('Create Payment')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <flux:button variant="ghost" icon="arrow-left" :href="route('dashboard.payments')" wire:navigate class="-ms-2">
                {{ __('Back to payments') }}
            </flux:button>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            @if ($merchantBranding)
                <div class="mb-6 flex items-center gap-4">
                    <img
                        src="{{ $merchantBranding['logo'] }}"
                        alt=""
                        width="48"
                        height="48"
                        class="size-12 rounded-lg object-contain"
                    />
                    <div>
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Collecting as') }}</p>
                        <p class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $merchantBranding['name'] }}</p>
                    </div>
                </div>
            @endif
            <flux:heading size="lg">{{ __('Create Payment') }}</flux:heading>
            <flux:subheading class="mt-1">{{ __('Create a new payment. All options are collected via Coins dynamic QR.') }}</flux:subheading>

            @if ($enabledGateways->isEmpty())
                <flux:callout variant="warning" icon="exclamation-triangle" class="mt-6">
                    {{ __('No gateways are enabled for your account.') }}
                    <flux:link :href="route('dashboard.gateways')" wire:navigate class="font-medium">
                        {{ __('View gateway status') }}
                    </flux:link>
                </flux:callout>
            @else
                <form action="{{ route('dashboard.payments.store') }}" method="POST" class="mt-6 max-w-md space-y-6">
                    @csrf

                    <flux:field>
                        <flux:label for="amount">{{ __('Amount') }}</flux:label>
                        <flux:input
                            id="amount"
                            type="number"
                            name="amount"
                            value="{{ old('amount') }}"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            required
                        />
                        <flux:error name="amount" />
                    </flux:field>

                    <flux:field>
                        <flux:label for="currency">{{ __('Currency') }}</flux:label>
                        <flux:input
                            id="currency"
                            type="text"
                            name="currency"
                            value="{{ old('currency', 'PHP') }}"
                            required
                        />
                        <flux:error name="currency" />
                    </flux:field>

                    <flux:field>
                        <flux:label for="gateway">{{ __('Gateway') }}</flux:label>
                        <flux:select id="gateway" name="gateway" required>
                            <option value="">{{ __('Select gateway') }}</option>
                            @foreach ($enabledGateways as $gateway)
                                <option value="{{ $gateway->code }}" @selected(old('gateway') === $gateway->code)>{{ $gateway->name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="gateway" />
                    </flux:field>

                    <div class="flex gap-3">
                        <flux:button
                            type="submit"
                            variant="primary"
                            style="{{ $merchantBranding ? 'background-color: '.$merchantBranding['theme_color'].'; border-color: '.$merchantBranding['theme_color'].';' : '' }}"
                        >
                            {{ __('Create Payment') }}
                        </flux:button>
                        <flux:button type="button" variant="ghost" :href="route('dashboard.payments')" wire:navigate>
                            {{ __('Cancel') }}
                        </flux:button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-layouts::app>
