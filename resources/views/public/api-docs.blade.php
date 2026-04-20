<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <title>GatewayHub API Documentation</title>
    <meta name="description" content="Integrate payments using GatewayHub APIs. Public developer documentation for endpoints, webhooks, and security best practices.">

    <meta property="og:title" content="GatewayHub API Documentation">
    <meta property="og:description" content="Integrate payments using GatewayHub APIs.">
    <meta property="og:url" content="https://gatewayhub.io/api-docs">
    <meta property="og:type" content="website">
    <link rel="canonical" href="https://gatewayhub.io/api-docs">

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}?v=gh2">
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=gh2" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=gh2">

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
        $sectionHeading = 'text-lg font-semibold text-zinc-900 dark:text-zinc-100';
        $subHeading = 'mt-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400';
        $bodyText = 'mt-1 text-sm text-zinc-600 dark:text-zinc-400';
        $cardWrap = 'rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900';
        $codeCard = 'code-card mt-2 overflow-x-auto rounded-2xl p-4 text-xs';
        $calloutInfo = 'mt-3 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-200';
        $calloutWarn = 'mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-200';
        $calloutDanger = 'mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-200';
        $fieldKey = 'font-mono text-[0.8125rem] text-zinc-900 dark:text-zinc-100';
    @endphp

    <header class="sticky top-0 z-30 border-b border-zinc-200 bg-white/80 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/80">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3 sm:px-6">
            <a href="https://gatewayhub.io" class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-sm">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
                </span>
                <span class="text-base font-semibold tracking-tight">GatewayHub</span>
            </a>
            <nav class="hidden items-center gap-5 text-sm text-zinc-600 dark:text-zinc-400 sm:flex">
                <a href="#flow" class="hover:text-zinc-900 dark:hover:text-zinc-100">Flow</a>
                <a href="#endpoints" class="hover:text-zinc-900 dark:hover:text-zinc-100">Endpoints</a>
                <a href="#webhooks" class="hover:text-zinc-900 dark:hover:text-zinc-100">Webhooks</a>
                <a href="#security" class="hover:text-zinc-900 dark:hover:text-zinc-100">Security</a>
                <a href="https://gatewayhub.io" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">gatewayhub.io &rarr;</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:py-14">

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-gradient-to-br from-white via-blue-50/40 to-indigo-50/40 p-8 shadow-sm dark:border-zinc-800 dark:from-zinc-900 dark:via-zinc-900 dark:to-indigo-950/20 sm:p-10">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-100/60 px-2.5 py-1 text-[0.7rem] font-semibold uppercase tracking-wide text-blue-700 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-300">
                <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                Developer Docs
            </span>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-4xl">GatewayHub API Documentation</h1>
            <p class="mt-3 max-w-2xl text-base text-zinc-600 dark:text-zinc-400">Integrate payments using GatewayHub APIs. This is the public reference for endpoints, payment flow, webhooks, and security best practices.</p>

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
                    <li>GatewayHub is a payment orchestration platform that lets you accept payments through multiple rails with a single integration.</li>
                    <li>Dynamic QR is the primary processing rail today. Additional rails may be enabled per merchant.</li>
                    <li>Authenticate merchant API calls with a Bearer token issued from your merchant dashboard. Keep this token server-side only.</li>
                    <li>Final payment status is delivered by webhook. Treat the webhook as the source of truth.</li>
                </ul>
            </section>

            <section class="{{ $cardWrap }}">
                <h2 class="{{ $sectionHeading }}">Base URL</h2>
                <p class="{{ $bodyText }}">All production API calls are made against the GatewayHub production host. Prepend this base URL to every endpoint shown below.</p>
                <pre class="{{ $codeCard }}"><span style="color:#f4f4f5">https://gatewayhub.io</span></pre>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-500">Marketing site: <a href="https://gatewayhub.io" class="text-blue-600 hover:underline dark:text-blue-400">gatewayhub.io</a></p>
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
                                <p class="mt-1">GatewayHub notifies your server asynchronously when the payment is paid, failed, or expired. This is the source of truth.</p>
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
                <p class="{{ $bodyText }}">Returns the payment options currently enabled for your merchant account. Use it to render only the methods the customer can actually pay with.</p>

                <h3 class="{{ $subHeading }}">Sample Request</h3>
                <pre class="{{ $codeCard }}"><span style="color:#34d399">GET</span> <span style="color:#f4f4f5">/api/gateways/enabled HTTP/1.1</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span>
