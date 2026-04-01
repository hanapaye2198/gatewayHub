<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>{{ config('app.name') }} — {{ ucfirst($outcome) }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-50 p-8 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <div class="mx-auto max-w-md rounded-xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <h1 class="text-lg font-semibold">
                @if ($outcome === 'success')
                    {{ __('Payment completed') }}
                @elseif ($outcome === 'failure')
                    {{ __('Payment did not complete') }}
                @elseif ($outcome === 'cancel')
                    {{ __('Payment cancelled') }}
                @else
                    {{ __('Payment') }}
                @endif
            </h1>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Reference') }}: <span class="font-mono text-xs">{{ $payment->reference_id }}</span>
            </p>
            <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-500">
                {{ __('You can close this window and return to the merchant site.') }}
            </p>
        </div>
    </body>
</html>
