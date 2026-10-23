<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <title>KemoPay API Documentation</title>
    <meta name="description" content="Integrate payments using KemoPay APIs. Public developer documentation for endpoints, webhooks, and security best practices.">

    <meta property="og:title" content="KemoPay API Documentation">
    <meta property="og:description" content="Integrate payments using KemoPay APIs.">
    <meta property="og:url" content="{{ url('/api-docs') }}">
    <meta property="og:type" content="website">
    <link rel="canonical" href="{{ url('/api-docs') }}">

    <link rel="icon" href="{{ asset('Modern K Payment App Icon.png') }}" type="image/png">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|space-mono:400,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance

    <style>
        body { font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif; }
        code, pre { font-family: 'Space Mono', ui-monospace, SFMono-Regular, Menlo, monospace; }
        html { scroll-padding-top: 5.5rem; }

        .code-card {
            background: #111827;
            border: 1px solid #374151;
            box-shadow: 0 14px 24px -18px rgba(0,0,0,.85), inset 0 1px 0 rgba(255,255,255,.04);
            color: #e5e7eb;
        }

        .copy-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: rgba(55,65,81,0.8);
            color: #e5e7eb;
            font-size: 0.7rem;
            padding: 0.25rem 0.6rem;
            border-radius: 0.375rem;
            border: 1px solid rgba(75,85,99,0.8);
            cursor: pointer;
            transition: background 0.15s;
        }
        .copy-btn:hover { background: rgba(75,85,99,0.95); }
        .copy-btn.copied { background: #059669; border-color: #047857; color: #fff; }
    </style>
</head>
<body class="bg-zinc-50 text-zinc-800 antialiased dark:bg-zinc-950 dark:text-zinc-200">

    @php
        $apiBaseUrl = rtrim(url('/'), '/');
        $sectionHeading = 'text-lg font-semibold text-zinc-900 dark:text-zinc-100';
        $subHeading = 'mt-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400';
        $bodyText = 'mt-1 text-sm text-zinc-600 dark:text-zinc-400';
        $cardWrap = 'rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900';
        $codeCard = 'code-card mt-2 overflow-x-auto rounded-2xl p-4 text-xs';
        $calloutInfo = 'mt-3 rounded-lg border border-violet-200 bg-violet-50 p-3 text-sm text-violet-900 dark:border-violet-900 dark:bg-violet-950/50 dark:text-violet-200';
        $calloutWarn = 'mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-200';
        $calloutDanger = 'mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-200';
        $fieldKey = 'font-mono text-[0.8125rem] text-zinc-900 dark:text-zinc-100';
    @endphp

    <header class="sticky top-0 z-30 border-b border-zinc-200 bg-white/80 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/80">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100">
                <img src="{{ asset('Modern K Payment App Icon.png') }}" alt="" class="h-8 w-8 rounded-lg shadow-sm">
                <span class="text-base font-semibold tracking-tight">KemoPay</span>
            </a>
            <nav class="hidden items-center gap-5 text-sm text-zinc-600 dark:text-zinc-400 sm:flex">
                <a href="#flow" class="hover:text-zinc-900 dark:hover:text-zinc-100">Flow</a>
                <a href="#endpoints" class="hover:text-zinc-900 dark:hover:text-zinc-100">Endpoints</a>
                <a href="#wallet" class="hover:text-zinc-900 dark:hover:text-zinc-100">Wallet</a>
                <a href="#webhooks" class="hover:text-zinc-900 dark:hover:text-zinc-100">Webhooks</a>
                <a href="#security" class="hover:text-zinc-900 dark:hover:text-zinc-100">Security</a>
                <a href="{{ route('home') }}" class="font-medium text-violet-600 hover:text-violet-700 dark:text-violet-400 dark:hover:text-violet-300">KemoPay &rarr;</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:py-14">

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-gradient-to-br from-white via-violet-50/40 to-violet-50/40 p-8 shadow-sm dark:border-zinc-800 dark:from-zinc-900 dark:via-zinc-900 dark:to-violet-950/20 sm:p-10">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-violet-200 bg-violet-100/60 px-2.5 py-1 text-[0.7rem] font-semibold uppercase tracking-wide text-violet-700 dark:border-violet-900 dark:bg-violet-950/50 dark:text-violet-300">
                <span class="h-1.5 w-1.5 rounded-full bg-violet-500"></span>
                Developer Docs
            </span>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-4xl">KemoPay API Documentation</h1>
            <p class="mt-3 max-w-2xl text-base text-zinc-600 dark:text-zinc-400">Integrate payments using KemoPay APIs. This is the public reference for endpoints, payment flow, webhooks, and security best practices.</p>

            <div class="mt-6 flex flex-wrap items-center gap-3 text-sm">
                <a href="#flow" class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-900 px-3.5 py-2 font-medium text-white shadow-sm transition hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white">
                    Get started
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                </a>
                <a href="#endpoints" class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3.5 py-2 font-medium text-zinc-800 shadow-sm transition hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-zinc-600">
                    View endpoints
                </a>
            </div>
        </section>

        <div class="mt-8 flex flex-col gap-6">

            <section class="{{ $cardWrap }}">
                <h2 class="{{ $sectionHeading }}">Overview</h2>
                <ul class="mt-3 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <li>KemoPay is a payment orchestration platform that lets you accept payments through multiple rails with a single integration.</li>
                    <li>PayMongo powers QR Ph, GCash, Maya, GrabPay, and ShopeePay. The methods available to you depend on your merchant settings and KemoPay’s active PayMongo account capabilities.</li>
                    <li>Authenticate merchant API calls with a Bearer token issued from your merchant dashboard. Keep this token server-side only.</li>
                    <li>After you configure a callback URL, final payment status updates are delivered by webhook. Treat that webhook as the source of truth.</li>
                </ul>
            </section>

            <section class="{{ $cardWrap }}">
                <h2 class="{{ $sectionHeading }}">Base URL</h2>
                <p class="{{ $bodyText }}">All production API calls are made against the KemoPay production host. Prepend this base URL to every endpoint shown below.</p>
                <pre class="{{ $codeCard }}"><span style="color:#f4f4f5">{{ $apiBaseUrl }}</span></pre>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-500">Marketing site: <a href="{{ route('home') }}" class="text-violet-600 hover:underline dark:text-violet-400">KemoPay</a></p>
            </section>

            <section id="flow" class="{{ $cardWrap }}">
                <h2 class="{{ $sectionHeading }}">Payment Flow</h2>
                <p class="{{ $bodyText }}">Every payment moves through these four steps. Implement them in this exact order.</p>

                <ol class="mt-4 space-y-4 text-sm text-zinc-700 dark:text-zinc-300">
                    <li>
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-6 w-6 flex-none items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white dark:bg-zinc-100 dark:text-zinc-900">1</span>
                            <div>
                                <p class="font-semibold text-zinc-900 dark:text-zinc-100">Create Payment</p>
                                <p class="mt-1">Your backend calls <code class="{{ $fieldKey }}">POST /api/payments</code> with the amount, currency, gateway, and your internal reference. Never call this from the browser.</p>
                            </div>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-6 w-6 flex-none items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white dark:bg-zinc-100 dark:text-zinc-900">2</span>
                            <div>
                                <p class="font-semibold text-zinc-900 dark:text-zinc-100">Display QR or Redirect</p>
                                <p class="mt-1">Render <code class="{{ $fieldKey }}">qr_data</code> as a QR image, or send the customer to <code class="{{ $fieldKey }}">redirect_url</code> if the gateway returned one. Show a countdown using <code class="{{ $fieldKey }}">expires_at</code>.</p>
                            </div>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-6 w-6 flex-none items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white dark:bg-zinc-100 dark:text-zinc-900">3</span>
                            <div>
                                <p class="font-semibold text-zinc-900 dark:text-zinc-100">Wait for Webhook</p>
                                <p class="mt-1">After you configure a webhook URL, KemoPay notifies your server asynchronously whenever the payment <code class="{{ $fieldKey }}">status</code> changes (for example pending → paid). This callback is the source of truth — not the browser.</p>
                                <div class="{{ $calloutWarn }}">Do NOT trust any status observed in the browser. The tab can be closed, reloaded, or tampered with. Only the webhook (or a backend status check) is authoritative.</div>
                            </div>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-6 w-6 flex-none items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white dark:bg-zinc-100 dark:text-zinc-900">4</span>
                            <div>
                                <p class="font-semibold text-zinc-900 dark:text-zinc-100">Verify via API</p>
                                <p class="mt-1">Before fulfilling the order, confirm the payment with <code class="{{ $fieldKey }}">GET /api/payments/{payment_id}/status</code> from your backend. Use this as a fallback when webhooks are delayed and as a final guard at checkout.</p>
                            </div>
                        </div>
                    </li>
                </ol>
            </section>

            <section class="{{ $cardWrap }}">
                <h2 class="{{ $sectionHeading }}">Authentication</h2>
                <p class="{{ $bodyText }}">Every merchant API call must include your API key as a Bearer token. Your API key is issued privately in your merchant dashboard — never hard-code or share it.</p>
                <pre class="{{ $codeCard }}"><span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span></pre>
                <div class="{{ $calloutDanger }}">
                    <span class="font-semibold">Never expose your API key in the frontend.</span>
                    Do not embed it in JavaScript, mobile apps, or public repositories. All merchant API calls must originate from your own backend.
                </div>
            </section>

            <section id="endpoints" class="{{ $cardWrap }}">
                <h2 class="{{ $sectionHeading }}">Get Enabled Gateways</h2>
                <p class="{{ $bodyText }}">Returns payment options enabled for your merchant account and available on KemoPay’s PayMongo account. Methods missing from PayMongo capabilities are omitted, even if a merchant toggle is on. Use each returned <code class="{{ $fieldKey }}">code</code> as the <code class="{{ $fieldKey }}">gateway</code> value when creating a payment.</p>

                <h3 class="{{ $subHeading }}">Sample Request</h3>
                <pre class="{{ $codeCard }}"><span style="color:#34d399">GET</span> <span style="color:#f4f4f5">/api/gateways/enabled HTTP/1.1</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span>
<span style="color:#7dd3fc">Accept:</span> <span style="color:#f4f4f5">application/json</span></pre>

                <h3 class="{{ $subHeading }}">Sample Response</h3>
                <pre class="{{ $codeCard }}">{
<span style="color:#67e8f9">"success"</span>: <span style="color:#6ee7b7">true</span>,
<span style="color:#67e8f9">"data"</span>: {
  <span style="color:#67e8f9">"gateways"</span>: [
    { <span style="color:#67e8f9">"code"</span>: <span style="color:#fcd34d">"gcash"</span>, <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"GCash"</span> },
    { <span style="color:#67e8f9">"code"</span>: <span style="color:#fcd34d">"maya"</span>,  <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"Maya"</span>  },
    { <span style="color:#67e8f9">"code"</span>: <span style="color:#fcd34d">"grab_pay"</span>, <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"GrabPay"</span> },
    { <span style="color:#67e8f9">"code"</span>: <span style="color:#fcd34d">"shopeepay"</span>, <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"ShopeePay"</span> },
    { <span style="color:#67e8f9">"code"</span>: <span style="color:#fcd34d">"qrph"</span>,  <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"QR Ph"</span>  }
  ],
  <span style="color:#67e8f9">"count"</span>: <span style="color:#c4b5fd">5</span>,
  <span style="color:#67e8f9">"provider_availability"</span>: {
    <span style="color:#67e8f9">"paymongo"</span>: {
      <span style="color:#67e8f9">"status"</span>: <span style="color:#fcd34d">"verified"</span>,
      <span style="color:#67e8f9">"available_methods"</span>: [<span style="color:#fcd34d">"qrph"</span>, <span style="color:#fcd34d">"gcash"</span>, <span style="color:#fcd34d">"maya"</span>, <span style="color:#fcd34d">"grab_pay"</span>, <span style="color:#fcd34d">"shopeepay"</span>],
      <span style="color:#67e8f9">"methods"</span>: {
        <span style="color:#67e8f9">"qrph"</span>: <span style="color:#fcd34d">"available"</span>,
        <span style="color:#67e8f9">"gcash"</span>: <span style="color:#fcd34d">"available"</span>
      }
    }
  },
  <span style="color:#67e8f9">"merchant"</span>: {
    <span style="color:#67e8f9">"id"</span>: <span style="color:#fcd34d">"merchant-id"</span>,
    <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"Your business name"</span>,
    <span style="color:#67e8f9">"logo"</span>: <span style="color:#fda4af">null</span>,
    <span style="color:#67e8f9">"theme_color"</span>: <span style="color:#fcd34d">"#1D4ED8"</span>
  }
},
<span style="color:#67e8f9">"error"</span>: <span style="color:#fda4af">null</span>
}</pre>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400"><code class="{{ $fieldKey }}">provider_availability.paymongo.methods</code> reports whether each method is available, unavailable, or unverified on KemoPay’s shared PayMongo account. If capabilities cannot be verified, PayMongo methods are omitted. PayMongo <code class="{{ $fieldKey }}">payment_method_not_allowed</code> errors return HTTP 422 before KemoPay creates a payment record; a capability lookup failure returns HTTP 503. KemoPay accepts only PayMongo payment methods. The provider-only <code class="{{ $fieldKey }}">paymongo</code> code is not selectable.</p>
            </section>

            <section class="{{ $cardWrap }}">
                <h2 class="{{ $sectionHeading }}">Create Payment</h2>
                <p class="{{ $bodyText }}">Creates a PHP payment and returns the data you need to collect funds (QR or redirect URL). The request amount is the product value. KemoPay adds its configured platform fee to the amount charged to the customer, so the merchant receives the full requested product value after the KemoPay fee is allocated. PayMongo methods currently accept amounts from PHP 1.00; GCash, Maya, GrabPay, and ShopeePay are capped at PHP 100,000, while QR Ph is capped at PHP 9,999,999.99.</p>

                <h3 class="{{ $subHeading }}">Sample Request</h3>
                <pre class="{{ $codeCard }}"><span style="color:#34d399">POST</span> <span style="color:#f4f4f5">/api/payments HTTP/1.1</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span>
<span style="color:#7dd3fc">Idempotency-Key:</span> <span style="color:#fcd34d">order-payment-1001</span>
<span style="color:#7dd3fc">Accept:</span> <span style="color:#f4f4f5">application/json</span>
<span style="color:#7dd3fc">Content-Type:</span> <span style="color:#f4f4f5">application/json</span></pre>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">An optional <code class="{{ $fieldKey }}">Idempotency-Key</code> header makes a repeated payment request return the original result. Reuse the same key only for retries of the same request.</p>

                <h3 class="{{ $subHeading }}">JSON Payload</h3>
                <pre class="{{ $codeCard }}">{
<span style="color:#67e8f9">"amount"</span>: <span style="color:#c4b5fd">500.00</span>,
<span style="color:#67e8f9">"currency"</span>: <span style="color:#fcd34d">"PHP"</span>,
<span style="color:#67e8f9">"gateway"</span>: <span style="color:#fcd34d">"gcash"</span>,
<span style="color:#67e8f9">"reference"</span>: <span style="color:#fcd34d">"ORDER-20260228-0001"</span>
}</pre>

                <h3 class="{{ $subHeading }}">cURL Example</h3>
                <div class="relative">
                    <button type="button" class="copy-btn" data-copy-target="#curl-create">Copy</button>
<pre id="curl-create" class="{{ $codeCard }}"><span style="color:#f4f4f5">curl -X POST {{ $apiBaseUrl }}/api/payments \
  -H </span><span style="color:#fcd34d">"Authorization: Bearer YOUR_API_KEY"</span><span style="color:#f4f4f5"> \
  -H </span><span style="color:#fcd34d">"Content-Type: application/json"</span><span style="color:#f4f4f5"> \
  -H </span><span style="color:#fcd34d">"Accept: application/json"</span><span style="color:#f4f4f5"> \
  -d </span><span style="color:#fcd34d">'{"amount":500.00,"currency":"PHP","gateway":"gcash","reference":"ORDER-20260228-0001"}'</span></pre>
                </div>

                <h3 class="{{ $subHeading }}">Sample Response</h3>
                <pre class="{{ $codeCard }}">{
<span style="color:#67e8f9">"success"</span>: <span style="color:#6ee7b7">true</span>,
<span style="color:#67e8f9">"data"</span>: {
  <span style="color:#67e8f9">"payment_id"</span>: <span style="color:#fcd34d">"uuid-value"</span>,
  <span style="color:#67e8f9">"transaction_id"</span>: <span style="color:#fcd34d">"uuid-value"</span>,
  <span style="color:#67e8f9">"gateway"</span>: <span style="color:#fcd34d">"gcash"</span>,
  <span style="color:#67e8f9">"amount"</span>: <span style="color:#c4b5fd">500</span>,
  <span style="color:#67e8f9">"product_amount"</span>: <span style="color:#c4b5fd">500</span>,
  <span style="color:#67e8f9">"currency"</span>: <span style="color:#fcd34d">"PHP"</span>,
  <span style="color:#67e8f9">"gross_amount"</span>: <span style="color:#c4b5fd">507.5</span>,
  <span style="color:#67e8f9">"gatewayhub_platform_fee_percent"</span>: <span style="color:#c4b5fd">1.5</span>,
  <span style="color:#67e8f9">"gatewayhub_platform_fee"</span>: <span style="color:#c4b5fd">7.5</span>,
  <span style="color:#67e8f9">"gatewayhub_net_amount"</span>: <span style="color:#c4b5fd">500</span>,
  <span style="color:#67e8f9">"status"</span>: <span style="color:#fcd34d">"pending"</span>,
  <span style="color:#67e8f9">"qr_data"</span>: <span style="color:#fcd34d">"000201..."</span>,
  <span style="color:#67e8f9">"expires_at"</span>: <span style="color:#fcd34d">"2026-02-28T12:00:00+08:00"</span>,
  <span style="color:#67e8f9">"redirect_url"</span>: <span style="color:#fda4af">null</span>,
  <span style="color:#67e8f9">"checkout_url"</span>: <span style="color:#fda4af">null</span>,
  <span style="color:#67e8f9">"merchant"</span>: {
    <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"Your business name"</span>,
    <span style="color:#67e8f9">"logo"</span>: <span style="color:#fcd34d">"https://..."</span>,
    <span style="color:#67e8f9">"theme_color"</span>: <span style="color:#fcd34d">"#1D4ED8"</span>
  }
},
<span style="color:#67e8f9">"error"</span>: <span style="color:#fda4af">null</span>
}</pre>

                <h3 class="{{ $subHeading }}">Response Field Reference</h3>
                <dl class="mt-2 space-y-3 text-sm text-zinc-700 dark:text-zinc-300">
                    <div>
                        <dt class="{{ $fieldKey }}">payment_id</dt>
                        <dd class="mt-0.5">Unique identifier for this payment. Store it against your order and use it for every status check or webhook match.</dd>
                    </div>
                    <div>
                        <dt class="{{ $fieldKey }}">transaction_id</dt>
                        <dd class="mt-0.5">Same value as <code class="{{ $fieldKey }}">payment_id</code> for compatibility with clients that expect a transaction identifier.</dd>
                    </div>
                    <div>
                        <dt class="{{ $fieldKey }}">qr_data</dt>
                        <dd class="mt-0.5">QR value returned by the gateway. It can be an EMVCo payload string that your app must encode, or a data URI image that can be used directly as an image source. Do not modify the value.</dd>
                    </div>
                    <div>
                        <dt class="{{ $fieldKey }}">expires_at</dt>
                        <dd class="mt-0.5">ISO-8601 timestamp of when the QR or redirect session expires. After this, the payment cannot be completed.</dd>
                    </div>
                    <div>
                        <dt class="{{ $fieldKey }}">redirect_url</dt>
                        <dd class="mt-0.5">If the gateway returns a hosted checkout page or wallet deep link, send the customer here. When null, use qr_data.</dd>
                    </div>
                    <div>
                        <dt class="{{ $fieldKey }}">checkout_url</dt>
                        <dd class="mt-0.5">Same as <code class="{{ $fieldKey }}">redirect_url</code> when present; otherwise null.</dd>
                    </div>
                    <div>
                        <dt class="{{ $fieldKey }}">merchant</dt>
                        <dd class="mt-0.5">Branding for hosted checkout: display name, logo URL, and theme color.</dd>
                    </div>
                    <div>
                        <dt class="{{ $fieldKey }}">status</dt>
                        <dd class="mt-0.5">Current payment state. On creation this is always <code class="{{ $fieldKey }}">pending</code>. Final states arrive via webhook.</dd>
                    </div>
                    <div>
                        <dt class="{{ $fieldKey }}">amount</dt>
                        <dd class="mt-0.5">The requested product value. It matches <code class="{{ $fieldKey }}">product_amount</code> and remains the same amount your integration sent in the request.</dd>
                    </div>
                    <div>
                        <dt class="{{ $fieldKey }}">gross_amount</dt>
                        <dd class="mt-0.5">The total charged to the customer: product_amount plus the KemoPay platform fee.</dd>
                    </div>
                    <div>
                        <dt class="{{ $fieldKey }}">product_amount</dt>
                        <dd class="mt-0.5">The product value requested by the merchant. The merchant's net after the KemoPay fee equals this amount.</dd>
                    </div>
                    <div>
                        <dt class="{{ $fieldKey }}">gatewayhub_platform_fee_percent</dt>
                        <dd class="mt-0.5">The configured KemoPay platform fee rate. The example value is illustrative and may change with platform settings.</dd>
                    </div>
                    <div>
                        <dt class="{{ $fieldKey }}">gatewayhub_platform_fee</dt>
                        <dd class="mt-0.5">The KemoPay fee added to the customer total, rounded to two decimals. The quoted fee is returned on creation. Provider fees are not included.</dd>
                    </div>
                    <div>
                        <dt class="{{ $fieldKey }}">gatewayhub_net_amount</dt>
                        <dd class="mt-0.5">The merchant's expected net after the KemoPay fee. For a successful payment, it equals product_amount.</dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">The fee fields retain their existing JSON names for API compatibility. They represent KemoPay platform fees.</p>
            </section>

            <section class="{{ $cardWrap }}">
                <h2 class="{{ $sectionHeading }}">Get Payment Status</h2>
                <p class="{{ $bodyText }}">Fetch the current status of a payment from your backend. Call this before fulfilling an order, or as a fallback when a webhook is delayed.</p>

                <h3 class="{{ $subHeading }}">Sample Request</h3>
                <pre class="{{ $codeCard }}"><span style="color:#34d399">GET</span> <span style="color:#f4f4f5">/api/payments/{payment_id}/status HTTP/1.1</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span>
<span style="color:#7dd3fc">Accept:</span> <span style="color:#f4f4f5">application/json</span></pre>

                <h3 class="{{ $subHeading }}">cURL Example</h3>
                <div class="relative">
                    <button type="button" class="copy-btn" data-copy-target="#curl-status">Copy</button>
<pre id="curl-status" class="{{ $codeCard }}"><span style="color:#f4f4f5">curl -X GET {{ $apiBaseUrl }}/api/payments/</span><span style="color:#fcd34d">{payment_id}</span><span style="color:#f4f4f5">/status \
  -H </span><span style="color:#fcd34d">"Authorization: Bearer YOUR_API_KEY"</span><span style="color:#f4f4f5"> \
  -H </span><span style="color:#fcd34d">"Accept: application/json"</span></pre>
                <h3 class="{{ $subHeading }}">Sample Response</h3>
                <pre class="{{ $codeCard }}">{
<span style="color:#67e8f9">"success"</span>: <span style="color:#6ee7b7">true</span>,
<span style="color:#67e8f9">"data"</span>: {
  <span style="color:#67e8f9">"status"</span>: <span style="color:#fcd34d">"success"</span>,
  <span style="color:#67e8f9">"product_amount"</span>: <span style="color:#c4b5fd">500</span>,
  <span style="color:#67e8f9">"gross_amount"</span>: <span style="color:#c4b5fd">507.5</span>,
  <span style="color:#67e8f9">"gatewayhub_platform_fee_percent"</span>: <span style="color:#c4b5fd">1.5</span>,
  <span style="color:#67e8f9">"gatewayhub_platform_fee"</span>: <span style="color:#c4b5fd">7.5</span>,
  <span style="color:#67e8f9">"gatewayhub_net_amount"</span>: <span style="color:#c4b5fd">500</span>
},
<span style="color:#67e8f9">"error"</span>: <span style="color:#fda4af">null</span>
}</pre>
                </div>
            </section>

            <section id="wallet" class="{{ $cardWrap }}">
                <h2 class="{{ $sectionHeading }}">Merchant Wallet</h2>
                <p class="{{ $bodyText }}">Read your KemoPay wallet balance, net payment receipts, and send available funds to a bank or e-wallet. The customer's total includes the KemoPay platform fee, so a paid payment credits the merchant's full product_amount. PayMongo processing fees are separate. These endpoints are available when wallet settlement is enabled; otherwise they return 404.</p>

                <h3 class="{{ $subHeading }}">Get Wallet Balance</h3>
                <pre class="{{ $codeCard }}"><span style="color:#34d399">GET</span> <span style="color:#f4f4f5">/api/wallets/balances?currency=PHP HTTP/1.1</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span></pre>

                <h3 class="{{ $subHeading }}">Sample Balance Response</h3>
                <pre class="{{ $codeCard }}">{
  <span style="color:#67e8f9">"success"</span>: <span style="color:#6ee7b7">true</span>,
  <span style="color:#67e8f9">"data"</span>: {
    <span style="color:#67e8f9">"currency"</span>: <span style="color:#fcd34d">"PHP"</span>,
    <span style="color:#67e8f9">"wallet_balance"</span>: <span style="color:#c4b5fd">500.00</span>,
    <span style="color:#67e8f9">"available_balance"</span>: <span style="color:#c4b5fd">500.00</span>,
    <span style="color:#67e8f9">"settled_balance"</span>: <span style="color:#c4b5fd">0</span>,
    <span style="color:#67e8f9">"tunnel_balance"</span>: <span style="color:#c4b5fd">500.00</span>,
    <span style="color:#67e8f9">"real_balance"</span>: <span style="color:#c4b5fd">0</span>,
    <span style="color:#67e8f9">"pending_net_settlement"</span>: <span style="color:#c4b5fd">500.00</span>,
    <span style="color:#67e8f9">"today_gross"</span>: <span style="color:#c4b5fd">507.50</span>,
    <span style="color:#67e8f9">"today_platform_fees"</span>: <span style="color:#c4b5fd">7.50</span>,
    <span style="color:#67e8f9">"today_net_settled"</span>: <span style="color:#c4b5fd">0</span>,
    <span style="color:#67e8f9">"as_of"</span>: <span style="color:#fcd34d">"2026-10-23T12:00:00+08:00"</span>
  },
  <span style="color:#67e8f9">"error"</span>: <span style="color:#fda4af">null</span>
}</pre>

                <h3 class="{{ $subHeading }}">List Wallet Receipts</h3>
                <p class="{{ $bodyText }}">Returns the latest net payment credits. Use <code class="{{ $fieldKey }}">currency</code> to filter by currency and <code class="{{ $fieldKey }}">per_page</code> to set a page size from 1 to 100 (default 20).</p>
                <pre class="{{ $codeCard }}"><span style="color:#34d399">GET</span> <span style="color:#f4f4f5">/api/wallets/transactions?currency=PHP&amp;per_page=20 HTTP/1.1</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span></pre>

                <h3 class="{{ $subHeading }}">Sample Transaction Response</h3>
                <pre class="{{ $codeCard }}">{
  <span style="color:#67e8f9">"success"</span>: <span style="color:#6ee7b7">true</span>,
  <span style="color:#67e8f9">"data"</span>: {
    <span style="color:#67e8f9">"currency"</span>: <span style="color:#fcd34d">"PHP"</span>,
    <span style="color:#67e8f9">"transactions"</span>: [{
      <span style="color:#67e8f9">"id"</span>: <span style="color:#c4b5fd">201</span>,
      <span style="color:#67e8f9">"payment_id"</span>: <span style="color:#fcd34d">"payment-uuid"</span>,
      <span style="color:#67e8f9">"reference"</span>: <span style="color:#fcd34d">"ORDER-20261023-0001"</span>,
      <span style="color:#67e8f9">"provider_reference"</span>: <span style="color:#fcd34d">"provider-ref-123"</span>,
      <span style="color:#67e8f9">"gateway"</span>: <span style="color:#fcd34d">"gcash"</span>,
      <span style="color:#67e8f9">"status"</span>: <span style="color:#fcd34d">"paid"</span>,
      <span style="color:#67e8f9">"currency"</span>: <span style="color:#fcd34d">"PHP"</span>,
      <span style="color:#67e8f9">"product_amount"</span>: <span style="color:#c4b5fd">500</span>,
      <span style="color:#67e8f9">"gross_amount"</span>: <span style="color:#c4b5fd">507.5</span>,
      <span style="color:#67e8f9">"platform_fee"</span>: <span style="color:#c4b5fd">7.50</span>,
      <span style="color:#67e8f9">"net_amount"</span>: <span style="color:#c4b5fd">500.00</span>,
      <span style="color:#67e8f9">"wallet_status"</span>: <span style="color:#fcd34d">"available"</span>,
      <span style="color:#67e8f9">"created_at"</span>: <span style="color:#fcd34d">"2026-10-23T12:00:00+08:00"</span>,
      <span style="color:#67e8f9">"settled_at"</span>: <span style="color:#fda4af">null</span>
    }],
    <span style="color:#67e8f9">"pagination"</span>: { <span style="color:#67e8f9">"current_page"</span>: <span style="color:#c4b5fd">1</span>, <span style="color:#67e8f9">"last_page"</span>: <span style="color:#c4b5fd">1</span>, <span style="color:#67e8f9">"per_page"</span>: <span style="color:#c4b5fd">20</span>, <span style="color:#67e8f9">"total"</span>: <span style="color:#c4b5fd">1</span> }
  },
  <span style="color:#67e8f9">"error"</span>: <span style="color:#fda4af">null</span>
}</pre>
                <h3 class="{{ $subHeading }}">Get Supported Banks and E-Wallets</h3>
                <p class="{{ $bodyText }}">Set <code class="{{ $fieldKey }}">provider</code> to <code class="{{ $fieldKey }}">instapay</code> or <code class="{{ $fieldKey }}">pesonet</code>. Use a returned institution's <code class="{{ $fieldKey }}">provider_code</code> as the destination account's <code class="{{ $fieldKey }}">bic</code>.</p>
                <pre class="{{ $codeCard }}"><span style="color:#34d399">GET</span> <span style="color:#f4f4f5">/api/wallets/payout-institutions?provider=instapay HTTP/1.1</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span></pre>
                <pre class="{{ $codeCard }}">{
  <span style="color:#67e8f9">"success"</span>: <span style="color:#6ee7b7">true</span>,
  <span style="color:#67e8f9">"data"</span>: {
    <span style="color:#67e8f9">"provider"</span>: <span style="color:#fcd34d">"instapay"</span>,
    <span style="color:#67e8f9">"institutions"</span>: [{ <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"Sample Bank"</span>, <span style="color:#67e8f9">"provider_code"</span>: <span style="color:#fcd34d">"BNORPHMM"</span> }]
  },
  <span style="color:#67e8f9">"error"</span>: <span style="color:#fda4af">null</span>
}</pre>

                <h3 class="{{ $subHeading }}">Create an External Payout</h3>
                <p class="{{ $bodyText }}">The payout amount is reserved from your KemoPay wallet balance. Each request needs a unique <code class="{{ $fieldKey }}">Idempotency-Key</code>. InstaPay supports PHP 1.00-50,000.00 per transfer; PESONet supports PHP 1.00-10,000,000.00. Use a new key for a new payout and the same key to retry it.</p>
                <pre class="{{ $codeCard }}"><span style="color:#34d399">POST</span> <span style="color:#f4f4f5">/api/wallets/payouts HTTP/1.1</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span>
<span style="color:#7dd3fc">Idempotency-Key:</span> <span style="color:#fcd34d">order-payout-1001</span>
<span style="color:#7dd3fc">Content-Type:</span> <span style="color:#f4f4f5">application/json</span>

{
  <span style="color:#67e8f9">"provider"</span>: <span style="color:#fcd34d">"instapay"</span>,
  <span style="color:#67e8f9">"amount"</span>: <span style="color:#c4b5fd">500.00</span>,
  <span style="color:#67e8f9">"currency"</span>: <span style="color:#fcd34d">"PHP"</span>,
  <span style="color:#67e8f9">"destination_account"</span>: {
    <span style="color:#67e8f9">"number"</span>: <span style="color:#fcd34d">"1234567890"</span>,
    <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"Juan Dela Cruz"</span>,
    <span style="color:#67e8f9">"bic"</span>: <span style="color:#fcd34d">"BNORPHMM"</span>,
    <span style="color:#67e8f9">"bank_name"</span>: <span style="color:#fcd34d">"Test Bank"</span>
  },
  <span style="color:#67e8f9">"purpose"</span>: <span style="color:#fcd34d">"merchant_payout"</span>,
  <span style="color:#67e8f9">"description"</span>: <span style="color:#fcd34d">"Weekly payout"</span>
}</pre>
                <h3 class="{{ $subHeading }}">Sample Payout Response</h3>
                <pre class="{{ $codeCard }}">{
  <span style="color:#67e8f9">"success"</span>: <span style="color:#6ee7b7">true</span>,
  <span style="color:#67e8f9">"data"</span>: {
    <span style="color:#67e8f9">"payout"</span>: {
      <span style="color:#67e8f9">"id"</span>: <span style="color:#fcd34d">"payout-uuid"</span>,
      <span style="color:#67e8f9">"provider"</span>: <span style="color:#fcd34d">"instapay"</span>,
      <span style="color:#67e8f9">"amount"</span>: <span style="color:#c4b5fd">500.00</span>,
      <span style="color:#67e8f9">"currency"</span>: <span style="color:#fcd34d">"PHP"</span>,
      <span style="color:#67e8f9">"status"</span>: <span style="color:#fcd34d">"pending"</span>,
      <span style="color:#67e8f9">"reference_number"</span>: <span style="color:#fcd34d">"KP01J..."</span>,
      <span style="color:#67e8f9">"provider_reference_number"</span>: <span style="color:#fda4af">null</span>,
      <span style="color:#67e8f9">"provider_fee"</span>: <span style="color:#fda4af">null</span>,
      <span style="color:#67e8f9">"destination_account"</span>: { <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"Juan Dela Cruz"</span>, <span style="color:#67e8f9">"bank_name"</span>: <span style="color:#fcd34d">"Test Bank"</span>, <span style="color:#67e8f9">"bic"</span>: <span style="color:#fcd34d">"BNORPHMM"</span>, <span style="color:#67e8f9">"number"</span>: <span style="color:#fcd34d">"••••••7890"</span> },
      <span style="color:#67e8f9">"failure"</span>: <span style="color:#fda4af">null</span>,
      <span style="color:#67e8f9">"created_at"</span>: <span style="color:#fcd34d">"2026-10-23T12:00:00.000000Z"</span>,
      <span style="color:#67e8f9">"completed_at"</span>: <span style="color:#fda4af">null</span>
    }
  },
  <span style="color:#67e8f9">"error"</span>: <span style="color:#fda4af">null</span>
}</pre>
                <p class="{{ $bodyText }}">Statuses are <code class="{{ $fieldKey }}">submitting</code>, <code class="{{ $fieldKey }}">submission_unknown</code>, <code class="{{ $fieldKey }}">pending</code>, <code class="{{ $fieldKey }}">succeeded</code>, and <code class="{{ $fieldKey }}">failed</code>. A <code class="{{ $fieldKey }}">submission_unknown</code> status means the provider may have received the transfer but KemoPay has not confirmed it; check the payout status and do not create another payout with a different key.</p>

                <h3 class="{{ $subHeading }}">List Payouts and Check Status</h3>
                <pre class="{{ $codeCard }}"><span style="color:#34d399">GET</span> <span style="color:#f4f4f5">/api/wallets/payouts?currency=PHP&amp;per_page=20 HTTP/1.1</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span>

<span style="color:#34d399">GET</span> <span style="color:#f4f4f5">/api/wallets/payouts/{payout_id} HTTP/1.1</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span></pre>
                <p class="{{ $calloutInfo }}">Payouts are sent from KemoPay's activated PayMongo wallet. KemoPay manages the internal PayMongo webhook for <code class="{{ $fieldKey }}">transfer.outward.successful</code> and <code class="{{ $fieldKey }}">transfer.outward.failed</code>. Merchants set their payment callback URL under API Credentials.</p>
                <p class="{{ $bodyText }}">The payout list response contains <code class="{{ $fieldKey }}">data.payouts</code> and <code class="{{ $fieldKey }}">data.pagination</code>. The single-payout response contains <code class="{{ $fieldKey }}">data.payout</code>. Both are scoped to the authenticated merchant.</p>
                <p class="{{ $calloutInfo }}">Keep the merchant API key on your server. Do not call wallet endpoints directly from browser or mobile client code.</p>
            </section>

            <section id="webhooks" class="{{ $cardWrap }}">
                <h2 class="{{ $sectionHeading }}">Webhook Handling</h2>
                <p class="{{ $bodyText }}">A webhook is a server-to-server notification. When a payment status changes, KemoPay sends an HTTP POST to your endpoint so you do not need to poll.</p>

                <div class="{{ $calloutDanger }}">
                    <span class="font-semibold">Rule of thumb:</span>
                    Always trust the webhook over any frontend polling. The browser can lie; the webhook cannot.
                </div>

                <h3 class="{{ $subHeading }}">Delivery</h3>
                <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">Register your HTTPS endpoint and signing secret on the API Credentials page. KemoPay delivers webhooks with these headers:</p>
                <pre class="{{ $codeCard }}"><span style="color:#34d399">POST</span> <span style="color:#f4f4f5">https://your-server.example.com/your-webhook-endpoint HTTP/1.1</span>
<span style="color:#7dd3fc">Content-Type:</span> <span style="color:#f4f4f5">application/json</span>
<span style="color:#7dd3fc">User-Agent:</span> <span style="color:#f4f4f5">KemoPay-Webhooks/1.0</span>
<span style="color:#7dd3fc">X-Merchant-Timestamp:</span> <span style="color:#fcd34d">1700000000</span>
<span style="color:#7dd3fc">X-Merchant-Signature:</span> <span style="color:#fcd34d">&lt;hex-hmac-sha256&gt;</span></pre>

                <h3 class="{{ $subHeading }}">How It Works</h3>
                <ul class="mt-2 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <li>KemoPay sends a POST whenever a payment’s <code class="{{ $fieldKey }}">status</code> changes (not only when it becomes paid).</li>
                    <li>Deliveries are asynchronous — usually within seconds, but network delays can happen.</li>
                    <li>Your endpoint must respond with HTTP 200 quickly (under ~5 seconds). Do heavy work in a queue.</li>
                    <li>If your endpoint errors or times out, KemoPay retries with backoff.</li>
                    <li>Duplicate deliveries are possible. Make your handler idempotent by keying on <code class="{{ $fieldKey }}">payment_id</code> and ignoring already-applied updates.</li>
                </ul>

                <h3 class="{{ $subHeading }}">Example Payload</h3>
                <pre class="{{ $codeCard }}">{
<span style="color:#67e8f9">"event"</span>: <span style="color:#fcd34d">"payment.updated"</span>,
<span style="color:#67e8f9">"timestamp"</span>: <span style="color:#fcd34d">"2026-02-28T12:00:00+08:00"</span>,
<span style="color:#67e8f9">"data"</span>: {
  <span style="color:#67e8f9">"payment_id"</span>: <span style="color:#fcd34d">"uuid-value"</span>,
  <span style="color:#67e8f9">"status"</span>: <span style="color:#fcd34d">"paid"</span>,
  <span style="color:#67e8f9">"amount"</span>: <span style="color:#c4b5fd">500</span>,
  <span style="color:#67e8f9">"currency"</span>: <span style="color:#fcd34d">"PHP"</span>,
  <span style="color:#67e8f9">"gateway"</span>: <span style="color:#fcd34d">"gcash"</span>,
  <span style="color:#67e8f9">"reference"</span>: <span style="color:#fcd34d">"ORDER-20260228-0001"</span>,
  <span style="color:#67e8f9">"product_amount"</span>: <span style="color:#c4b5fd">500</span>,
  <span style="color:#67e8f9">"gross_amount"</span>: <span style="color:#c4b5fd">507.5</span>,
  <span style="color:#67e8f9">"gatewayhub_platform_fee_percent"</span>: <span style="color:#c4b5fd">1.5</span>,
  <span style="color:#67e8f9">"gatewayhub_platform_fee"</span>: <span style="color:#c4b5fd">7.5</span>,
  <span style="color:#67e8f9">"gatewayhub_net_amount"</span>: <span style="color:#c4b5fd">500</span>,
  <span style="color:#67e8f9">"provider_reference"</span>: <span style="color:#fcd34d">"provider-ref-optional"</span>,
  <span style="color:#67e8f9">"paid_at"</span>: <span style="color:#fcd34d">"2026-02-28T12:01:42+08:00"</span>,
  <span style="color:#67e8f9">"created_at"</span>: <span style="color:#fcd34d">"2026-02-28T12:00:10+08:00"</span>,
  <span style="color:#67e8f9">"updated_at"</span>: <span style="color:#fcd34d">"2026-02-28T12:01:42+08:00"</span>
}
}</pre>

                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">The fee fields retain their existing JSON names for API compatibility. KemoPay adds the platform fee to the amount paid by the customer; provider processing or conversion fees remain separate.</p>

                <h3 class="{{ $subHeading }}">Signature Verification</h3>
                <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">Each delivery is signed with the same secret you configure for webhooks so you can verify it came from KemoPay.</p>
                <ul class="mt-2 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <li>Use the raw request body bytes exactly as received (no re-encoding or whitespace changes).</li>
                    <li>Let <code class="font-mono">timestamp</code> be the value of <code class="font-mono">X-Merchant-Timestamp</code> (Unix time as a decimal string).</li>
                    <li>Compute the hex digest <code class="font-mono">hash_hmac('sha256', timestamp + '.' + body, your_secret)</code> — the signing input is the timestamp, a single dot, then the raw JSON body.</li>
                    <li>Compare that digest to <code class="font-mono">X-Merchant-Signature</code> with a constant-time comparison (e.g. <code class="{{ $fieldKey }}">hash_equals</code> in PHP). Do not prefix with <code class="font-mono">sha256=</code>.</li>
                    <li>If they do not match, return 401 and do not act on the payload.</li>
                    <li>Optionally reject requests whose timestamp is too far from your server clock to limit replay windows.</li>
                </ul>

                <h3 class="{{ $subHeading }}">PHP Receiver Example</h3>
                <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">Save this as your HTTPS endpoint, set <code class="{{ $fieldKey }}">KEMOPAY_WEBHOOK_SECRET</code> in your server environment, and add your own idempotent order update where indicated.</p>
                <pre class="{{ $codeCard }}"><code>@verbatim
&lt;?php
$secret = getenv('KEMOPAY_WEBHOOK_SECRET');
$rawBody = file_get_contents('php://input');
$timestamp = $_SERVER['HTTP_X_MERCHANT_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_MERCHANT_SIGNATURE'] ?? '';

if (! is_string($secret) || $secret === '' || ! is_string($rawBody)
    || ! is_string($timestamp) || ! ctype_digit($timestamp)
    || ! is_string($signature) || abs(time() - (int) $timestamp) > 300) {
    http_response_code(401);
    exit;
}

$expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
if (! hash_equals($expected, $signature)) {
    http_response_code(401);
    exit;
}

try {
    $event = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    http_response_code(400);
    exit;
}

$data = is_array($event) ? ($event['data'] ?? null) : null;
if (! is_array($event)
    || ($event['event'] ?? null) !== 'payment.updated'
    || ! is_array($data)
    || ! is_string($data['payment_id'] ?? null)
    || ! is_string($data['status'] ?? null)) {
    http_response_code(400);
    exit;
}

// Implement this in your application: persist idempotently by payment_id and status,
// then queue fulfillment only for "paid" using payment_id as the idempotency key.
processPaymentUpdateIdempotently($data);

http_response_code(200);
echo 'ok';
@endverbatim</code></pre>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Define <code class="{{ $fieldKey }}">processPaymentUpdateIdempotently()</code> in your application before enabling this endpoint. Return a 2xx response only after recording the update; webhooks may be delivered more than once.</p>

                <div class="{{ $calloutInfo }}">
                    <span class="font-semibold">Tip:</span>
                    After verifying the signature, re-check the payment with <code class="font-mono">GET /api/payments/{payment_id}/status</code> before releasing goods — this guards against replayed or spoofed payloads.
                </div>
            </section>

            <section id="security" class="{{ $cardWrap }}">
                <h2 class="{{ $sectionHeading }}">Security Notes</h2>
                <ul class="mt-3 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <li>
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">Never expose your API key in the frontend.</span>
                        Do not embed it in JavaScript, mobile apps, or public repositories. All merchant API calls must originate from your backend.
                    </li>
                    <li>
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">Always verify payments from your backend.</span>
                        Before shipping, granting credits, or unlocking a service, confirm the status with <code class="{{ $fieldKey }}">GET /api/payments/{payment_id}/status</code>.
                    </li>
                    <li>
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">Do not trust client-side status.</span>
                        A success screen in the browser is not proof of payment. The customer may have closed the tab, faked the redirect, or manipulated local state.
                    </li>
                    <li>
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">Verify webhook signatures on every request.</span>
                        Reject any payload whose HMAC of <code class="font-mono">timestamp + "." + body</code> does not match. Generate a new signing secret on the API Credentials page if you suspect the old one leaked.
                    </li>
                    <li>
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">Use HTTPS everywhere.</span>
                        Your webhook endpoint and your checkout pages must be served over TLS.
                    </li>
                    <li>
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">Keep handlers idempotent.</span>
                        The same webhook may be delivered more than once. Apply the update only if the payment is not already in that state.
                    </li>
                </ul>
            </section>

            <section class="{{ $cardWrap }}">
                <h2 class="{{ $sectionHeading }}">Errors</h2>
                <p class="{{ $bodyText }}">All API errors use a consistent response envelope.</p>

                <h3 class="{{ $subHeading }}">Error Response Structure</h3>
                <pre class="{{ $codeCard }}">{
<span style="color:#67e8f9">"success"</span>: <span style="color:#fda4af">false</span>,
<span style="color:#67e8f9">"data"</span>: [],
<span style="color:#67e8f9">"error"</span>: <span style="color:#fcd34d">"Human-readable error message"</span>
}</pre>

                <h3 class="{{ $subHeading }}">Common Status Codes</h3>
                <ul class="mt-2 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <li><span class="font-semibold">401</span> — Unauthenticated or invalid API key.</li>
                    <li><span class="font-semibold">403</span> — Merchant account is inactive or gateway is not allowed.</li>
                    <li><span class="font-semibold">404</span> — Resource not found.</li>
                    <li><span class="font-semibold">409</span> — Idempotency key conflicts with a different payout request or payout submission is still being reconciled.</li>
                    <li><span class="font-semibold">422</span> — Validation error in request payload.</li>
                    <li><span class="font-semibold">429</span> — Rate limit exceeded.</li>
                </ul>
            </section>
        </div>

        <footer class="mt-10 border-t border-zinc-200 pt-6 text-center text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-500">
            <p>&copy; {{ date('Y') }} KemoPay. Public developer documentation.</p>
            <p class="mt-1">
                <a href="{{ route('home') }}" class="hover:text-zinc-700 dark:hover:text-zinc-300">KemoPay</a>
                <span class="mx-1.5 text-zinc-400">&middot;</span>
                <a href="{{ route('home') }}#privacy" class="hover:text-zinc-700 dark:hover:text-zinc-300">Privacy</a>
                <span class="mx-1.5 text-zinc-400">&middot;</span>
                <a href="{{ route('home') }}#terms" class="hover:text-zinc-700 dark:hover:text-zinc-300">Terms</a>
            </p>
        </footer>
    </main>

    <script>
        document.addEventListener('click', function (event) {
            const button = event.target.closest('.copy-btn');
            if (! button) {
                return;
            }

            const targetSelector = button.getAttribute('data-copy-target');
            const target = targetSelector ? document.querySelector(targetSelector) : null;
            if (! target) {
                return;
            }

            const text = target.innerText.trim();
            const done = () => {
                const original = button.textContent;
                button.textContent = 'Copied';
                button.classList.add('copied');
                setTimeout(() => {
                    button.textContent = original;
                    button.classList.remove('copied');
                }, 1500);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done).catch(() => fallbackCopy(text, done));
            } else {
                fallbackCopy(text, done);
            }
        });

        function fallbackCopy(text, done) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try { document.execCommand('copy'); done(); } catch (_) {}
            document.body.removeChild(textarea);
        }
    </script>
</body>
</html>
