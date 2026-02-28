<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GatewayHub — Payment Gateway Management</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|space-mono:400,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --accent: #2563eb;
            --accent-light: #dbeafe;
            --accent-dark: #1d4ed8;
        }

        /* Grid background */
        .grid-bg {
            background-image:
                linear-gradient(to right, rgba(113,113,122,0.07) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(113,113,122,0.07) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        .dark .grid-bg {
            background-image:
                linear-gradient(to right, rgba(255,255,255,0.04) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255,255,255,0.04) 1px, transparent 1px);
        }

        /* Animated status dot */
        @keyframes pulse-ring {
            0% { box-shadow: 0 0 0 0 rgba(34,197,94,0.4); }
            70% { box-shadow: 0 0 0 6px rgba(34,197,94,0); }
            100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
        }
        .pulse-dot { animation: pulse-ring 2s cubic-bezier(0.455,0.03,0.515,0.955) infinite; }

        /* Fade-up entrance */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-up { opacity: 0; animation: fadeUp 0.6s ease forwards; }
        .fade-up-1 { animation-delay: 0.05s; }
        .fade-up-2 { animation-delay: 0.15s; }
        .fade-up-3 { animation-delay: 0.25s; }
        .fade-up-4 { animation-delay: 0.35s; }
        .fade-up-5 { animation-delay: 0.45s; }
        .fade-up-6 { animation-delay: 0.55s; }

        /* Gateway card hover */
        .gateway-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .gateway-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }
        .dark .gateway-card:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
        }

        /* Stat number font */
        .stat-num { font-family: 'Space Mono', monospace; }

        /* Glowing badge */
        .badge-glow {
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
        }

        body { font-family: 'Instrument Sans', sans-serif; }
    </style>
