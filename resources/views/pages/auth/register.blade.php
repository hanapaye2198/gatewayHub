<x-layouts::auth>
    <div class="kp-auth-form-stack">
        <section class="kp-auth-form-card" aria-labelledby="register-title">
            <div class="kp-auth-card-heading">
                <span class="kp-auth-card-eyebrow">{{ __('Get started') }}</span>
                <h1 id="register-title">{{ __('Create your account') }}</h1>
                <p>{{ __('Set up your KemoPay workspace in a few quick steps.') }}</p>
            </div>

            <x-auth-session-status class="kp-auth-status" :status="session('status')" />

            <form method="POST" action="{{ route('register.store') }}" class="kp-auth-form">
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

                <flux:button type="submit" variant="primary" class="kp-auth-submit" data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </form>
        </section>

        <p class="kp-auth-switch">
            <span>{{ __('Already have a KemoPay account?') }}</span>
            <flux:link :href="route('login')" wire:navigate class="kp-auth-inline-link kp-auth-switch-link">
                {{ __('Log in') }}
            </flux:link>
        </p>
    </div>
</x-layouts::auth>
