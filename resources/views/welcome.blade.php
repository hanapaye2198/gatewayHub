<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GatewayHub — Payment Gateway Management</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}?v=gh2">
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=gh2" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=gh2">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|space-mono:400,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/css/welcome.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="landing-page bg-zinc-50 text-zinc-900 antialiased selection:bg-blue-500/20 dark:bg-zinc-950 dark:text-zinc-100 dark:selection:bg-blue-400/25">

    <div class="landing-progress" id="scroll-progress" aria-hidden="true"></div>

    {{-- Navigation --}}
    <header id="landing-nav" class="landing-nav fixed inset-x-0 top-0 z-50 border-b border-transparent">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <div class="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-blue-600 to-indigo-600 shadow-lg shadow-blue-600/25">
                    <svg class="size-4 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <span class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">GatewayHub</span>
            </a>

            <nav class="hidden items-center gap-1 md:flex">
                @foreach (['#platform' => 'Platform', '#features' => 'Features', '#gateways' => 'Gateways', '#developers' => 'Developers'] as $href => $label)
                    <a href="{{ $href }}" class="rounded-lg px-3.5 py-2 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white">{{ $label }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2">
                <button type="button" class="mobile-nav-toggle flex size-9 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-600 md:hidden dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300" aria-label="{{ __('Toggle navigation menu') }}" aria-expanded="false" aria-controls="mobile-nav">
                    <svg class="size-5 menu-open" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                    <svg class="hidden size-5 menu-close" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <button type="button" class="theme-toggle flex size-9 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300" aria-label="{{ __('Toggle color theme') }}">
                    <svg class="size-4 dark:hidden" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/></svg>
                    <svg class="hidden size-4 dark:block" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/></svg>
                </button>
                @auth
                    <a href="{{ url('/admin') }}" class="landing-btn-primary hidden px-4 py-2 sm:inline-flex">Dashboard</a>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="hidden rounded-lg px-3 py-2 text-sm font-medium text-zinc-600 hover:text-zinc-900 sm:inline dark:text-zinc-400 dark:hover:text-white">Sign in</a>
                    @endif
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="landing-btn-primary hidden px-4 py-2 sm:inline-flex">Get started</a>
                    @endif
                @endauth
            </div>
        </div>
        <nav id="mobile-nav" class="hidden border-t border-zinc-200 bg-white px-4 py-3 md:hidden dark:border-zinc-800 dark:bg-zinc-950">
            <div class="flex flex-col gap-1">
                @foreach (['#platform' => 'Platform', '#features' => 'Features', '#gateways' => 'Gateways', '#developers' => 'Developers'] as $href => $label)
                    <a href="{{ $href }}" class="mobile-nav-link rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $label }}</a>
                @endforeach
            </div>
        </nav>
    </header>

    {{-- Hero --}}
    <section class="landing-mesh relative overflow-hidden pt-28 pb-16 sm:pt-32 sm:pb-20 lg:pb-28">
        <div class="landing-grid pointer-events-none absolute inset-0" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                <div class="landing-reveal is-visible">
                    <div class="landing-pill mb-6">
                        <span class="size-2 rounded-full bg-emerald-500 landing-pulse-dot"></span>
                        All systems operational
                    </div>

                    <h1 class="landing-headline">
                        The control plane for
                        <span class="landing-gradient-text">payment gateways</span>
                    </h1>

                    <p class="mt-6 max-w-xl text-lg leading-relaxed text-zinc-600 dark:text-zinc-400">
                        Route transactions, toggle providers instantly, and deliver signed webhooks — all from one dashboard. No redeploys. No downtime.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        @auth
                            <a href="{{ url('/admin') }}" class="landing-btn-primary">Go to Dashboard →</a>
                        @else
                            <a href="{{ route('register') }}" class="landing-btn-primary">Start for free →</a>
                            <a href="{{ route('login') }}" class="landing-btn-secondary">Sign in</a>
                        @endauth
                    </div>

                    <div class="mt-10 flex flex-wrap gap-2">
                        <span class="landing-pill">Signed webhooks</span>
                        <span class="landing-pill">Per-merchant access</span>
                        <span class="landing-pill">REST API</span>
                    </div>
                </div>

                {{-- Live stats + mini preview --}}
                <div class="landing-reveal relative" style="transition-delay: 150ms">
                    <div class="landing-preview-glow" aria-hidden="true"></div>
                    <div class="landing-glass landing-float relative p-5 sm:p-6">
                        <div class="mb-5 flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">Live platform</p>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Real-time metrics</p>
                            </div>
                            <span class="rounded-full bg-emerald-500/15 px-2.5 py-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400">Online</span>
                        </div>

                        <div id="merchants" class="scroll-mt-28 grid grid-cols-3 gap-3">
                            <div class="landing-stat">
                                <p class="stat-mono text-2xl font-bold text-zinc-900 dark:text-white" data-counter="{{ (int) ($stats['gateway_total'] ?? 0) }}">{{ number_format((int) ($stats['gateway_total'] ?? 0)) }}</p>
                                <p class="mt-1 text-[0.65rem] font-medium tracking-wider text-zinc-500 uppercase">Gateways</p>
                            </div>
                            <div class="landing-stat">
                                <p class="stat-mono text-2xl font-bold text-zinc-900 dark:text-white" data-counter="{{ (int) ($stats['merchant_total'] ?? 0) }}">{{ number_format((int) ($stats['merchant_total'] ?? 0)) }}</p>
                                <p class="mt-1 text-[0.65rem] font-medium tracking-wider text-zinc-500 uppercase">Merchants</p>
                            </div>
                            <div class="landing-stat">
                                <p class="stat-mono text-lg font-bold text-zinc-900 sm:text-xl dark:text-white" data-counter="{{ (float) ($stats['paid_collections'] ?? 0) }}" data-counter-prefix="PHP " data-counter-decimals="2">PHP {{ number_format((float) ($stats['paid_collections'] ?? 0), 2) }}</p>
                                <p class="mt-1 text-[0.6rem] font-medium tracking-wider text-zinc-500 uppercase leading-tight">Paid Collections</p>
                            </div>
                        </div>

                        <div class="mt-5 space-y-2">
                            @forelse ($previewGateways->take(3) as $gateway)
                                <div class="flex items-center justify-between rounded-xl border border-zinc-200/80 bg-white/50 px-3 py-2.5 dark:border-zinc-700/80 dark:bg-zinc-950/50">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex size-7 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                            <svg class="size-3.5 text-zinc-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                                        </div>
                                        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $gateway->name }}</span>
                                    </div>
                                    @if ($gateway->is_global_enabled)
                                        <span class="size-2 rounded-full bg-emerald-500"></span>
                                    @else
                                        <span class="size-2 rounded-full bg-zinc-400"></span>
                                    @endif
                                </div>
                            @empty
                                <p class="text-center text-sm text-zinc-500 dark:text-zinc-400">No gateways configured yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Platform preview --}}
    <section id="platform" class="relative -mt-4 pb-20 sm:pb-28 scroll-mt-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="landing-reveal landing-glass overflow-hidden">
                <div class="flex items-center gap-2 border-b border-zinc-200/80 bg-zinc-50/80 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950/80">
                    <span class="size-3 rounded-full bg-red-400"></span>
                    <span class="size-3 rounded-full bg-amber-400"></span>
                    <span class="size-3 rounded-full bg-emerald-400"></span>
                    <div class="mx-auto max-w-sm flex-1 rounded-md border border-zinc-200 bg-white px-3 py-1 text-center font-mono text-xs text-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-500">
                        app.gatewayhub.io/admin/gateways
                    </div>
                </div>

                <div class="space-y-4 p-5 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Gateways</h2>
                            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">Enable or disable gateways globally.</p>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="size-2 rounded-full bg-emerald-500"></span>
                                {{ number_format((int) ($stats['enabled_gateway_total'] ?? 0)) }} active
                            </span>
                            <span class="text-zinc-300 dark:text-zinc-600">/</span>
                            <span>{{ number_format((int) ($stats['gateway_total'] ?? 0)) }} total</span>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-100 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
                                    <th class="px-4 py-3 text-left text-xs font-semibold tracking-wide text-zinc-500 uppercase">Gateway</th>
                                    <th class="hidden px-4 py-3 text-left text-xs font-semibold tracking-wide text-zinc-500 uppercase sm:table-cell">Code</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold tracking-wide text-zinc-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-zinc-500 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($previewGateways as $gateway)
                                <tr class="border-b border-zinc-100 last:border-0 hover:bg-zinc-50/80 dark:border-zinc-800 dark:hover:bg-zinc-800/40">
                                    <td class="px-4 py-3">
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $gateway->name }}</span>
                                    </td>
                                    <td class="hidden px-4 py-3 sm:table-cell">
                                        <code class="rounded-md bg-zinc-100 px-2 py-0.5 font-mono text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $gateway->code }}</code>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($gateway->is_global_enabled)
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20">
                                                <span class="size-1.5 rounded-full bg-emerald-500"></span> Enabled
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-500 ring-1 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:ring-zinc-700">
                                                <span class="size-1.5 rounded-full bg-zinc-400"></span> Disabled
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="rounded-lg border border-zinc-200 px-3 py-1 text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:text-zinc-400">
                                            {{ $gateway->is_global_enabled ? 'Disable' : 'Enable' }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">No gateways configured yet.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Bento features --}}
    <section id="features" class="py-20 sm:py-28 scroll-mt-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="landing-reveal mx-auto mb-14 max-w-2xl text-center">
                <p class="landing-section-label">Capabilities</p>
                <h2 class="landing-section-title">Built for platform operators</h2>
                <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">Fine-grained payment infrastructure control without the operational overhead.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                @php
                $bento = [
                    ['title' => 'Instant gateway toggles', 'desc' => 'Enable or disable any provider globally in one click. Changes propagate instantly across every merchant.', 'large' => true, 'icon' => 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z', 'accent' => 'from-amber-500/10 to-orange-500/5'],
                    ['title' => 'Per-merchant control', 'desc' => 'Assign gateway access independently per business.', 'large' => false, 'icon' => 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z', 'accent' => 'from-blue-500/10 to-cyan-500/5'],
                    ['title' => 'Webhook delivery', 'desc' => 'Signed payment notifications to merchant endpoints.', 'large' => false, 'icon' => 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0', 'accent' => 'from-cyan-500/10 to-teal-500/5'],
                    ['title' => 'Audit & compliance', 'desc' => 'Full activity log for every gateway change across your platform.', 'large' => false, 'icon' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z', 'accent' => 'from-emerald-500/10 to-green-500/5'],
                    ['title' => 'REST API', 'desc' => 'Automate gateway management and integrate with your CI/CD pipelines.', 'large' => false, 'icon' => 'M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z', 'accent' => 'from-violet-500/10 to-purple-500/5'],
                    ['title' => 'Payment analytics', 'desc' => 'Real-time visibility into volumes, failure rates, and gateway performance across all merchants.', 'large' => true, 'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z', 'accent' => 'from-rose-500/10 to-pink-500/5'],
                ];
                @endphp

                @foreach ($bento as $item)
                <div class="landing-reveal {{ $item['large'] ? 'landing-bento-lg' : 'landing-bento' }}" style="transition-delay: {{ min($loop->index * 80, 400) }}ms">
                    <div class="pointer-events-none absolute inset-0 bg-linear-to-br {{ $item['accent'] }} opacity-60" aria-hidden="true"></div>
                    <div class="relative">
                        <div class="mb-4 flex size-10 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                            <svg class="size-5 text-zinc-700 dark:text-zinc-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                        </div>
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $item['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $item['desc'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Gateways --}}
    <section id="gateways" class="border-y border-zinc-200/80 bg-zinc-100/50 py-16 sm:py-20 dark:border-zinc-800 dark:bg-zinc-900/30 scroll-mt-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="landing-reveal mb-10 text-center">
                <p class="landing-section-label">Integrations</p>
                <h2 class="landing-section-title">Works with your gateways</h2>
                <p class="mx-auto mt-3 max-w-lg text-zinc-600 dark:text-zinc-400">GCash, Maya, PayPal, Coins, QRPh — toggle any provider without code changes.</p>
            </div>

            @if ($supportedGatewayNames->isNotEmpty())
                <div class="landing-reveal hidden overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_8%,black_92%,transparent)] md:block">
                    <div class="landing-marquee flex w-max gap-3">
                        @foreach ($supportedGatewayNames->merge($supportedGatewayNames) as $name)
                            <div class="shrink-0 rounded-xl border border-zinc-200/80 bg-white px-6 py-3.5 text-sm font-semibold text-zinc-700 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">{{ $name }}</div>
                        @endforeach
                    </div>
                </div>
                <div class="landing-reveal grid grid-cols-2 gap-3 sm:grid-cols-3 md:hidden">
                    @foreach ($supportedGatewayNames as $name)
                        <div class="rounded-xl border border-zinc-200/80 bg-white px-4 py-3.5 text-center text-sm font-semibold text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">{{ $name }}</div>
                    @endforeach
                </div>
            @else
                <div class="landing-reveal rounded-2xl border border-dashed border-zinc-300 px-4 py-10 text-center text-sm text-zinc-500 dark:border-zinc-600 dark:text-zinc-400">
                    No gateways available yet.
                </div>
            @endif
        </div>
    </section>

    {{-- Developers --}}
    <section id="developers" class="py-20 sm:py-28 scroll-mt-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                <div class="landing-reveal">
                    <p class="landing-section-label">Developers</p>
                    <h2 class="landing-section-title">Integrate in minutes</h2>
                    <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">RESTful endpoints, per-merchant API keys, and signed webhooks. Full docs included.</p>
                    <ul class="mt-8 space-y-3">
                        @foreach (['Payment creation & status APIs', 'Scoped API credentials per merchant', 'HMAC webhook signature verification', 'CSV payment exports'] as $point)
                        <li class="flex items-center gap-3 text-sm text-zinc-700 dark:text-zinc-300">
                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <svg class="size-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            </span>
                            {{ $point }}
                        </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('public.api-docs') }}" class="landing-btn-secondary mt-8">View API docs →</a>
                </div>

                <div class="landing-reveal overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-950 shadow-2xl ring-1 ring-white/10" style="transition-delay: 120ms">
                    <div class="flex items-center gap-2 border-b border-zinc-800 px-4 py-3">
                        <span class="size-2.5 rounded-full bg-red-500/80"></span>
                        <span class="size-2.5 rounded-full bg-amber-500/80"></span>
                        <span class="size-2.5 rounded-full bg-emerald-500/80"></span>
                        <span class="ms-2 font-mono text-xs text-zinc-500">create-payment.sh</span>
                    </div>
                    <pre class="overflow-x-auto p-5 text-[0.7rem] leading-relaxed sm:text-xs"><code class="font-mono text-zinc-300"><span class="text-violet-400">curl</span> -X POST <span class="text-emerald-400">"https://api.gatewayhub.io/v1/payments"</span> \
  -H <span class="text-amber-300">"Authorization: Bearer sk_live_••••"</span> \
  -d <span class="text-sky-300">'{"amount":1500,"currency":"PHP","gateway_code":"gcash"}'</span><span class="landing-cursor"></span></code></pre>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="pb-20 sm:pb-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="landing-reveal landing-cta-band">
                <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">Ready to take control?</h2>
                <p class="mx-auto mt-4 max-w-md text-zinc-400">Start managing your payment infrastructure today. Free to get started.</p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-bold text-zinc-900 shadow-lg transition hover:-translate-y-0.5 hover:bg-zinc-100">Create free account →</a>
                    @endif
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/5 px-6 py-3 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/10">Sign in</a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-zinc-200/80 bg-white/80 py-8 backdrop-blur-md dark:border-zinc-800 dark:bg-zinc-950/80">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 sm:flex-row sm:px-6 lg:px-8">
            <div class="flex items-center gap-2">
                <div class="flex size-6 items-center justify-center rounded-md bg-blue-600">
                    <svg class="size-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
                <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">GatewayHub</span>
            </div>
            <p class="text-xs text-zinc-400">© {{ date('Y') }} GatewayHub. All rights reserved.</p>
            <div class="flex items-center gap-5 text-xs font-medium text-zinc-500">
                <a href="{{ route('public.api-docs') }}" class="hover:text-blue-600 dark:hover:text-blue-400">API Docs</a>
                <a href="#features" class="hover:text-blue-600 dark:hover:text-blue-400">Features</a>
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Sign in</a>
                @endif
            </div>
        </div>
    </footer>

    <script>
        document.querySelector('.theme-toggle')?.addEventListener('click', () => {
            window.Flux?.applyAppearance?.(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
        });

        const nav = document.getElementById('landing-nav');
        const onScroll = () => nav?.classList.toggle('is-scrolled', window.scrollY > 16);
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        const progress = document.getElementById('scroll-progress');
        window.addEventListener('scroll', () => {
            const h = document.documentElement.scrollHeight - window.innerHeight;
            if (progress) progress.style.width = h > 0 ? `${(window.scrollY / h) * 100}%` : '0%';
        }, { passive: true });

        const revealObs = new IntersectionObserver((entries) => {
            entries.forEach((e) => { if (e.isIntersecting) { e.target.classList.add('is-visible'); revealObs.unobserve(e.target); } });
        }, { threshold: 0.1, rootMargin: '0px 0px -5% 0px' });
        document.querySelectorAll('.landing-reveal:not(.is-visible)').forEach((el) => revealObs.observe(el));

        const animateCounter = (el) => {
            const target = parseFloat(el.dataset.counter ?? '0');
            const prefix = el.dataset.counterPrefix ?? '';
            const decimals = parseInt(el.dataset.counterDecimals ?? '0', 10);
            const start = performance.now();
            const duration = 1200;
            const tick = (now) => {
                const p = Math.min((now - start) / duration, 1);
                const v = target * (1 - Math.pow(1 - p, 3));
                el.textContent = decimals > 0
                    ? `${prefix}${v.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}`
                    : `${prefix}${Math.round(v).toLocaleString()}`;
                if (p < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        };
        const counterObs = new IntersectionObserver((entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting && e.target.dataset.counterAnimated !== 'true') {
                    e.target.dataset.counterAnimated = 'true';
                    animateCounter(e.target);
                    counterObs.unobserve(e.target);
                }
            });
        }, { threshold: 0.5 });
        document.querySelectorAll('[data-counter]').forEach((el) => counterObs.observe(el));

        const mobileToggle = document.querySelector('.mobile-nav-toggle');
        const mobileNav = document.getElementById('mobile-nav');
        const menuOpen = mobileToggle?.querySelector('.menu-open');
        const menuClose = mobileToggle?.querySelector('.menu-close');
        mobileToggle?.addEventListener('click', () => {
            const open = mobileNav?.classList.toggle('hidden') === false;
            mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            menuOpen?.classList.toggle('hidden', open);
            menuClose?.classList.toggle('hidden', !open);
        });
        document.querySelectorAll('.mobile-nav-link').forEach((l) => l.addEventListener('click', () => {
            mobileNav?.classList.add('hidden');
            mobileToggle?.setAttribute('aria-expanded', 'false');
            menuOpen?.classList.remove('hidden');
            menuClose?.classList.add('hidden');
        }));
    </script>
</body>
</html>
