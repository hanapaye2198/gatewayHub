@props([
    'title' => null,
    'context' => null,
])

<flux:header
    sticky
    data-app-shell-topbar
    class="app-shell-topbar"
>
    <div class="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
        <flux:sidebar.toggle
            class="app-shell-toggle shrink-0 lg:hidden"
            icon="bars-2"
            inset="left"
        />

        <flux:sidebar.collapse
            class="app-shell-toggle max-lg:hidden shrink-0"
            inset="left"
            :tooltip="__('Toggle sidebar')"
        />

        @if ($title)
            <div class="app-shell-topbar-title min-w-0">
                @if ($context)
                    <p class="truncate text-[0.65rem] font-semibold tracking-[0.14em] text-zinc-500 uppercase dark:text-zinc-500">
                        {{ $context }}
                    </p>
                @endif
                <flux:text class="truncate text-sm font-semibold text-zinc-900 sm:text-[0.95rem] dark:text-zinc-50">
                    {{ $title }}
                </flux:text>
            </div>
        @endif
    </div>

    <flux:spacer />

    <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
        {{ $slot }}
        <x-header-theme-toggle />
        <x-header-user-menu />
    </div>
</flux:header>

<script>
    document.querySelector('[data-app-shell-topbar] .theme-toggle')?.addEventListener('click', () => {
        window.Flux?.applyAppearance?.(
            document.documentElement.classList.contains('dark') ? 'light' : 'dark'
        );
    });
</script>
