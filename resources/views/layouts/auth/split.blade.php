<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="grid min-h-svh w-full grid-cols-1 lg:grid-cols-2">
            {{-- Left: product details (50% on large screens; hidden on small to prioritize the form) --}}
            <aside
                class="relative hidden min-h-svh flex-col justify-between overflow-hidden bg-linear-to-br from-zinc-900 via-zinc-900 to-emerald-950 px-8 py-10 text-white sm:px-12 lg:flex"
            >
                <div
                    class="pointer-events-none absolute inset-0 opacity-[0.07]"
                    style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"
                    aria-hidden="true"
                ></div>

                <div class="relative z-10 flex flex-col gap-10">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-3 font-medium" wire:navigate>
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-white/10 text-emerald-300 ring-1 ring-white/10"
                            aria-hidden="true"
                        >
                            <flux:icon name="wallet" class="size-6" />
                        </span>
                        <span class="text-lg font-semibold tracking-tight">{{ config('app.name', 'GatewayHub') }}</span>
                    </a>

                    <div class="max-w-md space-y-6">
                        <flux:heading size="xl" class="text-white">
                            {{ __('Accept and track payments in one place') }}
                        </flux:heading>
                        <flux:subheading class="text-base text-zinc-300">
                            {{ __('GatewayHub connects your business to secure checkout, dashboards, and APIs built for modern merchants.') }}
                        </flux:subheading>

                        <ul class="space-y-4 text-sm text-zinc-200">
                            <li class="flex gap-3">
                                <span
                                    class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-300"
                                    aria-hidden="true"
                                >
                                    <flux:icon name="shield-check" class="size-4" />
                                </span>
                                <span>
                                    <span class="font-medium text-white">{{ __('Secure sign-in') }}</span>
                                    {{ __('— email, password, and optional Google OAuth.') }}
                                </span>
                            </li>
                            <li class="flex gap-3">
                                <span
                                    class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-300"
                                    aria-hidden="true"
                                >
                                    <flux:icon name="chart-bar-square" class="size-4" />
                                </span>
                                <span>
                                    <span class="font-medium text-white">{{ __('Live visibility') }}</span>
                                    {{ __('— monitor payments and activity from your dashboard.') }}
                                </span>
                            </li>
                            <li class="flex gap-3">
                                <span
                                    class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-300"
                                    aria-hidden="true"
                                >
                                    <flux:icon name="bolt" class="size-4" />
                                </span>
                                <span>
                                    <span class="font-medium text-white">{{ __('Developer-friendly') }}</span>
                                    {{ __('— API credentials and tools when you need automation.') }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="relative z-10 mt-12 text-sm text-zinc-400">
                    <p>&copy; {{ now()->year }} {{ config('app.name', 'GatewayHub') }}. {{ __('All rights reserved.') }}</p>
                </div>
            </aside>

            {{-- Right: auth forms (full width mobile; 50% on lg) --}}
            <div
                class="flex min-h-svh flex-col justify-center bg-zinc-50/80 px-4 py-8 sm:px-8 lg:px-12 dark:bg-transparent"
            >
                <a
                    href="{{ route('home') }}"
                    class="mb-8 flex flex-col items-center gap-2.5 font-medium lg:hidden"
                    wire:navigate
                >
                    <span
                        class="flex size-11 items-center justify-center rounded-xl bg-emerald-600/10 text-emerald-700 shadow-sm dark:bg-emerald-400/15 dark:text-emerald-300"
                        aria-hidden="true"
                    >
                        <flux:icon name="wallet" class="size-6" />
                    </span>
                    <span class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-white">
                        {{ config('app.name', 'GatewayHub') }}
                    </span>
                </a>

                <div class="mx-auto w-full max-w-md">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
