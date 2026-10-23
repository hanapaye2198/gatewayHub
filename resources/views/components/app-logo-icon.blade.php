@props([
    'alt' => 'KemoPay',
    'crop' => 'center',
])

<img
    src="{{ asset('Modern K Payment App Icon.png') }}"
    alt="{{ $alt }}"
    {{ $attributes->class([
        'h-full w-full object-contain',
        'object-top' => $crop === 'top',
        'object-center' => $crop === 'center',
    ]) }}
/>
