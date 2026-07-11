@props([
    'size' => 'md',
])

@php
    $sizeClass = match ($size) {
        'sm' => 'landing-logo--sm',
        default => 'landing-logo--md',
    };
@endphp

<img
    src="{{ asset('logo.svg') }}"
    alt="GatewayHub"
    {{ $attributes->merge(['class' => "landing-logo {$sizeClass}"]) }}
/>
