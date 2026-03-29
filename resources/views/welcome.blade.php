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
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|space-mono:400,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
    <style>
        :root {
            --accent: #2563eb;
            --accent-light: #dbeafe;
            --accent-dark: #1d4ed8;
        }

        :root:not(.dark) {
            color-scheme: light;
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

        html { scroll-behavior: smooth; }

        /* Fade-up entrance (hero) */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-up { opacity: 0; animation: fadeUp 0.75s cubic-bezier(0.22, 1, 0.36, 1) forwards; }
        .fade-up-1 { animation-delay: 0.04s; }
        .fade-up-2 { animation-delay: 0.12s; }
        .fade-up-3 { animation-delay: 0.2s; }
        .fade-up-4 { animation-delay: 0.28s; }
        .fade-up-5 { animation-delay: 0.36s; }
        .fade-up-6 { animation-delay: 0.44s; }

        /* Scroll reveal */
        .reveal {
            opacity: 0;
            transform: translateY(2rem);
            transition:
                opacity 0.7s cubic-bezier(0.22, 1, 0.36, 1),
                transform 0.7s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .reveal.reveal-visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Hero orbs */
        @keyframes float-orb {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(3%, -4%) scale(1.06); }
            66% { transform: translate(-2%, 2%) scale(0.98); }
        }
        .animate-float-orb {
            animation: float-orb 22s ease-in-out infinite;
        }
        .animate-float-orb-delayed {
            animation: float-orb 28s ease-in-out infinite;
            animation-delay: -8s;
        }

        /* ─── Hero: advanced motion ─── */
        .hero-section {
            isolation: isolate;
        }

        @keyframes aurora-1 {
            0%, 100% { transform: translate(-15%, -10%) scale(1) rotate(0deg); opacity: 0.55; }
            50% { transform: translate(20%, 15%) scale(1.15) rotate(8deg); opacity: 0.85; }
        }
        @keyframes aurora-2 {
            0%, 100% { transform: translate(10%, 20%) scale(1.1) rotate(-6deg); opacity: 0.5; }
            50% { transform: translate(-25%, -5%) scale(0.95) rotate(4deg); opacity: 0.75; }
        }
        @keyframes aurora-3 {
            0%, 100% { transform: translate(5%, -20%) scale(1.05) rotate(3deg); opacity: 0.45; }
            50% { transform: translate(-10%, 25%) scale(1.2) rotate(-5deg); opacity: 0.7; }
        }

        .hero-aurora-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
            will-change: transform, opacity;
        }
        .hero-aurora-blob:nth-child(1) {
            width: min(55vw, 28rem);
            height: min(55vw, 28rem);
            left: -5%;
            top: 5%;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.45) 0%, transparent 70%);
            animation: aurora-1 14s ease-in-out infinite;
        }
        .dark .hero-aurora-blob:nth-child(1) {
            background: radial-gradient(circle, rgba(59, 130, 246, 0.35) 0%, transparent 70%);
        }
        .hero-aurora-blob:nth-child(2) {
            width: min(50vw, 24rem);
            height: min(50vw, 24rem);
            right: -8%;
            top: 35%;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.4) 0%, transparent 68%);
            animation: aurora-2 18s ease-in-out infinite;
            animation-delay: -4s;
        }
        .dark .hero-aurora-blob:nth-child(2) {
            background: radial-gradient(circle, rgba(167, 139, 250, 0.32) 0%, transparent 68%);
        }
        .hero-aurora-blob:nth-child(3) {
            width: min(45vw, 22rem);
            height: min(45vw, 22rem);
            left: 25%;
            bottom: -10%;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.35) 0%, transparent 65%);
            animation: aurora-3 16s ease-in-out infinite;
            animation-delay: -7s;
        }
        .dark .hero-aurora-blob:nth-child(3) {
            background: radial-gradient(circle, rgba(56, 189, 248, 0.28) 0%, transparent 65%);
        }

        @keyframes hero-grid-drift {
            0% { background-position: 0 0, 0 0; }
            100% { background-position: 40px 40px, 40px 40px; }
        }
        .hero-grid-drift {
            animation: hero-grid-drift 45s linear infinite;
        }

        @keyframes hero-beam {
            0% { transform: translateX(-60%) skewX(-12deg); opacity: 0; }
            15% { opacity: 0.35; }
            50% { transform: translateX(0%) skewX(-12deg); opacity: 0.2; }
            85% { opacity: 0.35; }
            100% { transform: translateX(60%) skewX(-12deg); opacity: 0; }
        }
        .hero-beam {
            position: absolute;
            left: 50%;
            top: 28%;
            width: 120%;
            max-width: 56rem;
            height: 140%;
            margin-left: -50%;
            background: linear-gradient(
                105deg,
                transparent 35%,
                rgba(255, 255, 255, 0.07) 48%,
                rgba(147, 197, 253, 0.12) 50%,
                rgba(255, 255, 255, 0.06) 52%,
                transparent 65%
            );
            pointer-events: none;
            animation: hero-beam 11s ease-in-out infinite;
        }
        .dark .hero-beam {
            background: linear-gradient(
                105deg,
                transparent 35%,
                rgba(255, 255, 255, 0.04) 48%,
                rgba(96, 165, 250, 0.08) 50%,
                rgba(255, 255, 255, 0.03) 52%,
                transparent 65%
            );
        }

        @keyframes hero-word-in {
            from {
                opacity: 0;
                transform: translateY(0.75em) rotateX(-55deg);
                filter: blur(6px);
            }
            to {
                opacity: 1;
                transform: translateY(0) rotateX(0);
                filter: blur(0);
            }
        }
        @keyframes hero-line-2-in {
            from {
                opacity: 0;
                transform: translateY(1.1em) scale(0.94);
                filter: blur(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
                filter: blur(0);
            }
        }

        .hero-title {
            perspective: 1200px;
        }
        .hero-word {
            display: inline-block;
            opacity: 0;
            animation: hero-word-in 0.85s cubic-bezier(0.22, 1, 0.36, 1) forwards;
            transform-origin: 50% 100%;
        }
        .hero-word:nth-child(1) { animation-delay: 0.1s; }
        .hero-word:nth-child(2) { animation-delay: 0.18s; }
        .hero-word:nth-child(3) { animation-delay: 0.26s; }
        .hero-word:nth-child(4) { animation-delay: 0.34s; }

        .hero-gradient-line {
            display: inline-block;
            opacity: 0;
            animation: hero-line-2-in 1s cubic-bezier(0.22, 1, 0.36, 1) 0.42s forwards;
            transform-origin: 50% 50%;
        }

        @keyframes hero-badge-shimmer {
            0% { background-position: -200% center; }
            100% { background-position: 200% center; }
        }
        .hero-badge {
            position: relative;
            background-image: linear-gradient(
                105deg,
                rgba(255, 255, 255, 0.95) 0%,
                rgba(239, 246, 255, 0.98) 45%,
                rgba(255, 255, 255, 0.95) 90%
            );
            background-size: 200% 100%;
            animation: hero-badge-shimmer 6s ease-in-out infinite;
        }
        .dark .hero-badge {
            background-image: linear-gradient(
                105deg,
                rgba(24, 24, 27, 0.95) 0%,
                rgba(39, 39, 42, 0.98) 45%,
                rgba(24, 24, 27, 0.95) 90%
            );
        }

        @keyframes hero-float-y {
            0%, 100% { transform: translateY(0) rotate(-1deg); }
            50% { transform: translateY(-12px) rotate(1deg); }
        }
        @keyframes hero-float-y-reverse {
            0%, 100% { transform: translateY(0) rotate(1deg); }
            50% { transform: translateY(14px) rotate(-1deg); }
        }
        @keyframes hero-bar-dance {
            0%, 100% { transform: scaleY(0.35); opacity: 0.6; }
            50% { transform: scaleY(1); opacity: 1; }
        }

        .hero-float-card {
            animation: hero-float-y 7s ease-in-out infinite;
            will-change: transform;
        }
        .hero-float-card--alt {
            animation: hero-float-y-reverse 8s ease-in-out infinite;
            animation-delay: -2s;
        }

        .hero-mini-bar {
            transform-origin: bottom center;
            animation: hero-bar-dance 2.4s ease-in-out infinite;
        }
        .hero-mini-bar:nth-child(1) { animation-delay: 0s; }
        .hero-mini-bar:nth-child(2) { animation-delay: 0.2s; }
        .hero-mini-bar:nth-child(3) { animation-delay: 0.4s; }
        .hero-mini-bar:nth-child(4) { animation-delay: 0.1s; }
        .hero-mini-bar:nth-child(5) { animation-delay: 0.35s; }

        @keyframes hero-stat-glow {
            0%, 100% { box-shadow: 0 0 0 0 transparent; }
            50% { box-shadow: 0 0 28px -4px rgba(59, 130, 246, 0.15); }
        }
        @keyframes hero-stat-glow-dark {
            0%, 100% { box-shadow: 0 0 0 0 transparent; }
            50% { box-shadow: 0 0 36px -4px rgba(96, 165, 250, 0.14); }
        }
        .hero-stats-wrap {
            animation: hero-stat-glow 5s ease-in-out infinite;
        }
        .dark .hero-stats-wrap {
            animation-name: hero-stat-glow-dark;
        }

        @keyframes hero-cta-breathe {
            0%, 100% {
                box-shadow:
                    0 10px 28px -6px rgba(37, 99, 235, 0.38),
                    0 0 0 1px rgba(255, 255, 255, 0.08) inset;
            }
            50% {
                box-shadow:
                    0 14px 36px -6px rgba(37, 99, 235, 0.48),
                    0 0 0 1px rgba(255, 255, 255, 0.12) inset;
            }
        }
        @keyframes hero-cta-breathe-dark {
            0%, 100% {
                box-shadow:
                    0 10px 28px -6px rgba(59, 130, 246, 0.25),
                    0 0 0 1px rgba(255, 255, 255, 0.06) inset;
            }
            50% {
                box-shadow:
                    0 14px 38px -6px rgba(96, 165, 250, 0.32),
                    0 0 0 1px rgba(255, 255, 255, 0.08) inset;
            }
        }
        .hero-cta-primary {
            animation: hero-cta-breathe 3.5s ease-in-out infinite;
        }
        .dark .hero-cta-primary {
            animation-name: hero-cta-breathe-dark;
        }

        /* Gradient headline */
        @keyframes gradient-shift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        .text-gradient-live {
            background: linear-gradient(105deg, #2563eb 0%, #6366f1 35%, #7c3aed 55%, #2563eb 100%);
            background-size: 220% auto;
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            animation: gradient-shift 7s ease infinite;
        }
        .dark .text-gradient-live {
            background: linear-gradient(105deg, #60a5fa 0%, #818cf8 35%, #c4b5fd 55%, #60a5fa 100%);
            background-size: 220% auto;
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        /* CTA shimmer */
        @keyframes cta-shimmer {
            0% { transform: translateX(-100%) skewX(-12deg); }
            100% { transform: translateX(200%) skewX(-12deg); }
        }
        .cta-shine::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(105deg, transparent 40%, rgba(255,255,255,0.12) 50%, transparent 60%);
            animation: cta-shimmer 5s ease-in-out infinite;
            pointer-events: none;
        }

        /* Gateway card hover */
        .gateway-card {
            transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.25s ease;
        }
        .gateway-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
        }
        .dark .gateway-card:hover {
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.45);
        }

        /* Feature cards */
        .feature-card {
            transition:
                transform 0.3s cubic-bezier(0.22, 1, 0.36, 1),
                box-shadow 0.3s ease,
                border-color 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 40px -12px rgba(37, 99, 235, 0.12);
        }
        .dark .feature-card:hover {
            box-shadow: 0 16px 40px -12px rgba(0, 0, 0, 0.5);
        }

        .integration-pill {
            transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1), border-color 0.25s ease, box-shadow 0.25s ease;
        }
        .integration-pill:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 8px 20px -6px rgba(37, 99, 235, 0.2);
        }
        .dark .integration-pill:hover {
            box-shadow: 0 8px 24px -6px rgba(59, 130, 246, 0.15);
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .fade-up,
            .animate-float-orb,
            .animate-float-orb-delayed,
            .text-gradient-live,
            .cta-shine::after,
            .hero-aurora-blob,
            .hero-grid-drift,
            .hero-beam,
            .hero-badge,
            .hero-float-card,
            .hero-float-card--alt,
            .hero-mini-bar,
            .hero-stats-wrap,
            .hero-cta-primary {
                animation: none !important;
            }
            .fade-up {
                opacity: 1;
                transform: none;
            }
            .hero-word,
            .hero-gradient-line {
                opacity: 1 !important;
                transform: none !important;
                filter: none !important;
                animation: none !important;
            }
            .hero-badge {
                background: rgba(255, 255, 255, 0.95) !important;
                background-size: auto !important;
            }
            .dark .hero-badge {
                background: rgba(24, 24, 27, 0.95) !important;
            }
            .text-gradient-live {
                background: none;
                -webkit-background-clip: unset;
                background-clip: unset;
                color: #2563eb;
            }
            .dark .text-gradient-live {
                background: none;
                color: #93c5fd;
            }
            .reveal {
                opacity: 1;
                transform: none;
                transition: none;
            }
            .cta-shine::after { display: none; }
            .feature-card:hover,
            .integration-pill:hover,
            .gateway-card:hover {
                transform: none;
            }
        }

        /* Stat number font */
        .stat-num { font-family: 'Space Mono', monospace; }

        /* Glowing badge */
        .badge-glow {
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .dark .badge-glow {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.22);
        }

        body { font-family: 'Instrument Sans', sans-serif; }
    </style>
