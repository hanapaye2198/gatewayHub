<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <div
            class="rounded-2xl border border-zinc-200/90 bg-white/80 p-6 shadow-lg shadow-zinc-950/5 ring-1 ring-zinc-950/[0.04] backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/50 dark:shadow-black/30 dark:ring-white/10 sm:p-8"
        >
            <div class="flex flex-col gap-6">
                <x-auth-header :title="__('Log in to your account')" :description="__('Sign in with your email and password to continue')" />

                @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                    <p class="text-center text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                        {{ __('After signing in, you can enable an authenticator app (two-factor authentication) under Settings for stronger account security.') }}
                    </p>
                @endif

                <x-auth-session-status
                    class="rounded-lg border border-emerald-200/90 bg-emerald-50/90 px-3 py-2.5 text-center text-sm text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/50 dark:text-emerald-200"
                    :status="session('status')"
                />

                <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
                    @csrf

                    <flux:input
                        name="email"
                        :label="__('Email address')"
                        :value="old('email')"
                        type="email"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="email@example.com"
                        icon="envelope"
                    />

                    <div class="flex flex-col gap-2">
                        <flux:input
                            name="password"
                            :label="__('Password')"
                            type="password"
                            required
                            autocomplete="current-password"
                            :placeholder="__('Enter your password')"
                            icon="lock-closed"
                            viewable
                        />
                        @if (Route::has('password.request'))
                            <div class="flex justify-end">
                                <flux:link
                                    class="text-xs font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-200"
                                    :href="route('password.request')"
                                    wire:navigate
                                >
                                    {{ __('Forgot password?') }}
                                </flux:link>
                            </div>
                        @endif
                    </div>

                    <flux:checkbox name="remember" :label="__('Keep me signed in on this device')" :checked="old('remember')" />

                    <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                        {{ __('Log in') }}
                    </flux:button>
                </form>
            </div>
        </div>

        @if (Route::has('register'))
            <p class="flex flex-wrap items-center justify-center gap-x-1 gap-y-0.5 text-center text-sm text-zinc-600 dark:text-zinc-400">
                <span>{{ __('Don\'t have an account?') }}</span>
                <flux:link :href="route('register')" wire:navigate class="font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ __('Sign up') }}
                </flux:link>
            </p>
        @endif
    </div>
</x-layouts::auth>