<span style="color:#7dd3fc">Accept:</span> <span style="color:#f4f4f5">application/json</span></pre>

                <h3 class="{{ $subHeading }}">Sample Response</h3>
                <pre class="{{ $codeCard }}">{
<span style="color:#67e8f9">"success"</span>: <span style="color:#6ee7b7">true</span>,
<span style="color:#67e8f9">"data"</span>: {
  <span style="color:#67e8f9">"gateways"</span>: [
    { <span style="color:#67e8f9">"code"</span>: <span style="color:#fcd34d">"gcash"</span>, <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"Gcash"</span> },
    { <span style="color:#67e8f9">"code"</span>: <span style="color:#fcd34d">"maya"</span>,  <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"Maya"</span>  },
    { <span style="color:#67e8f9">"code"</span>: <span style="color:#fcd34d">"qrph"</span>,  <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"QRPH"</span>  }
  ],
  <span style="color:#67e8f9">"count"</span>: <span style="color:#c4b5fd">3</span>
},
<span style="color:#67e8f9">"error"</span>: <span style="color:#fda4af">null</span>
}</pre>
            </section>

            <section class="{{ $cardWrap }}">
                <h2 class="{{ $sectionHeading }}">Create Payment</h2>
                <p class="{{ $bodyText }}">Creates a new payment and returns the data you need to collect funds (QR or redirect URL).</p>

                <h3 class="{{ $subHeading }}">Sample Request</h3>
                <pre class="{{ $codeCard }}"><span style="color:#34d399">POST</span> <span style="color:#f4f4f5">/api/payments HTTP/1.1</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span>
<span style="color:#7dd3fc">Accept:</span> <span style="color:#f4f4f5">application/json</span>
<span style="color:#7dd3fc">Content-Type:</span> <span style="color:#f4f4f5">application/json</span></pre>

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
<pre id="curl-create" class="{{ $codeCard }}"><span style="color:#f4f4f5">curl -X POST https://gatewayhub.io/api/payments \
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
  <span style="color:#67e8f9">"gateway"</span>: <span style="color:#fcd34d">"gcash"</span>,
  <span style="color:#67e8f9">"amount"</span>: <span style="color:#c4b5fd">500</span>,
  <span style="color:#67e8f9">"currency"</span>: <span style="color:#fcd34d">"PHP"</span>,
  <span style="color:#67e8f9">"status"</span>: <span style="color:#fcd34d">"pending"</span>,
  <span style="color:#67e8f9">"qr_data"</span>: <span style="color:#fcd34d">"000201..."</span>,
  <span style="color:#67e8f9">"expires_at"</span>: <span style="color:#fcd34d">"2026-02-28T12:00:00+08:00"</span>,
  <span style="color:#67e8f9">"redirect_url"</span>: <span style="color:#fda4af">null</span>
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
                        <dt class="{{ $fieldKey }}">qr_data</dt>
                        <dd class="mt-0.5">EMVCo-compatible QR payload string. Encode it as a QR code image and display it to the customer. Do not modify the string.</dd>
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
                        <dt class="{{ $fieldKey }}">status</dt>
                        <dd class="mt-0.5">Current payment state. On creation this is always <code class="{{ $fieldKey }}">pending</code>. Final states arrive via webhook.</dd>
                    </div>
                </dl>
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
<pre id="curl-status" class="{{ $codeCard }}"><span style="color:#f4f4f5">curl -X GET https://gatewayhub.io/api/payments/</span><span style="color:#fcd34d">{payment_id}</span><span style="color:#f4f4f5">/status \
  -H </span><span style="color:#fcd34d">"Authorization: Bearer YOUR_API_KEY"</span><span style="color:#f4f4f5"> \
  -H </span><span style="color:#fcd34d">"Accept: application/json"</span></pre>
                </div>
            </section>

            <section id="webhooks" class="{{ $cardWrap }}">
                <h2 class="{{ $sectionHeading }}">Webhook Handling</h2>
                <p class="{{ $bodyText }}">A webhook is a server-to-server notification. When a payment status changes, GatewayHub sends an HTTP POST to your endpoint so you do not need to poll.</p>

                <div class="{{ $calloutDanger }}">
                    <span class="font-semibold">Rule of thumb:</span>
                    Always trust the webhook over any frontend polling. The browser can lie; the webhook cannot.
                </div>

                <h3 class="{{ $subHeading }}">Delivery</h3>
                <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">GatewayHub delivers webhooks to the URL you register in your merchant dashboard. The request looks like this:</p>
                <pre class="{{ $codeCard }}"><span style="color:#34d399">POST</span> <span style="color:#f4f4f5">https://your-server.example.com/your-webhook-endpoint HTTP/1.1</span>
