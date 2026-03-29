<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <div
            class="rounded-2xl border border-zinc-200/90 bg-white/80 p-6 shadow-lg shadow-zinc-950/5 ring-1 ring-zinc-950/[0.04] backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/50 dark:shadow-black/30 dark:ring-white/10 sm:p-8"
        >
            <div class="flex flex-col gap-6">
                <x-auth-header :title="__('Create an account')" :description="__('A few details and you\'re ready to go')" />

                <x-auth-session-status
                    class="rounded-lg border border-emerald-200/90 bg-emerald-50/90 px-3 py-2.5 text-center text-sm text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/50 dark:text-emerald-200"
                    :status="session('status')"
                />

                <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-5">
                    @csrf

                    <flux:input
                        name="name"
                        :label="__('Name')"
                        :value="old('name')"
                        type="text"
                        required
                        autofocus
                        autocomplete="name"
                        :placeholder="__('Your full name')"
                        icon="user"
                    />

                    <flux:input
                        name="email"
                        :label="__('Email address')"
                        :value="old('email')"
                        type="email"
                        required
                        autocomplete="email"
                        placeholder="email@example.com"
                        icon="envelope"
                    />

                    <flux:input
                        name="password"
                        :label="__('Password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        :placeholder="__('Create a password')"
                        icon="lock-closed"
                        viewable
                    />

                    <flux:input
                        name="password_confirmation"
                        :label="__('Confirm password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        :placeholder="__('Re-enter your password')"
                        icon="lock-closed"
                        viewable
                    />

                    <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                        {{ __('Create account') }}
                    </flux:button>
                </form>
            </div>
        </div>

        <p class="flex flex-wrap items-center justify-center gap-x-1 gap-y-0.5 text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate class="font-semibold text-zinc-900 dark:text-zinc-100">
                {{ __('Log in') }}
            </flux:link>
        </p>
    </div>
</x-layouts::auth>
