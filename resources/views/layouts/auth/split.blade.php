<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        @vite('resources/css/kemopay.css')
        <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700" rel="stylesheet" />
    </head>
    <body class="kp-auth-body antialiased">
        <a class="kp-auth-skip-link" href="#auth-form">{{ __('Skip to account form') }}</a>

        <div class="kp-auth-shell">
            <div class="kp-auth-glow kp-auth-glow-one" aria-hidden="true"></div>
            <div class="kp-auth-glow kp-auth-glow-two" aria-hidden="true"></div>

            <header class="kp-auth-header">
                <div class="kp-auth-header-inner">
                    <a href="{{ route('home') }}" class="kp-auth-brand" wire:navigate aria-label="{{ __('KemoPay home') }}">
                        <img
                            class="kp-auth-logo"
                            src="{{ asset('KemoPay Payment Gateway Logo.png') }}"
                            alt="KemoPay Payment Gateway"
                        />
                    </a>

                    <p class="kp-auth-header-note">
                        <span class="kp-auth-header-dot" aria-hidden="true"></span>
                        {{ __('A clearer way to manage payments') }}
                    </p>

                    <a href="{{ route('home') }}" class="kp-auth-home-link" wire:navigate>
                        {{ __('Back to KemoPay') }}
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M4.5 10h11m-4.5-4.5L15.5 10 11 14.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </a>
                </div>
            </header>

            <main id="auth-content" class="kp-auth-main">
                <section class="kp-auth-story" aria-labelledby="kp-auth-story-title">
                    <div class="kp-auth-story-copy">
                        <p class="kp-auth-eyebrow">
                            <span class="kp-auth-status-dot" aria-hidden="true"></span>
                            {{ __('Payments, connected') }}
                        </p>

                        <h1 id="kp-auth-story-title">
                            {{ __('One view.') }}<br />
                            <span>{{ __('Every way to pay.') }}</span>
                        </h1>

                        <p class="kp-auth-story-description">
                            {{ __('Bring your payment channels, transactions, and business tools together in one calm, clear workspace.') }}
                        </p>

                        <div class="kp-auth-preview-wrap" aria-hidden="true">
                            <div class="kp-auth-preview">
                                <div class="kp-auth-preview-top">
                                    <span class="kp-auth-preview-brand">
                                        <img src="{{ asset('Modern K Payment App Icon.png') }}" alt="" />
                                        KemoPay
                                    </span>
                                    <span class="kp-auth-preview-live"><i></i>{{ __('Live workspace') }}</span>
                                </div>

                                <div class="kp-auth-preview-content">
                                    <div class="kp-auth-preview-label">{{ __('Total payments') }}</div>
                                    <div class="kp-auth-preview-amount">₱24,680<span>.00</span></div>
                                    <div class="kp-auth-preview-caption"><i></i>{{ __('Your payment activity at a glance') }}</div>

                                    <svg class="kp-auth-preview-chart" viewBox="0 0 360 92" fill="none" preserveAspectRatio="none">
                                        <defs>
                                            <linearGradient id="kp-auth-chart-fill" x1="180" y1="8" x2="180" y2="92" gradientUnits="userSpaceOnUse">
                                                <stop stop-color="#8A6BFF" stop-opacity=".24" />
                                                <stop offset="1" stop-color="#8A6BFF" stop-opacity="0" />
                                            </linearGradient>
                                        </defs>
                                        <path d="M0 72C20 68 23 75 43 63S74 56 88 61s21 3 35-13 26-14 41-2 22 8 37-4 26-9 42-1 24 5 39-9 24-19 39-11 24 4 39-11v81H0V72Z" fill="url(#kp-auth-chart-fill)" />
                                        <path d="M0 72C20 68 23 75 43 63S74 56 88 61s21 3 35-13 26-14 41-2 22 8 37-4 26-9 42-1 24 5 39-9 24-19 39-11 24 4 39-11" stroke="#7954F8" stroke-width="2.5" stroke-linecap="round" />
                                    </svg>

                                    <div class="kp-auth-preview-methods">
                                        <span><i class="kp-auth-method-gcash">G</i>GCash</span>
                                        <span><i class="kp-auth-method-maya">M</i>Maya</span>
                                        <span class="kp-auth-method-count">+3 {{ __('more') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="kp-auth-floating-note">
                                <span class="kp-auth-floating-icon">
                                    <svg viewBox="0 0 20 20" fill="none">
                                        <path d="m5 10.2 3.1 3.1L15.5 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                                <span><strong>{{ __('You’re all set') }}</strong><small>{{ __('Your business, in sync') }}</small></span>
                            </div>
                        </div>

                        <div class="kp-auth-benefits">
                            <span>
                                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 10.2 3.1 3.1L15.5 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                {{ __('Secure access') }}
                            </span>
                            <span>
                                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 10.2 3.1 3.1L15.5 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                {{ __('Clear payment tracking') }}
                            </span>
                        </div>
                    </div>
                </section>

                <section id="auth-form" class="kp-auth-form-column" aria-label="{{ __('Account access') }}">
                    {{ $slot }}
                </section>
            </main>

            <footer class="kp-auth-footer">
                <span>&copy; {{ now()->year }} {{ config('app.name', 'KemoPay') }}</span>
                <span class="kp-auth-footer-note">{{ __('Payments made clearer.') }}</span>
            </footer>
        </div>

        @fluxScripts
    </body>
</html>
