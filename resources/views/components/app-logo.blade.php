@props([
    'sidebar' => false,
])

@if ($sidebar)
    <flux:sidebar.brand name="KemoPay" {{ $attributes }}>
        <x-slot name="logo" class="app-shell-brand-logo">
            <x-app-logo-icon class="size-full" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="KemoPay" {{ $attributes }}>
        <x-slot name="logo" class="app-shell-brand-logo">
            <x-app-logo-icon class="size-full" />
        </x-slot>
    </flux:brand>
@endif
