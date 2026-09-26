@php
    $currency = $payment->currency;
@endphp

@if ($payment->usesAdditivePricing())
    <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-900/40">
        <div class="flex items-center justify-between text-sm">
            <span class="text-zinc-600 dark:text-zinc-400">{{ __('Transaction Amount') }}</span>
            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ number_format($payment->amount, 2) }} {{ $currency }}</span>
        </div>
        <div class="mt-2 flex items-center justify-between text-sm">
            <span class="text-zinc-600 dark:text-zinc-400">
                {{ __('Platform Fee') }}
                @if ($payment->platformFeePercent() !== null)
                    {{ number_format($payment->platformFeePercent(), 2) }}%
                @endif
            </span>
            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ number_format((float) $payment->platform_fee, 2) }} {{ $currency }}</span>
        </div>
        <div class="mt-2 flex items-center justify-between text-sm">
            <span class="text-zinc-600 dark:text-zinc-400">{{ __('Convenience Fee') }}</span>
            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ number_format((float) $payment->convenience_fee, 2) }} {{ $currency }}</span>
        </div>
        <div class="mt-2 border-t border-zinc-200 pt-2 dark:border-zinc-700">
            <div class="flex items-center justify-between text-sm font-semibold">
                <span class="text-zinc-900 dark:text-zinc-100">{{ __('Customer Total') }}</span>
                <span class="text-zinc-900 dark:text-zinc-100">{{ number_format((float) $payment->customer_total, 2) }} {{ $currency }}</span>
            </div>
        </div>
    </div>
@elseif ($payment->status === 'paid' && $payment->platform_fee !== null)
    <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-900/40">
        <div class="flex items-center justify-between text-sm">
            <span class="text-zinc-600 dark:text-zinc-400">{{ __('Transaction Amount') }}</span>
            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ number_format($payment->amount, 2) }} {{ $currency }}</span>
        </div>
        <div class="mt-2 flex items-center justify-between text-sm">
            <span class="text-zinc-600 dark:text-zinc-400">{{ __('GatewayHub Platform Fee') }}</span>
            <span class="font-medium text-rose-600 dark:text-rose-400">-{{ number_format($payment->platform_fee, 2) }} {{ $currency }}</span>
        </div>
        <div class="mt-2 border-t border-zinc-200 pt-2 dark:border-zinc-700">
            <div class="flex items-center justify-between text-sm font-semibold">
                <span class="text-zinc-900 dark:text-zinc-100">{{ __('Net After GatewayHub Fee') }}</span>
                <span class="text-emerald-600 dark:text-emerald-400">{{ number_format($payment->net_amount, 2) }} {{ $currency }}</span>
            </div>
        </div>
    </div>
@endif
