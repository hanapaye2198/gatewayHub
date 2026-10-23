<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <title>KemoPay — Payments for your business</title>
    <meta name="description" content="Accept payments through the providers your customers use. Manage gateways, merchants, transactions, and signed webhooks from one KemoPay workspace.">
    <meta property="og:title" content="KemoPay — Payments for your business">
    <meta property="og:description" content="One payment integration, multiple providers, and a clear view of every transaction.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/') }}">
    <link rel="canonical" href="{{ url('/') }}">
    <link rel="icon" href="{{ asset('Modern K Payment App Icon.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/css/kemopay.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="kp-site antialiased">
    <a href="#main-content" class="kp-skip-link">{{ __('Skip to content') }}</a>

    @php
        $navSections = [
            '#platform' => 'Platform',
            '#solutions' => 'Solutions',
            '#integrations' => 'Integrations',
            '#developers' => 'Developers',
        ];
    @endphp

    <header class="kp-header" id="kemopay-nav">
        <div class="kp-container kp-header-inner">
            <a href="{{ route('home') }}" class="kp-brand" aria-label="{{ __('KemoPay home') }}">
                <x-landing-logo-mark />
            </a>

            <nav class="kp-desktop-nav" aria-label="{{ __('Main navigation') }}">
                @foreach ($navSections as $href => $label)
                    <a href="{{ $href }}">{{ $label }}</a>
                @endforeach
            </nav>

            <div class="kp-header-actions">
                <button type="button" class="kp-menu-toggle" aria-label="{{ __('Toggle navigation menu') }}" aria-expanded="false" aria-controls="kemopay-mobile-nav">
                    <svg class="kp-menu-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                    <svg class="kp-close-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
                @auth
                    <a href="{{ $dashboardUrl }}" class="kp-button kp-button-primary kp-header-cta">Dashboard</a>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="kp-sign-in">{{ __('Sign in') }}</a>
                    @endif
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="kp-button kp-button-primary kp-header-cta">{{ __('Get started') }}</a>
                    @endif
                @endauth
            </div>
        </div>

        <nav id="kemopay-mobile-nav" class="kp-mobile-nav" aria-label="{{ __('Mobile navigation') }}" hidden>
            @foreach ($navSections as $href => $label)
                <a href="{{ $href }}">{{ $label }}</a>
            @endforeach
            @auth
                <a href="{{ $dashboardUrl }}">Dashboard</a>
            @else
                @if (Route::has('login'))
                    <a href="{{ route('login') }}">{{ __('Sign in') }}</a>
                @endif
                @if (Route::has('register'))
                    <a href="{{ route('register') }}">{{ __('Get started') }}</a>
                @endif
            @endauth
        </nav>
    </header>

    <main id="main-content">
        <section class="kp-hero">
            <div class="kp-hero-pattern" aria-hidden="true"></div>
            <div class="kp-container kp-hero-grid">
                <div class="kp-hero-copy">
                    <div class="kp-status-pill">
                        <span class="kp-status-dot {{ $isOperational ? 'is-online' : 'is-offline' }}"></span>
                        {{ $isOperational ? __('Platform is online') : __('Platform connection issue') }}
                    </div>

                    <p class="kp-eyebrow">{{ __('A simpler way to get paid') }}</p>
                    <h1>{{ __('Payments for') }} <span>{{ __('your next big idea.') }}</span></h1>
                    <p class="kp-hero-description">{{ __('Connect the payment methods your customers already use. Track transactions, manage providers, and keep your team in sync from one clear workspace.') }}</p>

                    <div class="kp-hero-actions">
                        @auth
                            <a href="{{ $dashboardUrl }}" class="kp-button kp-button-primary">{{ __('Go to Dashboard') }} <span aria-hidden="true">→</span></a>
                        @else
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="kp-button kp-button-primary">{{ __('Get started') }} <span aria-hidden="true">→</span></a>
                            @endif
                            <a href="{{ route('demo.checkout') }}" class="kp-button kp-button-quiet">{{ __('Try live demo') }}</a>
                        @endauth
                    </div>

                    <div class="kp-hero-proof">
                        <span><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5 10 3.2 3.2L15.5 6"/></svg>{{ __('One integration') }}</span>
                        <span><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5 10 3.2 3.2L15.5 6"/></svg>{{ __('Clear reporting') }}</span>
                        <span><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5 10 3.2 3.2L15.5 6"/></svg>{{ __('Signed webhooks') }}</span>
                    </div>
                </div>

                <div class="kp-hero-art" aria-label="{{ __('Preview of the KemoPay payment dashboard and checkout') }}">
                    <div class="kp-orbit kp-orbit-one" aria-hidden="true"></div>
                    <div class="kp-orbit kp-orbit-two" aria-hidden="true"></div>

                    <div class="kp-dashboard-window">
                        <div class="kp-window-topbar">
                            <div class="kp-window-brand"><x-landing-logo-mark size="sm" /><span>KemoPay</span></div>
                            <div class="kp-window-user"><span>{{ __('Workspace') }}</span><span class="kp-avatar">K</span></div>
                        </div>
                        <div class="kp-dashboard-content">
                            <div class="kp-dashboard-heading">
                                <div>
                                    <p class="kp-ui-overline">{{ __('OVERVIEW') }}</p>
                                    <h2>{{ __('Your payments') }}</h2>
                                </div>
                                <span class="kp-period">{{ __('All time') }} <span aria-hidden="true">⌄</span></span>
                            </div>

                            <div class="kp-total-card">
                                <div class="kp-total-copy">
                                    <span>{{ __('Paid volume') }}</span>
                                    <strong>₱{{ number_format((float) ($stats['paid_collections'] ?? 0), 2) }}</strong>
                                    <small><span class="kp-trend-dot"></span>{{ __('Collected payments') }}</small>
                                </div>
                                <div class="kp-chart-wrap" aria-hidden="true">
                                    <svg viewBox="0 0 360 120" preserveAspectRatio="none">
                                        <defs><linearGradient id="kp-chart-fill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#8a61ff" stop-opacity=".25"/><stop offset="1" stop-color="#8a61ff" stop-opacity="0"/></linearGradient></defs>
                                        <path class="kp-chart-area" d="M0 95 C24 88 29 92 48 76 S78 82 95 66 S125 73 145 55 S174 65 195 46 S225 54 245 33 S278 46 295 25 S331 41 360 10 V120 H0Z"/>
                                        <path class="kp-chart-line" d="M0 95 C24 88 29 92 48 76 S78 82 95 66 S125 73 145 55 S174 65 195 46 S225 54 245 33 S278 46 295 25 S331 41 360 10"/>
                                    </svg>
                                </div>
                            </div>

                            <div class="kp-dashboard-stats">
                                <div><span>{{ __('Payment methods') }}</span><strong>{{ number_format((int) ($stats['gateway_total'] ?? 0)) }} <small>{{ __('total') }}</small></strong></div>
                                <div><span>{{ __('Merchants') }}</span><strong>{{ number_format((int) ($stats['merchant_total'] ?? 0)) }}</strong></div>
                                <div><span>{{ __('Paid payments') }}</span><strong>{{ number_format((int) ($stats['paid_payments_count'] ?? 0)) }}</strong></div>
                            </div>

                            <div class="kp-connected-list">
                                <div class="kp-list-heading"><strong>{{ __('Payment channels') }}</strong><a href="#integrations">{{ __('View all') }}</a></div>
                                @forelse ($previewGateways->take(3) as $gateway)
                                    <div class="kp-channel-row">
                                        <span class="kp-channel-icon">{{ strtoupper(substr($gateway->name, 0, 1)) }}</span>
                                        <span class="kp-channel-name">{{ $gateway->name }}</span>
                                        <span class="kp-channel-state {{ $gateway->is_global_enabled ? 'is-enabled' : 'is-disabled' }}">{{ $gateway->is_global_enabled ? __('Active') : __('Paused') }}</span>
                                    </div>
                                @empty
                                    <p class="kp-empty-state">{{ __('No gateways configured yet.') }}</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="kp-checkout-card">
                        <div class="kp-checkout-notch" aria-hidden="true"></div>
                        <div class="kp-checkout-top">
                            <span class="kp-checkout-mark"><img src="{{ asset('Modern K Payment App Icon.png') }}" alt="" /></span>
                            <span>KemoPay</span>
                            <span class="kp-lock" aria-label="{{ __('Secure checkout') }}">●</span>
                        </div>
                        <p class="kp-checkout-overline">{{ __('COMPLETE YOUR PAYMENT') }}</p>
                        <h2>{{ __('Choose a payment method') }}</h2>
                        <div class="kp-checkout-options">
                            @foreach (collect($gatewayCatalog)->take(3) as $gateway)
                                <div class="kp-checkout-option">
                                    <span class="kp-checkout-provider" style="--provider-color: {{ $gateway['color'] }}">{{ strtoupper(substr($gateway['name'], 0, 1)) }}</span>
                                    <span>{{ $gateway['name'] }}</span>
                                    <span class="kp-radio" aria-hidden="true"></span>
                                </div>
                            @endforeach
                        </div>
                        <div class="kp-checkout-button">{{ __('Continue securely') }} <span aria-hidden="true">→</span></div>
                        <p class="kp-checkout-caption">{{ __('Protected payment experience') }}</p>
                    </div>

                    <div class="kp-notification-card">
                        <span class="kp-notification-icon" aria-hidden="true">✓</span>
                        <span><strong>{{ __('Payment received') }}</strong><small>{{ __('Status updated in real time') }}</small></span>
                    </div>
                </div>
            </div>
        </section>

        <section id="integrations" class="kp-integrations">
            <div class="kp-container">
                <p class="kp-strip-label">{{ __('Payment methods your customers know') }}</p>
                <div class="kp-provider-strip">
                    @foreach ($gatewayCatalog as $gateway)
                        <div class="kp-provider-item">
                            @if ($gateway['logo'])
                                <img src="{{ asset($gateway['logo']) }}" alt="" loading="lazy">
                            @else
                                <span class="kp-provider-mark" style="--provider-color: {{ $gateway['color'] }}">{{ strtoupper(substr($gateway['name'], 0, 1)) }}</span>
                            @endif
                            <span>{{ $gateway['name'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="platform" class="kp-platform-section">
            <div class="kp-container">
                <div class="kp-section-intro">
                    <p class="kp-eyebrow">{{ __('Your payment operations') }}</p>
                    <h2>{{ __('A connected view of every payment.') }}</h2>
                    <p>{{ __('See what is active, what has been collected, and where each transaction stands. KemoPay keeps the daily work close at hand.') }}</p>
                </div>

                <div class="kp-platform-grid">
                    <div class="kp-platform-copy">
                        <h3>{{ __('Everything in one workspace') }}</h3>
                        <p>{{ __('Keep merchant access, provider settings, and payment activity together. Instant propagation to merchants means gateway changes take effect without a redeploy.') }}</p>
                        <a href="{{ route('demo.checkout') }}" class="kp-text-link">{{ __('See how checkout works') }} <span aria-hidden="true">→</span></a>
                    </div>

                    <div class="kp-analytics-card">
                        <div class="kp-analytics-header">
                            <div><span class="kp-analytics-icon" aria-hidden="true">↗</span><span>{{ __('Platform activity') }}</span></div>
                            <span class="kp-preview-badge"><span></span>{{ __('Product preview') }}</span>
                        </div>
                        <div class="kp-analytics-numbers">
                            <div><span>{{ __('Active methods') }}</span><strong>{{ number_format((int) ($stats['enabled_gateway_total'] ?? 0)) }} <small>{{ __('active') }}</small></strong></div>
                            <div><span>{{ __('Configured methods') }}</span><strong>{{ number_format((int) ($stats['gateway_total'] ?? 0)) }} <small>{{ __('total') }}</small></strong></div>
                            <div><span>{{ __('Paid volume') }}</span><strong>₱{{ number_format((float) ($stats['paid_collections'] ?? 0), 2) }}</strong></div>
                        </div>
                        <div class="kp-analytics-graph" aria-hidden="true">
                            <div class="kp-graph-guides"><span></span><span></span><span></span></div>
                            <div class="kp-graph-bars">
                                <i style="--bar-height: 26%"></i><i style="--bar-height: 38%"></i><i style="--bar-height: 32%"></i><i style="--bar-height: 52%"></i><i style="--bar-height: 44%"></i><i style="--bar-height: 68%"></i><i style="--bar-height: 58%"></i><i style="--bar-height: 78%"></i><i style="--bar-height: 65%"></i><i style="--bar-height: 92%"></i><i style="--bar-height: 76%"></i><i style="--bar-height: 100%"></i>
                            </div>
                        </div>
                        <div class="kp-analytics-legend"><span><i></i>{{ __('Activity preview') }}</span><span>{{ __('Illustrative chart') }}</span></div>
                    </div>
                </div>

                <div class="kp-metrics-row" aria-label="{{ __('Platform metrics') }}">
                    <div><strong>{{ number_format((int) ($stats['gateway_total'] ?? 0)) }}</strong><span>{{ __('payment methods') }}</span></div>
                    <div><strong>{{ number_format((int) ($stats['merchant_total'] ?? 0)) }}</strong><span>{{ __('merchants') }}</span></div>
                    <div><strong>{{ number_format((int) ($stats['paid_payments_count'] ?? 0)) }}</strong><span>{{ __('paid payments') }}</span></div>
                    <div><strong>{{ number_format((int) ($stats['enabled_gateway_total'] ?? 0)) }}</strong><span>{{ __('active methods') }}</span></div>
                </div>
            </div>
        </section>

        <section id="solutions" class="kp-solutions-section">
            <div class="kp-container">
                <div class="kp-section-heading">
                    <p class="kp-eyebrow">{{ __('Made for your workflow') }}</p>
                    <h2>{{ __('Payments and operations, working together.') }}</h2>
                    <p>{{ __('Tools for the people who build, manage, and grow a payment experience.') }}</p>
                </div>

                <div class="kp-feature-grid">
                    <article class="kp-feature-card kp-feature-card-accent">
                        <span class="kp-feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3 10h18M7 15h4"/></svg>
                        </span>
                        <h3>{{ __('Payment orchestration') }}</h3>
                        <p>{{ __('Bring supported providers behind one consistent integration and manage availability from your platform.') }}</p>
                        <span class="kp-feature-number">01</span>
                    </article>

                    <article class="kp-feature-card">
                        <span class="kp-feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 20v-1.2A4.8 4.8 0 0 1 8.3 14h1.4a4.8 4.8 0 0 1 4.8 4.8V20M16 5.3a3.2 3.2 0 0 1 0 6.2m1.1 2.7a4.8 4.8 0 0 1 3.4 4.6V20"/></svg>
                        </span>
                        <h3>{{ __('Merchant workspaces') }}</h3>
                        <p>{{ __('Give each merchant scoped credentials, a clear transaction history, and the controls they need.') }}</p>
                        <span class="kp-feature-number">02</span>
                    </article>

                    <article class="kp-feature-card">
                        <span class="kp-feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h17M8 15l3-4 3 2 5-7"/></svg>
                        </span>
                        <h3>{{ __('Useful payment reporting') }}</h3>
                        <p>{{ __('Review payment statuses, export transaction data, and keep platform activity easy to follow.') }}</p>
                        <span class="kp-feature-number">03</span>
                    </article>

                    <article class="kp-feature-card">
                        <span class="kp-feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M12 3 19 6v5c0 4.4-2.8 7.7-7 10-4.2-2.3-7-5.6-7-10V6l7-3Z"/><path d="m9 12 2 2 4-4"/></svg>
                        </span>
                        <h3>{{ __('Signed webhooks') }}</h3>
                        <p>{{ __('Verify payment updates with signed notifications and keep your own systems in sync.') }}</p>
                        <span class="kp-feature-number">04</span>
                    </article>
                </div>
            </div>
        </section>

        <section class="kp-network-section">
            <div class="kp-network-decoration" aria-hidden="true"></div>
            <div class="kp-container kp-network-content">
                <div>
                    <p class="kp-eyebrow kp-eyebrow-light">{{ __('A clear view, from checkout to report') }}</p>
                    <h2>{{ __('Stay close to every payment.') }}</h2>
                    <p>{{ __('Bring payment channels and merchant activity into one connected workspace, then see the results as they happen.') }}</p>
                </div>
                <div class="kp-network-stats">
                    <div><strong>{{ number_format((int) ($stats['enabled_gateway_total'] ?? 0)) }}</strong><span>{{ __('active methods') }}</span></div>
                    <div><strong>{{ number_format((int) ($stats['merchant_total'] ?? 0)) }}</strong><span>{{ __('merchants') }}</span></div>
                    <div><strong>{{ number_format((int) ($stats['paid_payments_count'] ?? 0)) }}</strong><span>{{ __('paid payments') }}</span></div>
                    <div><strong>₱{{ number_format((float) ($stats['paid_collections'] ?? 0), 2) }}</strong><span>{{ __('paid volume') }}</span></div>
                </div>
            </div>
        </section>

        <section id="developers" class="kp-developers-section">
            <div class="kp-container kp-developers-grid">
                <div class="kp-developers-copy">
                    <p class="kp-eyebrow">{{ __('For builders') }}</p>
                    <h2>{{ __('A straightforward path from API to payment.') }}</h2>
                    <p>{{ __('Create a payment, send the customer to checkout, and receive a signed update when its status changes. The KemoPay API keeps the flow clear.') }}</p>
                    <ul>
                        <li><span>✓</span>{{ __('Payment creation and status endpoints') }}</li>
                        <li><span>✓</span>{{ __('Merchant-scoped API credentials') }}</li>
                        <li><span>✓</span>{{ __('Signed webhook notifications') }}</li>
                    </ul>
                    <div class="kp-developer-actions">
                        <a href="{{ route('public.api-docs') }}" class="kp-button kp-button-primary">{{ __('Read the API docs') }} <span aria-hidden="true">→</span></a>
                        <a href="{{ route('demo.checkout') }}" class="kp-text-link">{{ __('Explore the demo') }}</a>
                    </div>
                </div>

                <div class="kp-code-card">
                    <div class="kp-code-header">
                        <span><i></i><i></i><i></i></span>
                        <strong>{{ __('Create a payment') }}</strong>
                        <span>POST /api/payments</span>
                    </div>
                    <pre><code><span class="kp-code-purple">curl</span> -X POST <span class="kp-code-green">"{{ url('/api/payments') }}"</span> \
  -H <span class="kp-code-orange">"Authorization: Bearer YOUR_API_KEY"</span> \
  -H <span class="kp-code-orange">"Content-Type: application/json"</span> \
  -d <span class="kp-code-green">'{"amount":1500,"currency":"PHP","gateway_code":"gcash"}'</span></code></pre>
                    <div class="kp-code-foot"><span class="kp-code-check">✓</span>{{ __('Simple request. Clear payment status.') }}</div>
                </div>
            </div>
        </section>

        <section class="kp-cta-section">
            <div class="kp-container">
                <div class="kp-cta-card">
                    <div class="kp-cta-shape kp-cta-shape-one" aria-hidden="true"></div>
                    <div class="kp-cta-shape kp-cta-shape-two" aria-hidden="true"></div>
                    <div class="kp-cta-content">
                        <p class="kp-eyebrow kp-eyebrow-light">{{ __('Make your next payment simpler') }}</p>
                        <h2>{{ __('Ready to get started?') }}</h2>
                        <p>{{ __('Create your workspace and bring your payment operations together with KemoPay.') }}</p>
                        <div class="kp-cta-actions">
                            @auth
                                <a href="{{ $dashboardUrl }}" class="kp-button kp-button-white">{{ __('Open your dashboard') }} <span aria-hidden="true">→</span></a>
                            @else
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="kp-button kp-button-white">{{ __('Create your account') }} <span aria-hidden="true">→</span></a>
                                @endif
                                <a href="{{ route('demo.checkout') }}" class="kp-cta-secondary">{{ __('Try live demo') }}</a>
                            @endauth
                        </div>
                    </div>
                    <div class="kp-cta-art" aria-hidden="true">
                        <div class="kp-cta-circle"></div>
                        <div class="kp-cta-tile kp-cta-tile-one">₱</div>
                        <div class="kp-cta-tile kp-cta-tile-two">✓</div>
                        <div class="kp-cta-tile kp-cta-tile-three">K</div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="kp-footer">
        <div class="kp-container">
            <div class="kp-footer-main">
                <div class="kp-footer-brand">
                    <x-landing-logo-mark size="sm" />
                    <p>{{ __('Payment tools for a smoother business day.') }}</p>
                </div>
                <div class="kp-footer-links">
                    <div><strong>{{ __('Platform') }}</strong><a href="#platform">{{ __('Overview') }}</a><a href="#solutions">{{ __('Solutions') }}</a><a href="#integrations">{{ __('Integrations') }}</a></div>
                    <div><strong>{{ __('Developers') }}</strong><a href="{{ route('public.api-docs') }}">{{ __('API documentation') }}</a><a href="{{ route('demo.checkout') }}">{{ __('Demo checkout') }}</a></div>
                    <div><strong>{{ __('Account') }}</strong>
                        @if (Route::has('login'))<a href="{{ route('login') }}">{{ __('Sign in') }}</a>@endif
                        @if (Route::has('register'))<a href="{{ route('register') }}">{{ __('Get started') }}</a>@endif
                    </div>
                </div>
            </div>
            <div class="kp-footer-bottom">
                <span>© {{ date('Y') }} KemoPay. {{ __('All rights reserved.') }}</span>
                <a href="{{ route('health') }}">{{ __('System status') }}</a>
            </div>
        </div>
    </footer>

    <script>
        const menuToggle = document.querySelector('.kp-menu-toggle');
        const mobileNav = document.getElementById('kemopay-mobile-nav');

        menuToggle?.addEventListener('click', () => {
            const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';

            menuToggle.setAttribute('aria-expanded', String(!isExpanded));
            mobileNav.hidden = isExpanded;
            document.body.classList.toggle('kp-menu-open', !isExpanded);
        });

        mobileNav?.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                mobileNav.hidden = true;
                menuToggle?.setAttribute('aria-expanded', 'false');
                document.body.classList.remove('kp-menu-open');
            });
        });
    </script>
</body>
</html>