</head>
<body class="bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 antialiased min-h-screen selection:bg-blue-500/20 selection:text-blue-900 dark:selection:bg-blue-400/25 dark:selection:text-white">

    {{-- ─── NAVBAR ─────────────────────────────────────────────────────── --}}
    <header class="sticky top-0 z-50 border-b border-zinc-200/80 dark:border-zinc-800/80 bg-white/75 dark:bg-zinc-950/75 backdrop-blur-xl backdrop-saturate-150 shadow-sm shadow-zinc-900/5 dark:shadow-black/20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between">

            {{-- Logo --}}
            <a href="{{ route('home') }}" class="group flex items-center gap-2.5 rounded-lg outline-none ring-blue-500/0 transition-[transform,box-shadow] duration-300 hover:scale-[1.02] focus-visible:ring-2 focus-visible:ring-blue-500/40">
                <div class="size-8 rounded-xl bg-linear-to-br from-blue-600 to-blue-700 flex items-center justify-center shadow-md shadow-blue-600/25 ring-1 ring-white/20 transition-shadow duration-300 group-hover:shadow-lg group-hover:shadow-blue-600/30">
                    <svg class="size-4 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <span class="font-semibold text-zinc-900 dark:text-zinc-100 tracking-tight">GatewayHub</span>
            </a>

            {{-- Nav links --}}
            <nav class="hidden md:flex items-center gap-0.5 p-0.5 rounded-full bg-zinc-100/80 dark:bg-zinc-800/60 ring-1 ring-zinc-200/60 dark:ring-zinc-700/50">
                <a href="#features" class="px-3.5 py-1.5 text-sm font-medium text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 rounded-full transition-colors duration-200 hover:bg-white/90 dark:hover:bg-zinc-700/80">Features</a>
                <a href="#gateways" class="px-3.5 py-1.5 text-sm font-medium text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 rounded-full transition-colors duration-200 hover:bg-white/90 dark:hover:bg-zinc-700/80">Gateways</a>
                <a href="#merchants" class="px-3.5 py-1.5 text-sm font-medium text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 rounded-full transition-colors duration-200 hover:bg-white/90 dark:hover:bg-zinc-700/80">Merchants</a>
            </nav>

            {{-- Theme + auth --}}
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="theme-toggle flex size-9 shrink-0 items-center justify-center rounded-xl border border-zinc-200/90 bg-white/90 text-zinc-600 shadow-sm transition-all duration-200 hover:bg-zinc-50 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900/90 dark:text-zinc-300 dark:hover:bg-zinc-800"
                    aria-label="{{ __('Toggle color theme') }}"
                >
                    {{-- Moon: shown in light mode (switch to dark) --}}
                    <svg class="size-4 dark:hidden" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
                    </svg>
                    {{-- Sun: shown in dark mode (switch to light) --}}
                    <svg class="hidden size-4 dark:block" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
                    </svg>
                </button>
                @auth
                    <a href="{{ url('/admin') }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold bg-linear-to-b from-blue-600 to-blue-700 text-white rounded-xl transition-all duration-200 shadow-md shadow-blue-600/25 hover:shadow-lg hover:shadow-blue-600/30 hover:-translate-y-px active:translate-y-0">
                        Dashboard
                        <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="px-3 py-2 text-sm font-medium text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 rounded-xl transition-colors duration-200 hover:bg-zinc-100/90 dark:hover:bg-zinc-800/80">
                            Sign in
                        </a>
                    @endif
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold bg-linear-to-b from-blue-600 to-blue-700 text-white rounded-xl transition-all duration-200 shadow-md shadow-blue-600/25 hover:shadow-lg hover:shadow-blue-600/30 hover:-translate-y-px active:translate-y-0">
                            Get started
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </header>

    {{-- ─── HERO ────────────────────────────────────────────────────────── --}}
    <section class="hero-section relative overflow-hidden grid-bg hero-grid-drift">

        {{-- Aurora mesh --}}
        <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden" aria-hidden="true">
            <div class="hero-aurora-blob"></div>
            <div class="hero-aurora-blob"></div>
            <div class="hero-aurora-blob"></div>
        </div>

        {{-- Drifting light beam --}}
        <div class="hero-beam z-0 hidden sm:block" aria-hidden="true"></div>

        {{-- Soft central glow --}}
        <div class="pointer-events-none absolute inset-0 z-0 flex items-center justify-center" aria-hidden="true">
            <div class="animate-float-orb size-[min(95vw,40rem)] rounded-full bg-blue-400/10 dark:bg-blue-500/12 blur-3xl"></div>
        </div>

        {{-- Floating glass cards (large screens) --}}
        <div
            class="hero-float-card pointer-events-none absolute start-[4%] top-[32%] z-[1] hidden xl:block"
            aria-hidden="true"
        >
            <div class="w-[11.5rem] rounded-2xl border border-zinc-200/80 bg-white/70 p-3 shadow-xl shadow-zinc-900/10 ring-1 ring-white/60 backdrop-blur-xl dark:border-zinc-600/50 dark:bg-zinc-900/60 dark:ring-white/5">
                <p class="text-[0.65rem] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Live volume</p>
                <p class="mt-1 font-mono text-lg font-bold text-zinc-900 dark:text-zinc-50">PHP 24.8k</p>
                <div class="mt-3 flex h-14 items-end justify-between gap-1 px-0.5">
                    <span class="hero-mini-bar inline-block w-1.5 rounded-sm bg-blue-500/80 dark:bg-blue-400/80" style="height: 45%"></span>
                    <span class="hero-mini-bar inline-block w-1.5 rounded-sm bg-blue-500/80 dark:bg-blue-400/80" style="height: 72%"></span>
                    <span class="hero-mini-bar inline-block w-1.5 rounded-sm bg-blue-500/80 dark:bg-blue-400/80" style="height: 38%"></span>
                    <span class="hero-mini-bar inline-block w-1.5 rounded-sm bg-blue-500/80 dark:bg-blue-400/80" style="height: 90%"></span>
                    <span class="hero-mini-bar inline-block w-1.5 rounded-sm bg-blue-500/80 dark:bg-blue-400/80" style="height: 55%"></span>
                </div>
            </div>
        </div>
        <div
            class="hero-float-card hero-float-card--alt pointer-events-none absolute end-[4%] top-[38%] z-[1] hidden xl:block"
            aria-hidden="true"
        >
            <div class="w-[11rem] rounded-2xl border border-zinc-200/80 bg-white/70 p-3 shadow-xl shadow-zinc-900/10 ring-1 ring-white/60 backdrop-blur-xl dark:border-zinc-600/50 dark:bg-zinc-900/60 dark:ring-white/5">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[0.65rem] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Uptime</p>
                    <span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[0.65rem] font-semibold text-emerald-700 dark:text-emerald-400">99.9%</span>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-zinc-200/80 dark:bg-zinc-700/80">
                    <div class="h-full w-[99%] rounded-full bg-linear-to-r from-emerald-500 to-emerald-400"></div>
                </div>
                <p class="mt-2 text-[0.7rem] text-zinc-500 dark:text-zinc-400">Last 30 days</p>
            </div>
        </div>

        <div class="relative z-[2] max-w-6xl mx-auto px-4 sm:px-6 pt-16 pb-20 sm:pt-20 sm:pb-24 text-center">

            {{-- Badge --}}
            <div class="hero-badge fade-up fade-up-1 inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-zinc-200/90 dark:border-zinc-700/90 shadow-md shadow-zinc-900/5 backdrop-blur-md badge-glow mb-6 ring-1 ring-blue-500/10 dark:ring-blue-400/15">
                <span class="size-2 rounded-full bg-emerald-500 pulse-dot block ring-2 ring-emerald-500/30"></span>
                <span class="text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-300">All systems operational</span>
            </div>

            {{-- Headline (staggered 3D-style word reveal) --}}
            <h1 class="hero-title text-4xl sm:text-5xl md:text-6xl lg:text-[3.5rem] font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 leading-[1.12] max-w-3xl mx-auto">
                <span class="block overflow-visible">
                    <span class="hero-word">One</span>
                    <span class="hero-word">platform</span>
                    <span class="hero-word">for</span>
                    <span class="hero-word">every</span>
                </span>
                <span class="hero-gradient-line mt-1 block sm:mt-2">
                    <span class="text-gradient-live">payment gateway</span>
                </span>
            </h1>

            <p class="fade-up fade-up-3 mt-6 text-lg sm:text-xl text-zinc-600 dark:text-zinc-400 max-w-xl mx-auto leading-relaxed">
                Enable, disable, and manage payment gateways across all your merchants from a single admin panel. No code changes required.
            </p>

            {{-- CTA buttons --}}
            <div class="fade-up fade-up-4 mt-10 flex flex-wrap items-center justify-center gap-3">
                @auth
                    <a href="{{ url('/admin') }}" class="hero-cta-primary inline-flex items-center gap-2 px-7 py-3 bg-linear-to-b from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white text-sm font-semibold rounded-2xl transition-all duration-200 hover:-translate-y-0.5 active:translate-y-0">
                        Go to Dashboard
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>
                @else
                    <a href="{{ route('register') }}" class="hero-cta-primary inline-flex items-center gap-2 px-7 py-3 bg-linear-to-b from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white text-sm font-semibold rounded-2xl transition-all duration-200 hover:-translate-y-0.5 active:translate-y-0">
                        Start for free
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-7 py-3 bg-white/95 dark:bg-zinc-900/95 hover:bg-white dark:hover:bg-zinc-800 text-zinc-800 dark:text-zinc-200 text-sm font-semibold rounded-2xl border border-zinc-200/90 dark:border-zinc-600/90 transition-all duration-200 shadow-md shadow-zinc-900/5 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 backdrop-blur-sm">
                        Sign in
                    </a>
                @endauth
            </div>

            {{-- Stats row --}}
            <div id="merchants" class="hero-stats-wrap fade-up fade-up-5 mt-16 sm:mt-20 scroll-mt-28 grid grid-cols-3 max-w-lg mx-auto gap-px rounded-2xl overflow-hidden border border-zinc-200/90 dark:border-zinc-700/90 bg-zinc-200/90 dark:bg-zinc-700/90 shadow-lg shadow-zinc-900/8 dark:shadow-black/40 ring-1 ring-black/5 dark:ring-white/5">
                <div class="group bg-white/95 dark:bg-zinc-900/95 px-3 py-4 sm:px-6 sm:py-5 text-center backdrop-blur-sm transition-transform duration-300 hover:bg-white dark:hover:bg-zinc-900 sm:hover:scale-[1.02]">
                    <p class="stat-num text-xl sm:text-2xl font-bold tabular-nums text-zinc-900 dark:text-zinc-50">{{ number_format((int) ($stats['gateway_total'] ?? 0)) }}</p>
                    <p class="text-[0.65rem] sm:text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mt-1">Gateways</p>
                </div>
                <div class="group bg-white/95 dark:bg-zinc-900/95 px-3 py-4 sm:px-6 sm:py-5 text-center backdrop-blur-sm transition-transform duration-300 hover:bg-white dark:hover:bg-zinc-900 sm:hover:scale-[1.02]">
                    <p class="stat-num text-xl sm:text-2xl font-bold tabular-nums text-zinc-900 dark:text-zinc-50">{{ number_format((int) ($stats['merchant_total'] ?? 0)) }}</p>
                    <p class="text-[0.65rem] sm:text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mt-1">Merchants</p>
                </div>
                <div class="group bg-white/95 dark:bg-zinc-900/95 px-3 py-4 sm:px-6 sm:py-5 text-center backdrop-blur-sm transition-transform duration-300 hover:bg-white dark:hover:bg-zinc-900 sm:hover:scale-[1.02]">
                    <p class="stat-num text-base sm:text-lg font-bold tabular-nums text-zinc-900 dark:text-zinc-50">PHP {{ number_format((float) ($stats['paid_collections'] ?? 0), 2) }}</p>
                    <p class="text-[0.6rem] sm:text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mt-1 leading-tight">Paid Collections</p>
                </div>
            </div>

        </div>
    </section>

    {{-- ─── DASHBOARD PREVIEW ───────────────────────────────────────────── --}}
    <section class="reveal max-w-6xl mx-auto px-4 sm:px-6 -mt-2 pb-20 sm:pb-24">
        <div class="rounded-2xl border border-zinc-200/90 dark:border-zinc-700/90 bg-white/95 dark:bg-zinc-900/95 shadow-2xl shadow-zinc-900/10 dark:shadow-black/50 overflow-hidden ring-1 ring-black/5 dark:ring-white/10 backdrop-blur-sm transition-shadow duration-500 hover:shadow-zinc-900/15 dark:hover:shadow-black/60">

            {{-- Fake browser chrome --}}
            <div class="flex items-center gap-2 px-4 py-3 border-b border-zinc-100 dark:border-zinc-800 bg-linear-to-b from-zinc-100/90 to-zinc-50/90 dark:from-zinc-950 dark:to-zinc-900/80">
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
                    <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-emerald-500"></span>
                            {{ number_format((int) ($stats['enabled_gateway_total'] ?? 0)) }} active
                        </span>
                        <span class="text-zinc-300 dark:text-zinc-600">/</span>
                        <span class="text-zinc-600 dark:text-zinc-300">{{ number_format((int) ($stats['gateway_total'] ?? 0)) }} total</span>
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
    <section id="features" class="max-w-6xl mx-auto px-4 sm:px-6 py-16 sm:py-24">

        <div class="text-center mb-12 sm:mb-14 reveal">
            <p class="text-xs font-bold text-blue-600 dark:text-blue-400 uppercase tracking-[0.2em] mb-3">Why GatewayHub</p>
            <h2 class="text-3xl sm:text-4xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Everything you need to manage payments</h2>
            <p class="mt-4 text-base sm:text-lg text-zinc-600 dark:text-zinc-400 max-w-2xl mx-auto leading-relaxed">Built for platform operators who need fine-grained control over payment infrastructure without the complexity.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-4 sm:gap-5">

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
            <div
                class="feature-card reveal group relative overflow-hidden rounded-2xl border border-zinc-200/90 dark:border-zinc-700/90 bg-white/90 dark:bg-zinc-900/90 p-6 ring-1 ring-black/[0.03] dark:ring-white/[0.04] backdrop-blur-sm hover:border-blue-200/80 dark:hover:border-blue-500/25"
                style="transition-delay: {{ min($loop->index * 65, 400) }}ms;"
            >
                <div class="absolute -end-8 -top-8 size-24 rounded-full bg-linear-to-br from-blue-500/5 to-violet-500/5 opacity-0 transition-opacity duration-500 group-hover:opacity-100"></div>
                <div class="relative">
                    <div class="size-11 rounded-xl {{ $feat['bg'] }} flex items-center justify-center mb-4 ring-1 ring-black/5 dark:ring-white/10 transition-transform duration-300 group-hover:scale-105 group-hover:rotate-3">
                        <svg class="size-5 {{ $feat['color'] }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $feat['icon'] }}"/>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-lg text-zinc-900 dark:text-zinc-100 mb-2">{{ $feat['title'] }}</h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">{{ $feat['desc'] }}</p>
                </div>
            </div>
            @endforeach

        </div>
    </section>

    {{-- ─── SUPPORTED GATEWAYS ──────────────────────────────────────────── --}}
    <section id="gateways" class="relative overflow-hidden bg-linear-to-b from-zinc-100 to-zinc-50 dark:from-zinc-900 dark:to-zinc-950 border-y border-zinc-200/80 dark:border-zinc-800 py-16 sm:py-20">
        <div class="pointer-events-none absolute inset-0 grid-bg opacity-50 dark:opacity-30 [mask-image:linear-gradient(to_bottom,white,transparent)]"></div>
        <div class="relative max-w-6xl mx-auto px-4 sm:px-6">

            <div class="text-center mb-10 sm:mb-12 reveal">
                <p class="text-xs font-bold text-blue-600 dark:text-blue-400 uppercase tracking-[0.2em] mb-3">Integrations</p>
                <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Works with the gateways you already use</h2>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 sm:gap-4">
                @forelse ($supportedGatewayNames as $gatewayName)
                <div
                    class="integration-pill reveal flex items-center justify-center px-4 py-3.5 bg-white/90 dark:bg-zinc-800/90 rounded-xl border border-zinc-200/90 dark:border-zinc-700/90 text-sm font-semibold text-zinc-700 dark:text-zinc-200 cursor-default backdrop-blur-sm ring-1 ring-black/[0.02] dark:ring-white/5"
                    style="transition-delay: {{ min($loop->index * 40, 320) }}ms"
                >
                    {{ $gatewayName }}
                </div>
                @empty
                <div class="col-span-full reveal flex items-center justify-center px-4 py-8 bg-white/90 dark:bg-zinc-800/90 rounded-2xl border border-dashed border-zinc-300 dark:border-zinc-600 text-sm font-medium text-zinc-500 dark:text-zinc-400">
                    No gateways available yet.
                </div>
                @endforelse
            </div>

        </div>
    </section>

    {{-- ─── CTA ─────────────────────────────────────────────────────────── --}}
    <section class="max-w-6xl mx-auto px-4 sm:px-6 py-20 sm:py-28">
        <div class="reveal relative overflow-hidden rounded-3xl bg-linear-to-br from-blue-600 via-blue-600 to-indigo-700 dark:from-blue-700 dark:via-blue-700 dark:to-indigo-900 px-6 py-12 sm:px-10 sm:py-16 text-center shadow-2xl shadow-blue-600/25 dark:shadow-blue-900/40 ring-1 ring-white/10 cta-shine">

            {{-- Decorative grid inside CTA --}}
            <div class="absolute inset-0 opacity-[0.12]" style="background-image: linear-gradient(to right, white 1px, transparent 1px), linear-gradient(to bottom, white 1px, transparent 1px); background-size: 32px 32px;"></div>
            <div class="pointer-events-none absolute -top-24 start-1/4 size-48 rounded-full bg-white/10 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-16 end-1/4 size-40 rounded-full bg-indigo-400/20 blur-3xl"></div>

            <div class="relative">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-semibold text-white tracking-tight">Ready to take control of your payments?</h2>
                <p class="mt-4 text-base text-blue-100/95 max-w-lg mx-auto leading-relaxed">Start managing your gateway infrastructure today. Free to get started, no credit card required.</p>
                <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-7 py-3 bg-white text-blue-700 text-sm font-bold rounded-2xl hover:bg-blue-50 transition-all duration-200 shadow-lg hover:shadow-xl hover:-translate-y-0.5 active:translate-y-0">
                            Create free account
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </a>
                    @endif
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-7 py-3 bg-white/10 hover:bg-white/20 text-white text-sm font-semibold rounded-2xl transition-all duration-200 border border-white/25 backdrop-blur-sm hover:-translate-y-0.5 active:translate-y-0">
                            Sign in to your account
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- ─── FOOTER ──────────────────────────────────────────────────────── --}}
    <footer class="reveal border-t border-zinc-200/80 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <div class="size-6 rounded-md bg-blue-600 flex items-center justify-center">
                    <svg class="size-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">GatewayHub</span>
            </div>
            <p class="text-xs text-zinc-400 dark:text-zinc-500">© {{ date('Y') }} GatewayHub. All rights reserved.</p>
            <div class="flex items-center gap-5 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                <a href="#" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200">Privacy</a>
                <a href="#" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200">Terms</a>
                <a href="#" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200">Docs</a>
            </div>
        </div>
    </footer>

    <script>
        document.querySelector('.theme-toggle')?.addEventListener('click', () => {
            if (!window.Flux?.applyAppearance) {
                return;
            }
            window.Flux.applyAppearance(
                document.documentElement.classList.contains('dark') ? 'light' : 'dark'
            );
        });

        const revealObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) {
                        return;
                    }
                    entry.target.classList.add('reveal-visible');
                    revealObserver.unobserve(entry.target);
                });
            },
            { rootMargin: '0px 0px -6% 0px', threshold: 0.08 }
        );

        document.querySelectorAll('.reveal').forEach((el) => revealObserver.observe(el));
    </script>

</body>
</html>
