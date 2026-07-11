@props([
    'alt' => 'GatewayHub',
    'crop' => 'center',
])

<img
    src="{{ asset('logo.svg') }}"
    alt="{{ $alt }}"
    {{ $attributes->class([
        'h-full w-full object-contain',
        'object-top' => $crop === 'top',
        'object-center' => $crop === 'center',
    ]) }}
/>
