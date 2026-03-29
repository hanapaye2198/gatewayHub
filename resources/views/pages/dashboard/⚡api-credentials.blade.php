<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component
{
    #[Layout('layouts.app', ['title' => 'API Credentials'])]

    public bool $showRegenerateConfirm = false;
    public bool $showApiKey = false;

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

    public function toggleShowApiKey(): void
    {
        $this->showApiKey = ! $this->showApiKey;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 font-sans text-zinc-900 dark:text-zinc-100">
    <div class="mx-auto w-full max-w-5xl space-y-6">
        {{-- Page header (matches gateways / dashboard pattern) --}}
        <div class="rounded-xl border border-zinc-200/80 bg-white p-6 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-800/80 dark:shadow-zinc-950/20">
            <div class="mb-1 flex items-center gap-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-950/50">
                    <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912l-.003-.018a6 6 0 00-1.883.003l-.018.003A6 6 0 0112 3.75c-1.052 0-2.05.27-2.913.75l-.003.018A6 6 0 006.75 9v.75a.75.75 0 01-.75.75H4.5a.75.75 0 00-.75.75v3.75c0 .414.336.75.75.75H6a.75.75 0 01.75.75V18a.75.75 0 01-.75.75h-1.5A2.25 2.25 0 012 16.5v-3.75A2.25 2.25 0 014.5 10.5h.75a.75.75 0 00.75-.75V9z"/>
                    </svg>
                </div>
                <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">{{ __('API Credentials') }}</h1>
            </div>
            <p class="max-w-2xl text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Manage and secure your API authentication keys') }}
            </p>
        </div>

        @php $user = auth()->user(); @endphp

        {{-- API Key card --}}
        <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm transition-shadow hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900/90 dark:shadow-zinc-950/30 dark:hover:shadow-lg/20">
            <div class="p-6 sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex min-w-0 flex-1 items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-950/50">
                            <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912l-.003-.018a6 6 0 00-1.883.003l-.018.003A6 6 0 0112 3.75c-1.052 0-2.05.27-2.913.75l-.003.018A6 6 0 006.75 9v.75a.75.75 0 01-.75.75H4.5a.75.75 0 00-.75.75v3.75c0 .414.336.75.75.75H6a.75.75 0 01.75.75V18a.75.75 0 01-.75.75h-1.5A2.25 2.25 0 012 16.5v-3.75A2.25 2.25 0 014.5 10.5h.75a.75.75 0 00.75-.75V9z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ __('API Key') }}</h2>
                            <p class="mt-0.5 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Use this key in the Authorization header for API requests') }}</p>
                        </div>
                    </div>

                    @if ($user?->hasApiKey())
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                wire:click="toggleShowApiKey"
                                class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                    @if($showApiKey)
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                    @else
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    @endif
                                </svg>
                            </button>
                            <button
                                type="button"
                                onclick="copyToClipboard('{{ $user->api_key }}')"
                                class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/>
                                </svg>
                            </button>
                        </div>
                    @endif
                </div>

                @if ($user?->hasApiKey())
                    <div class="mt-6">
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <div class="flex items-start gap-3">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-emerald-500 shadow-[0_0_0_3px_rgba(16,185,129,0.2)] dark:bg-emerald-400"></span>
                                <div class="min-w-0 flex-1 break-all font-mono text-sm">
                                    @if($showApiKey)
                                        <span class="text-zinc-900 dark:text-zinc-50">{{ $user->api_key }}</span>
                                    @else
                                        <span class="text-zinc-600 dark:text-zinc-400">{{ $user->masked_api_key }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @if ($user->api_key_generated_at)
                            <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-500">
                                <span class="inline-flex items-center gap-1">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    {{ __('Generated :date', ['date' => $user->api_key_generated_at->format('M j, Y g:i A')]) }}
                                </span>
                            </p>
                        @endif
                    </div>
                @else
                    <div class="mt-6 rounded-xl border border-dashed border-zinc-300 bg-zinc-50/80 p-8 text-center dark:border-zinc-600 dark:bg-zinc-800/30">
                        <svg class="mx-auto h-12 w-12 text-zinc-400 dark:text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                        </svg>
                        <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">{{ __('No API key set. Generate one below to start using the API.') }}</p>
                    </div>
                @endif

                <div class="mt-8">
                    <button
                        type="button"
                        wire:click="confirmRegenerate"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 dark:focus:ring-offset-zinc-900"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                        </svg>
                        {{ $user?->hasApiKey() ? __('Regenerate API Key') : __('Generate API Key') }}
                    </button>
                </div>
            </div>
        </div>

        @if (session('new_api_key'))
            <div class="rounded-xl border border-amber-200 bg-amber-50/90 shadow-sm dark:border-amber-500/25 dark:bg-amber-950/40 dark:shadow-none">
                <div class="p-6 sm:p-8">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-500/15">
                            <svg class="h-5 w-5 text-amber-700 dark:text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-semibold text-amber-950 dark:text-amber-100">{{ __('Save your new API key now') }}</h3>
                            <p class="mt-1 text-sm text-amber-900/90 dark:text-amber-200/90">{{ __('This is the only time we will show it. Copy it and store it securely.') }}</p>
                            <div class="mt-4">
                                <div class="relative">
                                    <input
                                        type="text"
                                        value="{{ e(session('new_api_key')) }}"
                                        readonly
                                        id="new-api-key-once"
                                        class="w-full rounded-lg border border-amber-300/80 bg-white px-4 py-3 pr-24 font-mono text-sm text-zinc-900 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 dark:border-amber-600/50 dark:bg-zinc-900 dark:text-zinc-100"
                                    >
                                    <button
                                        type="button"
                                        onclick="copyToClipboardAndNotify('new-api-key-once')"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md bg-amber-200/80 px-3 py-1.5 text-sm font-medium text-amber-950 transition-colors hover:bg-amber-300/80 dark:bg-amber-500/20 dark:text-amber-100 dark:hover:bg-amber-500/30"
                                    >
                                        {{ __('Copy') }}
                                    </button>
                                </div>
                            </div>
                            <p class="mt-3 flex items-start gap-2 text-xs text-amber-800 dark:text-amber-300/90">
                                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                                </svg>
                                <span>{{ __('If you lose this key, you will need to regenerate a new one. Your old key will no longer work.') }}</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- How to use --}}
        <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900/90 dark:shadow-zinc-950/30">
            <div class="p-6 sm:p-8">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-950/50">
                        <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ __('How to use') }}</h2>
                        <p class="mt-0.5 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Send your API key in the request header') }}</p>
                    </div>
                </div>

                <div class="mt-6">
                    <div class="overflow-hidden rounded-xl border border-zinc-700 bg-zinc-950 p-4 dark:border-zinc-600">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-medium text-zinc-500">{{ __('HTTP Request') }}</span>
                            <button
                                type="button"
                                onclick="copyToClipboardAndNotifyText('Authorization: Bearer YOUR_API_KEY')"
                                class="rounded-md bg-zinc-800 px-2 py-1 text-xs font-medium text-zinc-200 transition-colors hover:bg-zinc-700"
                            >
                                {{ __('Copy') }}
                            </button>
                        </div>
                        <code class="mt-3 block font-mono text-sm leading-relaxed text-emerald-400">
                            Authorization: Bearer <span class="text-amber-300">YOUR_API_KEY</span>
                        </code>
                    </div>

                    <div class="mt-6 space-y-3">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-500/15">
                                <svg class="h-4 w-4 text-emerald-700 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                </svg>
                            </div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Include this header in all API requests to authenticate') }}</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-500/15">
                                <svg class="h-4 w-4 text-indigo-700 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.288-.144M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                                </svg>
                            </div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Example: When creating a payment, include the header above with your actual key') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Security --}}
        <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900/90 dark:shadow-zinc-950/30">
            <div class="p-6 sm:p-8">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950/40">
                        <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ __('Security Best Practices') }}</h2>
                        <p class="mt-0.5 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Keep your API keys secure') }}</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="flex items-start gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-50">{{ __('Never expose your key') }}</p>
                            <p class="mt-0.5 text-xs text-zinc-600 dark:text-zinc-400">{{ __('Do not share or commit it to public repositories') }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-50">{{ __('Rotate regularly') }}</p>
                            <p class="mt-0.5 text-xs text-zinc-600 dark:text-zinc-400">{{ __('Regenerate keys periodically for better security') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Regenerate modal --}}
    <div
        x-data="{ show: @entangle('showRegenerateConfirm') }"
        x-show="show"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        style="display: none;"
    >
        <div class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0">
            <div x-show="show" x-transition.opacity class="fixed inset-0 bg-zinc-900/60 backdrop-blur-[1px] transition-opacity dark:bg-black/70" @click="show = false"></div>
            <span class="hidden sm:inline-block sm:h-screen sm:align-middle">&#8203;</span>
            <div x-show="show" x-transition.duration.300 class="relative inline-block w-full max-w-lg transform overflow-hidden rounded-2xl border border-zinc-200 bg-white text-left align-bottom shadow-xl transition-all dark:border-zinc-700 dark:bg-zinc-900 sm:my-8 sm:align-middle">
                <div class="px-6 pb-4 pt-6 sm:p-8">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-100 dark:bg-red-950/50">
                            <svg class="h-5 w-5 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">{{ __('Regenerate API key?') }}</h3>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ __('Your current API key will stop working immediately. Any applications or scripts using it will need to be updated with the new key. This action cannot be undone.') }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-3 border-t border-zinc-200 bg-zinc-50 px-6 py-4 dark:border-zinc-700 dark:bg-zinc-800/50 sm:flex-row sm:justify-end sm:px-8">
                    <button type="button" @click="show = false" class="inline-flex justify-center rounded-lg px-4 py-2 text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-200/80 dark:text-zinc-300 dark:hover:bg-zinc-700">
                        {{ __('Cancel') }}
                    </button>
                    <button type="button" wire:click="regenerateApiKey" wire:loading.attr="disabled" class="inline-flex justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                        {{ __('Yes, regenerate') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard', 'success');
    });
}

function copyToClipboardAndNotify(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        navigator.clipboard.writeText(element.value).then(() => {
            showToast('Copied to clipboard', 'success');
        });
    }
}

function copyToClipboardAndNotifyText(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard', 'success');
    });
}

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className =
        'fixed bottom-4 right-4 z-[100] rounded-lg border px-4 py-3 text-sm font-medium shadow-lg transition-all duration-300 ' +
        (type === 'success'
            ? 'border-emerald-500/30 bg-zinc-900 text-white dark:border-emerald-500/40 dark:bg-zinc-800'
            : 'border-red-500/30 bg-zinc-900 text-white dark:border-red-500/40 dark:bg-zinc-800');
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
</script>

<style>
[x-cloak] { display: none !important; }
</style>
