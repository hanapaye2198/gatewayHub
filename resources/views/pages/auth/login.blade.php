<x-layouts::auth>
    <div class="kp-auth-form-stack">
        <section class="kp-auth-form-card" aria-labelledby="login-title">
            <div class="kp-auth-card-heading">
                <span class="kp-auth-card-eyebrow">{{ __('Welcome back') }}</span>
                <h1 id="login-title">{{ __('Log in to your account') }}</h1>
                <p>{{ __('Sign in to see your payments, gateways, and business activity.') }}</p>
            </div>

            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <p class="kp-auth-security-note">
                    {{ __('After signing in, you can enable an authenticator app under Settings for stronger account security.') }}
                </p>
            @endif

            <x-auth-session-status class="kp-auth-status" :status="session('status')" />

            <form method="POST" action="{{ route('login.store') }}" class="kp-auth-form">
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

                <div class="kp-auth-password-group">
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
                        <div class="kp-auth-forgot-row">
                            <flux:link :href="route('password.request')" wire:navigate class="kp-auth-inline-link">
                                {{ __('Forgot password?') }}
                            </flux:link>
                        </div>
                    @endif
                </div>

                <div class="kp-auth-remember">
                    <flux:checkbox name="remember" :label="__('Keep me signed in on this device')" :checked="old('remember')" />
                </div>

                <flux:button variant="primary" type="submit" class="kp-auth-submit" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </form>

            <div class="kp-auth-form">
                <flux:button variant="outline" :href="route('kemofi.redirect')" class="kp-auth-submit">
                    {{ __('Continue with your KemoFi account') }}
                </flux:button>
                <p class="kp-auth-security-note">
                    {{ __('Your first KemoFi sign-in creates or links your KemoPay merchant account.') }}
                </p>
            </div>
        </section>

        @if (Route::has('register'))
            <p class="kp-auth-switch">
                <span>{{ __('New to KemoPay?') }}</span>
                <flux:link :href="route('register')" wire:navigate class="kp-auth-inline-link kp-auth-switch-link">
                    {{ __('Create an account') }}
                </flux:link>
            </p>
        @endif
    </div>
</x-layouts::auth>