<span style="color:#7dd3fc">Content-Type:</span> <span style="color:#f4f4f5">application/json</span>
<span style="color:#7dd3fc">User-Agent:</span> <span style="color:#f4f4f5">GatewayHub-Webhooks/1.0</span>
<span style="color:#7dd3fc">X-Signature:</span> <span style="color:#fcd34d">&lt;hmac-sha256-signature&gt;</span></pre>

                <h3 class="{{ $subHeading }}">How It Works</h3>
                <ul class="mt-2 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <li>GatewayHub sends updates asynchronously — usually within seconds of the event, but network delays can happen.</li>
                    <li>Your endpoint must respond with HTTP 200 quickly (under ~5 seconds). Do heavy work in a queue.</li>
                    <li>If your endpoint errors or times out, GatewayHub retries with backoff.</li>
                    <li>Duplicate deliveries are possible. Make your handler idempotent by keying on <code class="{{ $fieldKey }}">payment_id</code> and ignoring already-applied updates.</li>
                </ul>

                <h3 class="{{ $subHeading }}">Example Payload</h3>
                <pre class="{{ $codeCard }}">{
<span style="color:#67e8f9">"event"</span>: <span style="color:#fcd34d">"payment.updated"</span>,
<span style="color:#67e8f9">"status"</span>: <span style="color:#fcd34d">"paid"</span>,
<span style="color:#67e8f9">"data"</span>: {
  <span style="color:#67e8f9">"payment_id"</span>: <span style="color:#fcd34d">"uuid-value"</span>,
  <span style="color:#67e8f9">"amount"</span>: <span style="color:#c4b5fd">500</span>,
  <span style="color:#67e8f9">"currency"</span>: <span style="color:#fcd34d">"PHP"</span>,
  <span style="color:#67e8f9">"status"</span>: <span style="color:#fcd34d">"paid"</span>,
  <span style="color:#67e8f9">"paid_at"</span>: <span style="color:#fcd34d">"2026-02-28T12:01:42+08:00"</span>,
  <span style="color:#67e8f9">"reference"</span>: <span style="color:#fcd34d">"ORDER-20260228-0001"</span>
}
}</pre>

                <h3 class="{{ $subHeading }}">Signature Verification</h3>
                <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">Each delivery is signed with your webhook secret so you can be sure it came from GatewayHub.</p>
                <ul class="mt-2 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <li>Read the signature from the signature header sent with the request.</li>
                    <li>Compute an HMAC-SHA256 of the raw request body using your webhook secret.</li>
                    <li>Compare the two values using a constant-time comparison (e.g. <code class="{{ $fieldKey }}">hash_equals</code> in PHP).</li>
                    <li>If they do not match, return 401 and do not act on the payload.</li>
                </ul>

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
                        Reject any payload whose HMAC does not match. Rotate the webhook secret if you suspect it leaked.
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
                    <li><span class="font-semibold">422</span> — Validation error in request payload.</li>
                    <li><span class="font-semibold">429</span> — Rate limit exceeded.</li>
                </ul>
            </section>
        </div>

        <footer class="mt-10 border-t border-zinc-200 pt-6 text-center text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-500">
            <p>&copy; {{ date('Y') }} GatewayHub. Public developer documentation.</p>
            <p class="mt-1">
                <a href="https://gatewayhub.io" class="hover:text-zinc-700 dark:hover:text-zinc-300">gatewayhub.io</a>
                <span class="mx-1.5 text-zinc-400">&middot;</span>
                <a href="https://gatewayhub.io#privacy" class="hover:text-zinc-700 dark:hover:text-zinc-300">Privacy</a>
                <span class="mx-1.5 text-zinc-400">&middot;</span>
                <a href="https://gatewayhub.io#terms" class="hover:text-zinc-700 dark:hover:text-zinc-300">Terms</a>
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
