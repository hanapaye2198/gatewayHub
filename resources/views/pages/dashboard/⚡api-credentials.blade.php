<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component
{
    #[Layout('layouts.app', ['title' => 'API Credentials'])]

    public bool $showRegenerateConfirm = false;

    public function confirmRegenerate(): void
    {
        $this->showRegenerateConfirm = true;
    }

    public function regenerateApiKey(): void
    {
        $user = auth()->user();
        if ($user === null || $user->id !== auth()->id()) {
            return;
        }

        $newKey = $user->regenerateApiKey();
        $this->showRegenerateConfirm = false;

        session()->flash('new_api_key', $newKey);
        $this->redirect(route('dashboard.api-credentials'), navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('API Credentials') }}</h1>
        <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Manage your API key for authenticating requests') }}</p>
    </div>

    <div class="max-w-2xl space-y-6">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <flux:heading size="lg">{{ __('API Key') }}</flux:heading>
            <flux:subheading class="mt-1">{{ __('Use this key in the Authorization header when calling the API.') }}</flux:subheading>

            @php $user = auth()->user(); @endphp
            @if ($user->api_key)
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <flux:field class="min-w-0 flex-1">
                        <flux:input
                            type="text"
                            value="{{ $user->masked_api_key }}"
                            readonly
                            class="font-mono"
                        />
                    </flux:field>
                    @if ($user->api_key_generated_at)
                        <flux:text class="shrink-0 text-zinc-500 dark:text-zinc-400">
                            {{ __('Generated :date', ['date' => $user->api_key_generated_at->format('M j, Y g:i A')]) }}
                        </flux:text>
                    @endif
                </div>
            @else
                <flux:text class="mt-4 text-zinc-500 dark:text-zinc-400">{{ __('No API key set. Generate one below.') }}</flux:text>
            @endif

            <div class="mt-6">
                <flux:button variant="danger" icon="key" wire:click="confirmRegenerate" wire:loading.attr="disabled">
                    {{ $user->api_key ? __('Regenerate API Key') : __('Generate API Key') }}
                </flux:button>
            </div>
        </div>

        @if (session('new_api_key'))
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 shadow-sm dark:border-amber-800 dark:bg-amber-950/30">
                <flux:heading size="lg" class="flex items-center gap-2 text-amber-800 dark:text-amber-200">
                    <flux:icon name="exclamation-triangle" class="size-5" />
                    {{ __('Save your new API key now') }}
                </flux:heading>
                <flux:text class="mt-2 text-amber-700 dark:text-amber-300">
                    {{ __('This is the only time we will show it. Copy it and store it securely. You will not be able to see it again after you leave this page.') }}
                </flux:text>
                <div class="mt-4 flex flex-wrap items-center gap-3" x-data>
                    <div class="min-w-0 flex-1">
                        <input
                            type="text"
                            value="{{ e(session('new_api_key')) }}"
                            readonly
                            class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-zinc-400/50 focus:ring-offset-2 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:focus:ring-zinc-500/40 dark:focus:ring-offset-zinc-800"
                            id="new-api-key-once"
                        />
                    </div>
                    <flux:button
                        variant="primary"
                        icon="clipboard-document"
                        type="button"
                        x-on:click="navigator.clipboard.writeText(document.getElementById('new-api-key-once').value); $dispatch('flux-toast', { message: '{{ __('Copied to clipboard') }}', type: 'success' })"
                    >
                        {{ __('Copy to clipboard') }}
                    </flux:button>
                </div>
                <p class="mt-2 text-sm text-amber-600 dark:text-amber-400">
                    {{ __('If you lose this key, you will need to regenerate a new one. Your old key will no longer work.') }}
                </p>
            </div>
        @endif

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <flux:heading size="lg">{{ __('How to use') }}</flux:heading>
            <flux:subheading class="mt-1">{{ __('Send your API key in the request header.') }}</flux:subheading>
            <div class="mt-4 rounded-lg bg-zinc-100 p-4 font-mono text-sm dark:bg-zinc-800">
                <div class="text-zinc-500 dark:text-zinc-400">Authorization: Bearer YOUR_API_KEY</div>
            </div>
            <flux:text class="mt-4 text-zinc-600 dark:text-zinc-400">
                {{ __('Example: When creating a payment, include the header above. Replace YOUR_API_KEY with your actual key.') }}
            </flux:text>
        </div>
    </div>

    <flux:modal wire:model="showRegenerateConfirm" wire:close="showRegenerateConfirm = false" class="max-w-md" dismissible>
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Regenerate API key?') }}</flux:heading>
                <flux:text class="mt-2 block text-zinc-600 dark:text-zinc-400">
                    {{ __('Your current API key will stop working immediately. Any applications or scripts using it will need to be updated with the new key. This cannot be undone.') }}
                </flux:text>
            </div>
            <div class="flex flex-wrap gap-3 pt-2">
                <flux:button variant="danger" wire:click="regenerateApiKey" wire:loading.attr="disabled" class="min-w-[120px]">
                    {{ __('Yes, regenerate') }}
                </flux:button>
                <flux:button variant="ghost" wire:click="$set('showRegenerateConfirm', false)">
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
