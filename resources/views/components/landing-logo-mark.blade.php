@props([
    'size' => 'md',
])

@php
    $sizeClass = match ($size) {
        'sm' => 'kp-brand-logo--sm',
        default => '',
    };
@endphp

<span {{ $attributes->merge(['class' => "kp-brand-logo {$sizeClass}"]) }}>
    <img class="kp-brand-icon" src="{{ asset('Modern K Payment App Icon.png') }}" alt="" />
    <span class="kp-brand-wordmark">KemoPay</span>
</span>
