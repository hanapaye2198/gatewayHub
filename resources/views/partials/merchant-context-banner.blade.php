@php
    $accessedMerchant = null;
    $contextUser = auth()->user();
    if ($contextUser instanceof \App\Models\User && $contextUser->isPlatformOperator()) {
        $accessedMerchant = app(\App\Support\MerchantContext::class)->merchant();
    }
@endphp

@if ($accessedMerchant instanceof \App\Models\Merchant)
    <div class="border-b border-amber-300 bg-amber-50 px-4 py-3 dark:border-amber-700/70 dark:bg-amber-950/50">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-amber-950 dark:text-amber-100">
                    {{ __('Merchant Context: :merchant', ['merchant' => $accessedMerchant->name]) }}
                </p>
                <p class="text-sm text-amber-900 dark:text-amber-200">
                    {{ __('You are viewing this merchant as Super Admin') }}
                </p>
            </div>
            <form method="POST" action="{{ route('admin.merchant-context.exit') }}">
                @csrf
                <flux:button type="submit" size="sm" variant="primary">{{ __('Exit Merchant') }}</flux:button>
            </form>
        </div>
    </div>
@endif
