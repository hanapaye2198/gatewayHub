@extends('layouts.admin')

@section('content')
    <div class="flex h-full w-full flex-1 flex-col gap-6 px-4 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-zinc-200 bg-white px-8 py-7 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Platform Fee') }}</h1>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Current Rate: :rate%', ['rate' => $percentage]) }}
            </p>
            <p class="mt-3 max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('This percentage is applied to applicable paid transactions using the existing platform fee calculation.') }}
            </p>
            <p class="mt-2 max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('The customer pays the quoted amount. GatewayHub deducts this fee from the merchant proceeds.') }}
            </p>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
            @if (session('status'))
                <flux:callout variant="success" icon="check-circle" class="mb-5">
                    {{ session('status') }}
                </flux:callout>
            @endif

            <form method="POST" action="{{ route('admin.platform-fee.update') }}" class="flex max-w-xl flex-col gap-5">
                @csrf
                @method('PUT')

                <flux:field>
                    <flux:label for="percentage">{{ __('Platform fee') }}</flux:label>
                    <flux:input
                        id="percentage"
                        name="percentage"
                        type="number"
                        inputmode="decimal"
                        step="0.01"
                        min="0"
                        max="100"
                        :value="old('percentage', $percentage)"
                        required
                        autofocus
                    />
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Percent of the gross amount. Example: 1.50') }}</p>
                    <flux:error name="percentage" />
                </flux:field>

                <div>
                    <flux:button type="submit" variant="primary">{{ __('Save Changes') }}</flux:button>
                </div>
            </form>
        </div>
    </div>
@endsection
