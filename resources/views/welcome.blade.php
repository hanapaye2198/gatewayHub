<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <title>GatewayHub — Payment Gateway Management</title>
    <meta name="description" content="Route transactions, toggle payment providers instantly, and deliver signed webhooks — all from one control plane. Built for platform operators and merchants in the Philippines.">
    <meta property="og:title" content="GatewayHub — Payment Gateway Management">
    <meta property="og:description" content="The control plane for payment gateways. GCash, Maya, PayPal, Coins.ph, QRPh — toggle providers without redeploys.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/') }}">
    <link rel="canonical" href="{{ url('/') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=gh5" sizes="any">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|space-mono:400,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/css/welcome.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="landing-page antialiased selection:bg-blue-500/25 dark:selection:bg-blue-400/25">

    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[70] focus:rounded-lg focus:bg-blue-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
        {{ __('Skip to content') }}
    </a>

    <div class="landing-progress" id="scroll-progress" aria-hidden="true"></div>

    @php
        $navSections = [
            '#how-it-works' => 'How it works',
            '#platform' => 'Platform',
            '#features' => 'Features',
            '#gateways' => 'Gateways',
            '#security' => 'Security',
            '#developers' => 'Developers',
            '#faq' => 'FAQ',
        ];
    @endphp

    {{-- Navigation --}}
    <header id="landing-nav" class="landing-nav fixed inset-x-0 top-0 z-50 border-b border-transparent">
        <div class="landing-nav-inner mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="landing-brand flex items-center">
                <x-landing-logo-mark />
            </a>

            <nav class="hidden items-center gap-0.5 lg:flex" aria-label="{{ __('Page sections') }}">
                @foreach ($navSections as $href => $label)
                    <a href="{{ $href }}" class="landing-nav-link section-nav-link" data-section="{{ $href }}">{{ $label }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2">
                <button type="button" class="mobile-nav-toggle flex size-9 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-600 lg:hidden dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300" aria-label="{{ __('Toggle navigation menu') }}" aria-expanded="false" aria-controls="mobile-nav">
                    <svg class="size-5 menu-open" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                    <svg class="hidden size-5 menu-close" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <button type="button" class="theme-toggle flex size-9 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300" aria-label="{{ __('Toggle color theme') }}">
                    <svg class="size-4 dark:hidden" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/></svg>
                    <svg class="hidden size-4 dark:block" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/></svg>
                </button>
                @auth
                    <a href="{{ $dashboardUrl }}" class="landing-btn-primary hidden px-4 py-2 sm:inline-flex">Dashboard</a>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="hidden rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:text-blue-700 sm:inline dark:text-zinc-400 dark:hover:text-white">Sign in</a>
                    @endif
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="landing-btn-primary hidden px-4 py-2 sm:inline-flex">Get started</a>
                    @endif
                @endauth
            </div>
        </div>
        <nav id="mobile-nav" class="hidden border-t border-zinc-200 bg-white px-4 py-3 lg:hidden dark:border-zinc-800 dark:bg-zinc-950">
            <div class="flex flex-col gap-1">
                @foreach ($navSections as $href => $label)
                    <a href="{{ $href }}" class="mobile-nav-link section-nav-link rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-700 dark:text-zinc-300" data-section="{{ $href }}">{{ $label }}</a>
                @endforeach
            </div>
        </nav>
    </header>

    <main id="main-content">

    {{-- Hero --}}
    <section class="landing-mesh relative overflow-hidden pt-28 pb-16 sm:pt-32 sm:pb-20 lg:pb-28">
        <div class="landing-grid pointer-events-none absolute inset-0" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                <div class="landing-reveal-stagger landing-reveal">
                    <div class="landing-pill mb-6">
                        <span class="size-2 rounded-full {{ $isOperational ? 'bg-emerald-500 landing-pulse-dot' : 'bg-amber-500 landing-pulse-dot is-offline' }}"></span>
                        {{ $isOperational ? 'All systems operational' : 'Experiencing issues' }}
                    </div>

                    <h1 class="landing-headline">
                        The control plane for
                        <span class="landing-gradient-text">payment gateways</span>
                    </h1>

                    <p class="landing-body-text mt-6 max-w-xl text-lg leading-relaxed">
                        Route transactions, toggle providers instantly, and deliver signed webhooks — all from one dashboard. No redeploys. No downtime.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        @auth
                            <a href="{{ $dashboardUrl }}" class="landing-btn-primary">Go to Dashboard →</a>
                        @else
                            <a href="{{ route('register') }}" class="landing-btn-primary">Start for free →</a>
                            <a href="{{ route('demo.checkout') }}" class="landing-btn-secondary">Try live demo</a>
                        @endauth
                    </div>

                    <div class="mt-10 flex flex-wrap gap-2">
                        <span class="landing-pill">Signed webhooks</span>
                        <span class="landing-pill">Per-merchant access</span>
                        <span class="landing-pill">REST API</span>
                        <span class="landing-pill">Google sign-in</span>
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
                            <span class="rounded-full {{ $isOperational ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-amber-500/15 text-amber-600 dark:text-amber-400' }} px-2.5 py-1 text-xs font-semibold">
                                {{ $isOperational ? 'Online' : 'Degraded' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="landing-stat">
                                <p class="landing-stat-value" data-counter="{{ (int) ($stats['gateway_total'] ?? 0) }}">{{ number_format((int) ($stats['gateway_total'] ?? 0)) }}</p>
                                <p class="landing-stat-label">Gateways</p>
                            </div>
                            <div class="landing-stat">
                                <p class="landing-stat-value" data-counter="{{ (int) ($stats['merchant_total'] ?? 0) }}">{{ number_format((int) ($stats['merchant_total'] ?? 0)) }}</p>
                                <p class="landing-stat-label">Merchants</p>
                            </div>
                            <div class="landing-stat">
                                <p class="landing-stat-value" data-counter="{{ (int) ($stats['paid_payments_count'] ?? 0) }}">{{ number_format((int) ($stats['paid_payments_count'] ?? 0)) }}</p>
                                <p class="landing-stat-label">Paid</p>
                            </div>
                            <div class="landing-stat">
                                <p class="landing-stat-value is-compact" data-counter="{{ (float) ($stats['paid_collections'] ?? 0) }}" data-counter-prefix="₱" data-counter-decimals="2">₱{{ number_format((float) ($stats['paid_collections'] ?? 0), 2) }}</p>
                                <p class="landing-stat-label">Volume</p>
                            </div>
                        </div>

                        <div class="mt-5 space-y-2">
                            @forelse ($previewGateways->take(3) as $gateway)
                                <div class="landing-card-row">
                                    <div class="flex items-center gap-2.5">
                                        <div class="landing-icon-box">
                                            <svg class="size-3.5 text-zinc-500 dark:text-blue-300/70" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                                        </div>
                                        <span class="text-sm font-medium text-zinc-900 dark:text-slate-100">{{ $gateway->name }}</span>
                                    </div>
                                    @if ($gateway->is_global_enabled)
                                        <span class="size-2 rounded-full bg-emerald-500 landing-pulse-dot" style="animation-duration:3s"></span>
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

    {{-- Social proof strip --}}
    <section class="landing-social-proof" aria-label="{{ __('Platform metrics') }}">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-10 gap-y-4 px-4 text-center sm:px-6 lg:px-8">
            <p class="text-sm text-blue-100 dark:text-blue-200/90">
                <span class="stat-mono font-bold text-white dark:text-blue-50">{{ number_format((int) ($stats['enabled_gateway_total'] ?? 0)) }}</span> gateways active
            </p>
            <p class="hidden text-blue-300/70 sm:block dark:text-blue-400/40">|</p>
            <p class="text-sm text-blue-100 dark:text-blue-200/90">
                <span class="stat-mono font-bold text-white dark:text-blue-50">{{ number_format((int) ($stats['merchant_total'] ?? 0)) }}</span> merchants onboarded
            </p>
            <p class="hidden text-blue-300/70 sm:block dark:text-blue-400/40">|</p>
            <p class="text-sm text-blue-100 dark:text-blue-200/90">
                <span class="stat-mono font-bold text-white dark:text-blue-50">{{ number_format((int) ($stats['paid_payments_count'] ?? 0)) }}</span> successful payments
            </p>
            <p class="hidden text-blue-300/70 sm:block dark:text-blue-400/40">|</p>
            <p class="text-sm text-blue-100 dark:text-blue-200/90">5+ payment providers supported</p>
        </div>
    </section>

    {{-- Who it's for --}}
    <section class="py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="landing-reveal mx-auto mb-10 max-w-2xl text-center">
                <p class="landing-section-label">Built for</p>
                <h2 class="landing-section-title">Two sides of the payment stack</h2>
                <p class="landing-section-subtitle mt-4">Whether you operate the platform or integrate as a merchant, GatewayHub gives you the controls you need.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div class="landing-reveal landing-audience-card" style="transition-delay: 0ms">
                    <div class="mb-3 flex size-10 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/></svg>
                    </div>
                    <h3 class="landing-card-title landing-card-title-lg">Platform operators</h3>
                    <p class="landing-card-text landing-card-text-sm">Manage gateways globally, assign per-merchant access, monitor payments, and configure platform credentials — all from the admin console.</p>
                    <ul class="landing-card-list">
                        <li class="flex items-center gap-2"><span class="landing-check">✓</span> Global gateway toggles</li>
                        <li class="flex items-center gap-2"><span class="landing-check">✓</span> Merchant access matrix</li>
                        <li class="flex items-center gap-2"><span class="landing-check">✓</span> Payment exports & oversight</li>
                    </ul>
                </div>
                <div class="landing-reveal landing-audience-card" style="transition-delay: 100ms">
                    <div class="mb-3 flex size-10 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                    </div>
                    <h3 class="landing-card-title landing-card-title-lg">Merchants</h3>
                    <p class="landing-card-text landing-card-text-sm">Accept payments via REST API, manage scoped credentials, configure webhooks, and export transaction data from your merchant dashboard.</p>
                    <ul class="landing-card-list">
                        <li class="flex items-center gap-2"><span class="landing-check">✓</span> Payment creation API</li>
                        <li class="flex items-center gap-2"><span class="landing-check">✓</span> Per-merchant API keys</li>
                        <li class="flex items-center gap-2"><span class="landing-check">✓</span> Webhook configuration</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section id="how-it-works" class="landing-section-alt border-y py-20 sm:py-28 scroll-mt-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="landing-reveal mx-auto mb-14 max-w-2xl text-center">
                <p class="landing-section-label">How it works</p>
                <h2 class="landing-section-title">Up and running in three steps</h2>
                <p class="landing-section-subtitle mt-4">From sign-up to your first payment in minutes, not weeks.</p>
            </div>

            <div class="landing-steps-grid landing-reveal landing-reveal-stagger grid gap-5 md:grid-cols-3 lg:gap-6">
                @foreach ([
                    [
                        'step' => '01',
                        'tone' => 'blue',
                        'title' => 'Create your account',
                        'desc' => 'Sign up with email or Google. Complete a guided onboarding flow to set up your business profile.',
                        'icon' => 'M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM3 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 019.374 21c-2.331 0-4.512-.645-6.374-1.766z',
                    ],
                    [
                        'step' => '02',
                        'tone' => 'cyan',
                        'title' => 'Enable gateways',
                        'desc' => 'Toggle payment providers globally or per merchant. Assign GCash, Maya, PayPal, and more without code changes.',
                        'icon' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
                    ],
                    [
                        'step' => '03',
                        'tone' => 'emerald',
                        'title' => 'Integrate & go live',
                        'desc' => 'Use the REST API to create payments, verify status, and receive signed webhook notifications.',
                        'icon' => 'M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5',
                    ],
                ] as $step)
                <article class="landing-step landing-step--{{ $step['tone'] }}">
                    <div class="landing-step-shine" aria-hidden="true"></div>
                    <div class="landing-step-accent" aria-hidden="true"></div>

                    <div class="relative z-10 flex h-full flex-col">
                        <div class="mb-5 flex items-start justify-between gap-4">
                            <div class="landing-step-icon">
                                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $step['icon'] }}"/>
                                </svg>
                            </div>
                            <span class="landing-step-badge">{{ $step['step'] }}</span>
                        </div>

                        <h3 class="landing-card-title landing-card-title-lg">{{ $step['title'] }}</h3>
                        <p class="landing-card-text landing-card-text-sm mt-3">{{ $step['desc'] }}</p>
                    </div>
                </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Platform preview --}}
    <section id="platform" class="relative py-20 sm:py-28 scroll-mt-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="landing-reveal mx-auto mb-10 max-w-2xl text-center">
                <p class="landing-section-label">Admin console</p>
                <h2 class="landing-section-title">One dashboard to rule them all</h2>
                <p class="landing-section-subtitle mt-4">Enable or disable any gateway globally. Changes propagate instantly across every merchant.</p>
            </div>

            <div class="landing-reveal landing-glass overflow-hidden">
                <div class="flex items-center gap-2 border-b border-zinc-200/80 bg-zinc-50/80 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950/80">
                    <span class="size-3 rounded-full bg-red-400"></span>
                    <span class="size-3 rounded-full bg-amber-400"></span>
                    <span class="size-3 rounded-full bg-emerald-400"></span>
                    <div class="mx-auto max-w-sm flex-1 rounded-md border border-zinc-200 bg-white px-3 py-1 text-center font-mono text-xs text-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-500">
                        {{ parse_url(url('/admin/gateways'), PHP_URL_HOST) }}/admin/gateways
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
                                <span data-counter="{{ (int) ($stats['enabled_gateway_total'] ?? 0) }}" data-counter-animated="false">{{ number_format((int) ($stats['enabled_gateway_total'] ?? 0)) }}</span> active
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
                            <tbody id="platform-demo-rows">
                                @forelse ($previewGateways as $gateway)
                                <tr class="platform-demo-row border-b border-zinc-100 last:border-0 transition-colors hover:bg-zinc-50/80 dark:border-zinc-800 dark:hover:bg-zinc-800/40 {{ $loop->first ? 'landing-demo-toggle' : '' }} {{ $gateway->is_global_enabled ? 'is-enabled' : 'is-disabled' }}" data-enabled="{{ $gateway->is_global_enabled ? '1' : '0' }}">
                                    <td class="px-4 py-3">
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $gateway->name }}</span>
                                    </td>
                                    <td class="hidden px-4 py-3 sm:table-cell">
                                        <code class="rounded-md bg-zinc-100 px-2 py-0.5 font-mono text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $gateway->code }}</code>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="landing-demo-status inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1">
                                            <span class="landing-demo-dot size-1.5 rounded-full"></span>
                                            <span class="landing-demo-label">{{ $gateway->is_global_enabled ? 'Enabled' : 'Disabled' }}</span>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="landing-demo-action rounded-lg border border-zinc-200 px-3 py-1 text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:text-zinc-400">
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
    <section id="features" class="relative scroll-mt-24 overflow-hidden py-20 sm:py-28">
        <div class="landing-feature-bg pointer-events-none absolute inset-0" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="landing-reveal mx-auto mb-14 max-w-2xl text-center">
                <p class="landing-section-label">Capabilities</p>
                <h2 class="landing-section-title">Built for platform operators</h2>
                <p class="landing-section-subtitle mt-4">Fine-grained payment infrastructure control without the operational overhead.</p>
            </div>

            <div class="landing-reveal landing-reveal-stagger landing-feature-grid grid gap-5 md:grid-cols-2 lg:gap-6">
                @php
                $features = [
                    [
                        'title' => 'Instant gateway toggles',
                        'desc' => 'Enable or disable any provider globally in one click. Changes propagate instantly across every merchant.',
                        'large' => true,
                        'tag' => 'Control plane',
                        'tone' => 'amber',
                        'icon' => 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z',
                        'points' => [
                            ['icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => 'Global on/off in one click'],
                            ['icon' => 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z', 'text' => 'Instant propagation to merchants'],
                            ['icon' => 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182', 'text' => 'No redeploys or code changes'],
                        ],
                    ],
                    [
                        'title' => 'Per-merchant control',
                        'desc' => 'Assign gateway access independently per business. Fine-grained access matrix for multi-tenant platforms.',
                        'large' => false,
                        'tag' => 'Access',
                        'tone' => 'blue',
                        'icon' => 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z',
                        'points' => [
                            ['icon' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z', 'text' => 'Per-merchant matrix'],
                            ['icon' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z', 'text' => 'Scoped permissions'],
                        ],
                    ],
                    [
                        'title' => 'Webhook delivery',
                        'desc' => 'Signed payment notifications to merchant endpoints with HMAC verification and replay protection.',
                        'large' => false,
                        'tag' => 'Events',
                        'tone' => 'cyan',
                        'icon' => 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0',
                        'points' => [
                            ['icon' => 'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z', 'text' => 'HMAC-signed payloads'],
                            ['icon' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => 'Replay protection'],
                        ],
                    ],
                    [
                        'title' => 'Activity tracking',
                        'desc' => 'Track gateway configuration changes and admin actions for operational visibility.',
                        'large' => false,
                        'tag' => 'Audit',
                        'tone' => 'emerald',
                        'icon' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
                        'points' => [
                            ['icon' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => 'Config change history'],
                            ['icon' => 'M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5', 'text' => 'Admin action logs'],
                        ],
                    ],
                    [
                        'title' => 'REST API',
                        'desc' => 'Automate gateway management and integrate with your CI/CD pipelines.',
                        'large' => false,
                        'tag' => 'Developers',
                        'tone' => 'violet',
                        'icon' => 'M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z',
                        'points' => [
                            ['icon' => 'M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5', 'text' => 'CI/CD automation'],
                            ['icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z', 'text' => 'OpenAPI-ready endpoints'],
                        ],
                    ],
                    [
                        'title' => 'Payment analytics',
                        'desc' => 'Real-time visibility into volumes, failure rates, and gateway performance across all merchants.',
                        'large' => true,
                        'tag' => 'Insights',
                        'tone' => 'rose',
                        'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
                        'points' => [
                            ['icon' => 'M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941', 'text' => 'Volume & success rates'],
                            ['icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75z', 'text' => 'Gateway performance'],
                            ['icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z', 'text' => 'Failure rate alerts'],
                        ],
                    ],
                ];
                @endphp

                @foreach ($features as $feature)
                <article class="landing-feature-card landing-feature-card--{{ $feature['tone'] }} {{ $feature['large'] ? 'landing-feature-card-lg' : '' }}">
                    <div class="landing-feature-card-shine" aria-hidden="true"></div>
                    <div class="landing-feature-card-accent" aria-hidden="true"></div>

                    <div class="relative z-10 flex h-full flex-col">
                        <div class="mb-5 flex items-start justify-between gap-4">
                            <div class="landing-feature-icon">
                                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $feature['icon'] }}"/>
                                </svg>
                            </div>
                            <span class="landing-feature-tag">{{ $feature['tag'] }}</span>
                        </div>

                        <h3 class="landing-card-title landing-card-title-lg">{{ $feature['title'] }}</h3>
                        <p class="landing-card-text landing-card-text-sm">{{ $feature['desc'] }}</p>

                        <ul class="landing-feature-points mt-auto pt-5">
                            @foreach ($feature['points'] as $point)
                            <li class="landing-feature-point">
                                <span class="landing-feature-point-icon" aria-hidden="true">
                                    <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $point['icon'] }}"/>
                                    </svg>
                                </span>
                                <span>{{ $point['text'] }}</span>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Gateways --}}
    <section id="gateways" class="landing-section-alt border-y py-16 sm:py-20 scroll-mt-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="landing-reveal mb-10 text-center">
                <p class="landing-section-label">Integrations</p>
                <h2 class="landing-section-title">Works with your gateways</h2>
                <p class="landing-section-subtitle mx-auto mt-3 max-w-lg">GCash, Maya, PayPal, Coins.ph, QRPh — toggle any provider without code changes.</p>
            </div>

            <div class="landing-reveal hidden space-y-4 overflow-hidden md:block">
                <div class="[mask-image:linear-gradient(to_right,transparent,black_8%,black_92%,transparent)]">
                    <div class="landing-marquee flex w-max gap-3">
                        @foreach (collect($gatewayCatalog)->merge($gatewayCatalog) as $gateway)
                            <div class="landing-gateway-card">
                                @if ($gateway['logo'])
                                    <img src="{{ asset($gateway['logo']) }}" alt="{{ $gateway['name'] }}" class="h-6 w-auto max-w-[4.5rem] object-contain" loading="lazy">
                                @else
                                    <div class="landing-gateway-badge" style="background-color: {{ $gateway['color'] }}">{{ strtoupper(substr($gateway['name'], 0, 1)) }}</div>
                                @endif
                                <div>
                                    <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $gateway['name'] }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $gateway['tag'] }}</p>
                                </div>
                                @if ($enabledGatewayCodes->contains($gateway['code']))
                                    <span class="ms-2 size-2 shrink-0 rounded-full bg-emerald-500" title="Active"></span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="[mask-image:linear-gradient(to_right,transparent,black_8%,black_92%,transparent)]">
                    <div class="landing-marquee-reverse flex w-max gap-3">
                        @foreach (collect($gatewayCatalog)->reverse()->merge(collect($gatewayCatalog)->reverse()) as $gateway)
                            <div class="landing-gateway-card">
                                @if ($gateway['logo'])
                                    <img src="{{ asset($gateway['logo']) }}" alt="{{ $gateway['name'] }}" class="h-6 w-auto max-w-[4.5rem] object-contain" loading="lazy">
                                @else
                                    <div class="landing-gateway-badge" style="background-color: {{ $gateway['color'] }}">{{ strtoupper(substr($gateway['name'], 0, 1)) }}</div>
                                @endif
                                <div>
                                    <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $gateway['name'] }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $gateway['tag'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="landing-reveal grid grid-cols-1 gap-3 sm:grid-cols-2 md:hidden">
                @foreach ($gatewayCatalog as $gateway)
                    <div class="landing-gateway-card">
                        @if ($gateway['logo'])
                            <img src="{{ asset($gateway['logo']) }}" alt="{{ $gateway['name'] }}" class="h-6 w-auto max-w-[4.5rem] object-contain" loading="lazy">
                        @else
                            <div class="landing-gateway-badge" style="background-color: {{ $gateway['color'] }}">{{ strtoupper(substr($gateway['name'], 0, 1)) }}</div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $gateway['name'] }}</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $gateway['tag'] }}</p>
                        </div>
                        @if ($enabledGatewayCodes->contains($gateway['code']))
                            <span class="size-2 shrink-0 rounded-full bg-emerald-500"></span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Security --}}
    <section id="security" class="py-20 sm:py-28 scroll-mt-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="landing-reveal mx-auto mb-14 max-w-2xl text-center">
                <p class="landing-section-label">Security</p>
                <h2 class="landing-section-title">Enterprise-grade by default</h2>
                <p class="landing-section-subtitle mt-4">Payment infrastructure demands rigorous security. GatewayHub bakes it in from day one.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['title' => 'HMAC webhooks', 'desc' => 'Every payment notification is signed so merchants can verify authenticity before processing.', 'icon' => 'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z'],
                    ['title' => 'Replay protection', 'desc' => 'Webhook timestamps and idempotency checks prevent duplicate or replayed payment events.', 'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['title' => 'Scoped API keys', 'desc' => 'Per-merchant credentials with masked display. Keys are hashed and never stored in plain text.', 'icon' => 'M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z'],
                    ['title' => 'Idempotent processing', 'desc' => 'Payment records are preserved for audit. Status transitions are safe against duplicate webhooks.', 'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                ] as $index => $item)
                <div class="landing-reveal landing-security-card" style="transition-delay: {{ $index * 80 }}ms">
                    <div class="mb-3 flex size-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <svg class="size-4.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                    </div>
                    <h3 class="landing-card-title landing-card-title-sm">{{ $item['title'] }}</h3>
                    <p class="landing-card-text landing-card-text-xs">{{ $item['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Developers --}}
    <section id="developers" class="landing-section-alt border-t py-20 sm:py-28 scroll-mt-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                <div class="landing-reveal">
                    <p class="landing-section-label">Developers</p>
                    <h2 class="landing-section-title">Integrate in minutes</h2>
                    <p class="landing-section-subtitle mt-4">RESTful endpoints, per-merchant API keys, and signed webhooks. Full docs included.</p>
                    <ul class="mt-8 space-y-3">
                        @foreach (['Payment creation & status APIs', 'Scoped API credentials per merchant', 'HMAC webhook signature verification', 'CSV payment exports'] as $point)
                        <li class="flex items-center gap-3 text-sm landing-card-text">
                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <svg class="size-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            </span>
                            {{ $point }}
                        </li>
                        @endforeach
                    </ul>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('public.api-docs') }}" class="landing-btn-secondary">View API docs →</a>
                        <a href="{{ route('demo.checkout') }}" class="landing-btn-ghost">Try demo checkout</a>
                    </div>
                </div>

                <div class="landing-reveal overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-950 shadow-2xl ring-1 ring-white/10" style="transition-delay: 120ms">
                    <div class="flex items-center justify-between border-b border-zinc-800 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="size-2.5 rounded-full bg-red-500/80"></span>
                            <span class="size-2.5 rounded-full bg-amber-500/80"></span>
                            <span class="size-2.5 rounded-full bg-emerald-500/80"></span>
                            <span class="ms-2 font-mono text-xs text-zinc-500" id="code-filename">create-payment.sh</span>
                        </div>
                        <div class="flex gap-1" role="tablist" aria-label="{{ __('Code examples') }}">
                            <button type="button" class="landing-code-tab is-active" data-tab="create" role="tab" aria-selected="true">Create</button>
                            <button type="button" class="landing-code-tab" data-tab="status" role="tab" aria-selected="false">Status</button>
                            <button type="button" class="landing-code-tab" data-tab="webhook" role="tab" aria-selected="false">Webhook</button>
                        </div>
                    </div>
                    <div class="relative">
                        <button type="button" class="landing-copy-btn" id="code-copy-btn" aria-label="{{ __('Copy code') }}">Copy</button>
                        <pre class="overflow-x-auto p-5 text-[0.7rem] leading-relaxed sm:text-xs"><code class="font-mono text-zinc-300" id="code-block"><span class="text-violet-400">curl</span> -X POST <span class="text-emerald-400">"{{ url('/api/payments') }}"</span> \
  -H <span class="text-amber-300">"Authorization: Bearer sk_live_••••"</span> \
  -d <span class="text-sky-300">'{"amount":1500,"currency":"PHP","gateway_code":"gcash"}'</span><span class="landing-cursor" id="code-cursor"></span></code></pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="py-20 sm:py-28 scroll-mt-24">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="landing-reveal mb-10 text-center">
                <p class="landing-section-label">FAQ</p>
                <h2 class="landing-section-title">Common questions</h2>
            </div>

            <div class="landing-reveal space-y-3">
                @foreach ([
                    ['q' => 'Do I need to redeploy when toggling a gateway?', 'a' => 'No. Gateway toggles take effect instantly across the platform. Merchants see the change on their next payment request without any code or config changes on their end.'],
                    ['q' => 'Which payment providers are supported?', 'a' => 'GatewayHub supports GCash, Maya, PayPal, Coins.ph, and QRPh out of the box. Additional providers can be added through the driver architecture.'],
                    ['q' => 'How are webhooks signed?', 'a' => 'Each merchant receives a webhook secret during onboarding. GatewayHub signs outbound notifications with HMAC so you can verify the payload before processing it.'],
                    ['q' => 'Can I use GatewayHub as a merchant only?', 'a' => 'Yes. Merchants get their own dashboard with API credentials, webhook settings, payment history, and CSV exports. Platform admin features are separate.'],
                    ['q' => 'Is there a free tier?', 'a' => 'You can create a free account and start integrating immediately. Check the dashboard for current usage limits and pricing details.'],
                ] as $faq)
                <details class="landing-faq group">
                    <summary>
                        {{ $faq['q'] }}
                        <svg class="size-4 shrink-0 text-zinc-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                    </summary>
                    <div class="landing-faq-content">{{ $faq['a'] }}</div>
                </details>
                @endforeach
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
                        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-bold text-blue-800 shadow-lg transition hover:-translate-y-0.5 hover:bg-blue-50">Create free account →</a>
                    @endif
                    <a href="{{ route('demo.checkout') }}" class="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/5 px-6 py-3 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/10">Try live demo</a>
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/5 px-6 py-3 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/10">Sign in</a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    </main>

    {{-- Footer --}}
    <footer class="landing-footer">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="landing-footer-inner">
                <div class="landing-brand flex items-center">
                    <x-landing-logo-mark size="sm" />
                </div>
                <p class="landing-footer-copy">© {{ date('Y') }} GatewayHub. All rights reserved.</p>
                <div class="landing-footer-links landing-card-text">
                    <a href="{{ route('public.api-docs') }}" class="hover:text-blue-600 dark:hover:text-blue-400">API Docs</a>
                    <a href="{{ route('demo.checkout') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Demo</a>
                    <a href="#features" class="hover:text-blue-600 dark:hover:text-blue-400">Features</a>
                    <a href="#faq" class="hover:text-blue-600 dark:hover:text-blue-400">FAQ</a>
                    <a href="{{ route('health') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Status</a>
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Sign in</a>
                    @endif
                </div>
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
            entries.forEach((e) => {
                if (e.isIntersecting) {
                    e.target.classList.add('is-visible');
                    revealObs.unobserve(e.target);
                }
            });
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

        const sectionIds = ['#how-it-works', '#platform', '#features', '#gateways', '#security', '#developers', '#faq'];
        const sectionEls = sectionIds.map((id) => document.querySelector(id)).filter(Boolean);
        const navLinks = document.querySelectorAll('.section-nav-link');
        const updateActiveSection = () => {
            let current = sectionIds[0];
            sectionEls.forEach((el, i) => {
                const rect = el.getBoundingClientRect();
                if (rect.top <= 120) current = sectionIds[i];
            });
            navLinks.forEach((link) => {
                const isActive = link.dataset.section === current;
                link.classList.toggle('is-active', isActive);
                if (link.classList.contains('landing-nav-link')) {
                    link.setAttribute('aria-current', isActive ? 'true' : 'false');
                }
            });
        };
        window.addEventListener('scroll', updateActiveSection, { passive: true });
        updateActiveSection();

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

        const demoRow = document.querySelector('.landing-demo-toggle');
        if (demoRow) {
            let enabled = demoRow.dataset.enabled === '1';
            setInterval(() => {
                enabled = !enabled;
                demoRow.classList.toggle('is-enabled', enabled);
                demoRow.classList.toggle('is-disabled', !enabled);
                demoRow.querySelector('.landing-demo-label').textContent = enabled ? 'Enabled' : 'Disabled';
                demoRow.querySelector('.landing-demo-action').textContent = enabled ? 'Disable' : 'Enable';
            }, 3000);
        }

        const codeExamples = {
            create: {
                filename: 'create-payment.sh',
                code: `<span class="text-violet-400">curl</span> -X POST <span class="text-emerald-400">"{{ url('/api/payments') }}"</span> \\\n  -H <span class="text-amber-300">"Authorization: Bearer sk_live_••••"</span> \\\n  -d <span class="text-sky-300">'{"amount":1500,"currency":"PHP","gateway_code":"gcash"}'</span>`,
            },
            status: {
                filename: 'check-status.sh',
                code: `<span class="text-violet-400">curl</span> <span class="text-emerald-400">"{{ url('/api/payments') }}/txn_abc123"</span> \\\n  -H <span class="text-amber-300">"Authorization: Bearer sk_live_••••"</span>`,
            },
            webhook: {
                filename: 'verify-webhook.php',
                code: `<span class="text-violet-400">$</span><span class="text-zinc-400">signature</span> = <span class="text-amber-300">hash_hmac</span>(<span class="text-sky-300">'sha256'</span>, <span class="text-zinc-400">$payload</span>, <span class="text-zinc-400">$webhookSecret</span>);\n<span class="text-violet-400">$</span><span class="text-zinc-400">valid</span> = <span class="text-amber-300">hash_equals</span>(<span class="text-zinc-400">$signature</span>, <span class="text-zinc-400">$_SERVER</span>[<span class="text-sky-300">'HTTP_X_SIGNATURE'</span>]);`,
            },
        };
        const codeBlock = document.getElementById('code-block');
        const codeFilename = document.getElementById('code-filename');
        const codeCursor = document.getElementById('code-cursor');
        document.querySelectorAll('.landing-code-tab').forEach((tab) => {
            tab.addEventListener('click', () => {
                const key = tab.dataset.tab;
                const example = codeExamples[key];
                if (!example) return;
                document.querySelectorAll('.landing-code-tab').forEach((t) => {
                    const active = t === tab;
                    t.classList.toggle('is-active', active);
                    t.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                codeFilename.textContent = example.filename;
                codeBlock.innerHTML = example.code;
                codeCursor?.classList.add('is-done');
            });
        });

        const copyBtn = document.getElementById('code-copy-btn');
        copyBtn?.addEventListener('click', async () => {
            const text = codeBlock?.textContent ?? '';
            try {
                await navigator.clipboard.writeText(text);
                copyBtn.textContent = 'Copied!';
                copyBtn.classList.add('is-copied');
                setTimeout(() => {
                    copyBtn.textContent = 'Copy';
                    copyBtn.classList.remove('is-copied');
                }, 2000);
            } catch (_) {}
        });

        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            const typewriterObs = new IntersectionObserver((entries) => {
                entries.forEach((e) => {
                    if (!e.isIntersecting) return;
                    const cursor = document.getElementById('code-cursor');
                    if (!cursor || cursor.classList.contains('is-done')) return;
                    const full = codeBlock?.textContent ?? '';
                    codeBlock.textContent = '';
                    let i = 0;
                    const step = () => {
                        if (i < full.length) {
                            codeBlock.textContent += full[i++];
                            requestAnimationFrame(step);
                        } else {
                            codeBlock.innerHTML = codeExamples.create.code;
                            cursor.classList.add('is-done');
                        }
                    };
                    step();
                    typewriterObs.unobserve(e.target);
                });
            }, { threshold: 0.5 });
            const codePanel = document.getElementById('code-block')?.closest('.landing-reveal');
            if (codePanel) typewriterObs.observe(codePanel);
        }
    </script>
</body>
</html>