</head>
<body class="bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 antialiased min-h-screen">

    {{-- ─── NAVBAR ─────────────────────────────────────────────────────── --}}
    <header class="sticky top-0 z-50 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/80 dark:bg-zinc-950/80 backdrop-blur-md">
        <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">

            {{-- Logo --}}
            <div class="flex items-center gap-2.5">
                <div class="size-7 rounded-lg bg-blue-600 flex items-center justify-center shadow-sm">
                    <svg class="size-4 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <span class="font-semibold text-zinc-900 dark:text-zinc-100 tracking-tight">GatewayHub</span>
            </div>

            {{-- Nav links --}}
            <nav class="hidden md:flex items-center gap-1">
                <a href="#features" class="px-3 py-1.5 text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-md transition-colors">Features</a>
                <a href="#gateways" class="px-3 py-1.5 text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-md transition-colors">Gateways</a>
                <a href="#merchants" class="px-3 py-1.5 text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-md transition-colors">Merchants</a>
            </nav>

            {{-- Auth buttons --}}
            <div class="flex items-center gap-2">
                @auth
                    <a href="{{ url('/admin') }}" class="inline-flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors shadow-sm">
                        Dashboard
                        <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="px-3 py-1.5 text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors">
                            Sign in
                        </a>
                    @endif
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="inline-flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors shadow-sm">
                            Get started
                        </a>
                    @endif
                @endauth
            </div>

        </div>
    </header>

    {{-- ─── HERO ────────────────────────────────────────────────────────── --}}
    <section class="relative overflow-hidden grid-bg">

        {{-- Radial glow --}}
        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
            <div class="size-[600px] rounded-full bg-blue-500/8 dark:bg-blue-500/10 blur-3xl"></div>
        </div>

        <div class="relative max-w-6xl mx-auto px-6 pt-20 pb-24 text-center">

            {{-- Badge --}}
            <div class="fade-up fade-up-1 inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 shadow-sm badge-glow mb-6">
                <span class="size-2 rounded-full bg-emerald-500 pulse-dot block"></span>
                <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">All systems operational</span>
            </div>

            {{-- Headline --}}
            <h1 class="fade-up fade-up-2 text-5xl md:text-6xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 leading-tight max-w-3xl mx-auto">
                One platform for every<br>
                <span class="text-blue-600 dark:text-blue-400">payment gateway</span>
            </h1>

            <p class="fade-up fade-up-3 mt-5 text-lg text-zinc-500 dark:text-zinc-400 max-w-xl mx-auto leading-relaxed">
                Enable, disable, and manage payment gateways across all your merchants from a single admin panel. No code changes required.
            </p>

            {{-- CTA buttons --}}
            <div class="fade-up fade-up-4 mt-8 flex flex-wrap items-center justify-center gap-3">
                @auth
                    <a href="{{ url('/admin') }}" class="inline-flex items-center gap-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-xl transition-colors shadow-sm">
                        Go to Dashboard
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>
                @else
                    <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-xl transition-colors shadow-sm">
                        Start for free
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-6 py-2.5 bg-white dark:bg-zinc-900 hover:bg-zinc-50 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 text-sm font-medium rounded-xl border border-zinc-200 dark:border-zinc-700 transition-colors shadow-sm">
                        Sign in
                    </a>
                @endauth
            </div>

            {{-- Stats row --}}
            <div class="fade-up fade-up-5 mt-16 grid grid-cols-3 max-w-lg mx-auto gap-px bg-zinc-200 dark:bg-zinc-700 rounded-2xl overflow-hidden border border-zinc-200 dark:border-zinc-700 shadow-sm">
                <div class="bg-white dark:bg-zinc-900 px-6 py-5 text-center">
                    <p class="stat-num text-2xl font-bold text-zinc-900 dark:text-zinc-50">{{ number_format((int) ($stats['gateway_total'] ?? 0)) }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Gateways</p>
                </div>
                <div class="bg-white dark:bg-zinc-900 px-6 py-5 text-center">
                    <p class="stat-num text-2xl font-bold text-zinc-900 dark:text-zinc-50">{{ number_format((int) ($stats['merchant_total'] ?? 0)) }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Merchants</p>
                </div>
                <div class="bg-white dark:bg-zinc-900 px-6 py-5 text-center">
                    <p class="stat-num text-lg font-bold text-zinc-900 dark:text-zinc-50">PHP {{ number_format((float) ($stats['paid_collections'] ?? 0), 2) }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Paid Collections</p>
                </div>
            </div>

        </div>
    </section>

    {{-- ─── DASHBOARD PREVIEW ───────────────────────────────────────────── --}}
    <section class="fade-up fade-up-6 max-w-6xl mx-auto px-6 -mt-2 pb-20">
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xl overflow-hidden">

            {{-- Fake browser chrome --}}
            <div class="flex items-center gap-2 px-4 py-3 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950">
                <span class="size-3 rounded-full bg-red-400"></span>
                <span class="size-3 rounded-full bg-amber-400"></span>
                <span class="size-3 rounded-full bg-emerald-400"></span>
                <div class="flex-1 mx-4">
                    <div class="max-w-xs mx-auto bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md px-3 py-1 text-xs text-zinc-400 dark:text-zinc-500 text-center font-mono">
                        app.gatewayhub.io/admin/gateways
                    </div>
                </div>
            </div>

            {{-- Mock admin UI --}}
            <div class="p-6 space-y-4">

                {{-- Header bar --}}
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Gateways</h2>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">Enable or disable gateways globally.</p>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-zinc-500">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-emerald-500"></span>
                            {{ number_format((int) ($stats['enabled_gateway_total'] ?? 0)) }} active
                        </span>
                        <span class="text-zinc-300 dark:text-zinc-600">/</span>
                        <span>{{ number_format((int) ($stats['gateway_total'] ?? 0)) }} total</span>
                    </div>
                </div>

                {{-- Mock table --}}
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-950">
                                <th class="text-left px-4 py-3 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Gateway</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Code</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Status</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($previewGateways as $gateway)
                            <tr class="border-b border-zinc-100 dark:border-zinc-800 last:border-0 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors gateway-card">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="size-8 rounded-lg border border-zinc-200 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-700 flex items-center justify-center shrink-0">
                                            <svg class="size-4 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/>
                                            </svg>
                                        </div>
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $gateway->name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <code class="px-2 py-0.5 text-xs bg-zinc-100 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300 rounded-md font-mono">{{ $gateway->code }}</code>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($gateway->is_global_enabled)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 ring-1 ring-emerald-200 dark:ring-emerald-500/20">
                                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                            Enabled
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full bg-zinc-100 dark:bg-zinc-700 text-zinc-500 dark:text-zinc-400 ring-1 ring-zinc-200 dark:ring-zinc-600">
                                            <span class="size-1.5 rounded-full bg-zinc-400"></span>
                                            Disabled
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($gateway->is_global_enabled)
                                        <button class="px-3 py-1 text-xs font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg border border-zinc-200 dark:border-zinc-600 transition-colors">
                                            Disable
                                        </button>
                                    @else
                                        <button class="px-3 py-1 text-xs font-medium text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10 rounded-lg border border-zinc-200 dark:border-zinc-600 transition-colors">
                                            Enable
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                    No gateways configured yet.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </section>

    {{-- ─── FEATURES ────────────────────────────────────────────────────── --}}
    <section id="features" class="max-w-6xl mx-auto px-6 py-20">

        <div class="text-center mb-12">
            <p class="text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-widest mb-3">Why GatewayHub</p>
            <h2 class="text-3xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Everything you need to manage payments</h2>
            <p class="mt-3 text-zinc-500 dark:text-zinc-400 max-w-xl mx-auto">Built for platform operators who need fine-grained control over payment infrastructure without the complexity.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-4">

            @php
            $features = [
                [
                    'icon'  => 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z',
                    'title' => 'Instant toggles',
                    'desc'  => 'Enable or disable any gateway globally in one click. Changes propagate instantly — no deployments needed.',
                    'color' => 'text-amber-500',
                    'bg'    => 'bg-amber-50 dark:bg-amber-500/10',
                ],
                [
                    'icon'  => 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z',
                    'title' => 'Per-merchant control',
                    'desc'  => 'Granular permissions let you configure which gateways each merchant can access independently.',
                    'color' => 'text-blue-500',
                    'bg'    => 'bg-blue-50 dark:bg-blue-500/10',
                ],
                [
                    'icon'  => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
                    'title' => 'Audit & compliance',
                    'desc'  => 'Full activity log for every gateway change. Know who did what and when across your entire platform.',
                    'color' => 'text-emerald-500',
                    'bg'    => 'bg-emerald-50 dark:bg-emerald-500/10',
                ],
                [
                    'icon'  => 'M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z',
                    'title' => 'REST API',
                    'desc'  => 'Automate gateway management through a clean REST API. Integrate with your existing CI/CD pipelines.',
                    'color' => 'text-violet-500',
                    'bg'    => 'bg-violet-50 dark:bg-violet-500/10',
                ],
                [
                    'icon'  => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
                    'title' => 'Analytics',
                    'desc'  => 'Real-time visibility into gateway performance, failure rates, and transaction volumes.',
                    'color' => 'text-rose-500',
                    'bg'    => 'bg-rose-50 dark:bg-rose-500/10',
                ],
                [
                    'icon'  => 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0',
                    'title' => 'Smart alerts',
                    'desc'  => 'Get notified when a gateway goes down or experiences elevated error rates before your merchants notice.',
                    'color' => 'text-cyan-500',
                    'bg'    => 'bg-cyan-50 dark:bg-cyan-500/10',
                ],
            ];
            @endphp

            @foreach ($features as $feat)
            <div class="p-5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 hover:shadow-md dark:hover:shadow-zinc-900 transition-shadow">
                <div class="size-10 rounded-lg {{ $feat['bg'] }} flex items-center justify-center mb-4">
                    <svg class="size-5 {{ $feat['color'] }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $feat['icon'] }}"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-1.5">{{ $feat['title'] }}</h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">{{ $feat['desc'] }}</p>
            </div>
            @endforeach

        </div>
    </section>

    {{-- ─── SUPPORTED GATEWAYS ──────────────────────────────────────────── --}}
    <section id="gateways" class="bg-zinc-100 dark:bg-zinc-900 border-y border-zinc-200 dark:border-zinc-800 py-16">
        <div class="max-w-6xl mx-auto px-6">

            <div class="text-center mb-10">
                <p class="text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-widest mb-3">Integrations</p>
                <h2 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Works with the gateways you already use</h2>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                @forelse ($supportedGatewayNames as $gatewayName)
                <div class="flex items-center justify-center px-4 py-3 bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 text-sm font-medium text-zinc-600 dark:text-zinc-300 hover:border-blue-300 dark:hover:border-blue-600 transition-colors cursor-default">
                    {{ $gatewayName }}
                </div>
                @empty
                <div class="col-span-full flex items-center justify-center px-4 py-6 bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 text-sm font-medium text-zinc-500 dark:text-zinc-400">
                    No gateways available yet.
                </div>
                @endforelse
            </div>

        </div>
    </section>

    {{-- ─── CTA ─────────────────────────────────────────────────────────── --}}
    <section class="max-w-6xl mx-auto px-6 py-24">
        <div class="relative overflow-hidden rounded-2xl bg-blue-600 dark:bg-blue-700 px-8 py-14 text-center">

            {{-- Decorative grid inside CTA --}}
            <div class="absolute inset-0 opacity-10" style="background-image: linear-gradient(to right, white 1px, transparent 1px), linear-gradient(to bottom, white 1px, transparent 1px); background-size: 32px 32px;"></div>

            <div class="relative">
                <h2 class="text-3xl font-semibold text-white tracking-tight">Ready to take control of your payments?</h2>
                <p class="mt-3 text-blue-100 max-w-md mx-auto">Start managing your gateway infrastructure today. Free to get started, no credit card required.</p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-6 py-2.5 bg-white text-blue-700 text-sm font-semibold rounded-xl hover:bg-blue-50 transition-colors shadow-sm">
                            Create free account
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </a>
                    @endif
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-6 py-2.5 bg-blue-500 hover:bg-blue-400 text-white text-sm font-medium rounded-xl transition-colors border border-blue-400">
                            Sign in to your account
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- ─── FOOTER ──────────────────────────────────────────────────────── --}}
    <footer class="border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
        <div class="max-w-6xl mx-auto px-6 py-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <div class="size-6 rounded-md bg-blue-600 flex items-center justify-center">
                    <svg class="size-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">GatewayHub</span>
            </div>
            <p class="text-xs text-zinc-400 dark:text-zinc-500">© {{ date('Y') }} GatewayHub. All rights reserved.</p>
            <div class="flex items-center gap-4 text-xs text-zinc-400 dark:text-zinc-500">
                <a href="#" class="hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors">Privacy</a>
                <a href="#" class="hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors">Terms</a>
                <a href="#" class="hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors">Docs</a>
            </div>
        </div>
    </footer>

</body>
</html>
