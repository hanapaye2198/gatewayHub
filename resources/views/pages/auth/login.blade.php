<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <div
            class="rounded-2xl border border-zinc-200/90 bg-white/80 p-6 shadow-lg shadow-zinc-950/5 ring-1 ring-zinc-950/[0.04] backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/50 dark:shadow-black/30 dark:ring-white/10 sm:p-8"
        >
            <div class="flex flex-col gap-6">
                <x-auth-header :title="__('Log in to your account')" :description="__('Sign in with email or Google to continue')" />

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

                    <div class="relative py-0.5">
                        <flux:separator />
                        <span
                            class="absolute start-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 bg-white/80 px-3 text-xs font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-900/50 dark:text-zinc-500"
                        >
                            {{ __('or') }}
                        </span>
                    </div>

                    <flux:button
                        href="{{ route('google.redirect') }}"
                        variant="outline"
                        class="w-full font-normal"
                        data-test="google-login-button"
                    >
                        <svg class="size-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                            <path
                                fill="#4285F4"
                                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                            />
                            <path
                                fill="#34A853"
                                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                            />
                            <path
                                fill="#FBBC05"
                                d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                            />
                            <path
                                fill="#EA4335"
                                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                            />
                        </svg>
                        {{ __('Continue with Google') }}
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
