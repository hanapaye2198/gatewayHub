@props([
    'title' => null,
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
            <div class="min-w-0 border-s border-zinc-200 ps-2 sm:ps-3 dark:border-zinc-700/80">
                <flux:text class="truncate text-sm font-semibold text-zinc-800 sm:text-base dark:text-zinc-100">
                    {{ $title }}
                </flux:text>
            </div>
        @endif
    </div>

    <flux:spacer />

    <div class="flex shrink-0 items-center gap-2 sm:gap-3">
        {{ $slot }}
        <x-header-user-menu />
    </div>
</flux:header>
