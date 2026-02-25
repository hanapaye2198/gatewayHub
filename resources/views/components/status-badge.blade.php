@props(['status', 'label' => null])
@php
    $display = $label ?? ucfirst($status);
    $classes = match ($status) {
        'paid', 'succeeded', 'active', 'enabled', 'processed' => 'rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
        'pending', 'received' => 'rounded-full px-2.5 py-0.5 text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        'failed', 'reversed', 'inactive', 'disabled' => 'rounded-full px-2.5 py-0.5 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        default => 'rounded-full px-2.5 py-0.5 text-xs font-medium bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
    };
@endphp
<span {{ $attributes->merge(['class' => $classes]) }}>{{ $display }}</span>
