<?php

use App\Concerns\PasswordValidationRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        $user = Auth::user();
        $mustChangePassword = (bool) $user->must_change_password;

        $passwordRules = $this->passwordRules();

        if ($mustChangePassword) {
            $passwordRules[] = 'different:current_password';
        }

        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $passwordRules,
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        $this->reset('current_password', 'password', 'password_confirmation');

        if ($mustChangePassword && $user->isMerchantUser()) {
            session()->flash('status', __('Your password has been changed.'));

            $this->redirect($user->merchantOnboardingOrDashboardUrl(), navigate: true);

            return;
        }

        $this->dispatch('password-updated');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Password Settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Update password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
        @if (auth()->user()->must_change_password)
            <flux:callout variant="warning" icon="exclamation-triangle" class="mb-6" data-test="must-change-password-callout">
                {{ __('Your password was reset to the default password. Please set a new password to continue using your dashboard.') }}
            </flux:callout>
        @endif

        <form method="POST" wire:submit="updatePassword" class="space-y-6">
            <flux:input
                wire:model="current_password"
                :label="__('Current password')"
                type="password"
                required
                autocomplete="current-password"
            />
            <flux:input
                wire:model="password"
                :label="__('New password')"
                type="password"
                required
                autocomplete="new-password"
            />
            <flux:input
                wire:model="password_confirmation"
                :label="__('Confirm Password')"
                type="password"
                required
                autocomplete="new-password"
            />

            <div class="flex flex-wrap items-center gap-3 pt-2">
                <flux:button variant="primary" type="submit" class="min-w-[120px]" data-test="update-password-button">
                    {{ __('Save') }}
                </flux:button>
                <x-action-message class="text-sm text-zinc-600 dark:text-zinc-400" on="password-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
