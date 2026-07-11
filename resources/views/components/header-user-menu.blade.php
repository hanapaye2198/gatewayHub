<flux:dropdown position="bottom" align="end">
    <flux:profile
        :name="auth()->user()->name"
        :initials="auth()->user()->initials()"
        icon-trailing="chevron-down"
        class="app-shell-profile-trigger"
        data-test="header-user-menu"
    />

    <flux:menu class="min-w-56">
        <div class="flex items-center gap-3 px-2 py-2 text-start text-sm">
            <flux:avatar
                :name="auth()->user()->name"
                :initials="auth()->user()->initials()"
            />
            <div class="grid min-w-0 flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                <flux:text class="truncate text-zinc-500 dark:text-zinc-400">{{ auth()->user()->email }}</flux:text>
            </div>
        </div>

        <flux:menu.separator />

        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                {{ __('Settings') }}
            </flux:menu.item>
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer"
                    data-test="logout-button"
                >
                    {{ __('Log Out') }}
                </flux:menu.item>
            </form>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
