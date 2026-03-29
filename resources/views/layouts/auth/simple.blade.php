<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-md flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2.5 font-medium" wire:navigate>
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
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
