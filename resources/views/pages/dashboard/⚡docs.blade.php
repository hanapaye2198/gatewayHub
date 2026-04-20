<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component {
    #[Layout('layouts.app', ['title' => 'Docs'])]
};
?>

@php
    $jsonCardClasses = 'mt-2 overflow-x-auto rounded-2xl p-4 text-xs text-zinc-100';
    $jsonCardStyle = 'background:#111827;border:1px solid #374151;box-shadow:0 14px 24px -18px rgba(0,0,0,.85), inset 0 1px 0 rgba(255,255,255,.04);';
    $endpointCardClasses = 'mt-2 overflow-x-auto rounded-2xl p-4 text-xs';
    $endpointCardStyle = 'background:#111827;border:1px solid #374151;box-shadow:0 14px 24px -18px rgba(0,0,0,.85), inset 0 1px 0 rgba(255,255,255,.04);color:#e5e7eb;';
    $sectionHeading = 'text-lg font-semibold text-zinc-900 dark:text-zinc-100';
    $subHeading = 'mt-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400';
    $bodyText = 'mt-1 text-sm text-zinc-600 dark:text-zinc-400';
    $cardWrap = 'rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800';
    $calloutInfo = 'mt-3 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-200';
    $calloutWarn = 'mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-200';
    $calloutDanger = 'mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-200';
    $fieldKey = 'font-mono text-[0.8125rem] text-zinc-900 dark:text-zinc-100';
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="{{ $cardWrap }}">
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Merchant Docs') }}</h1>
        <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Everything you need to accept payments: authentication, creating payments, polling status, and handling webhooks.') }}</p>
    </div>

    <div class="{{ $cardWrap }}">
        <h2 class="{{ $sectionHeading }}">{{ __('Basic Platform Docs') }}</h2>
        <ul class="mt-3 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
            <li>{{ __('GatewayHub is a centralized payment orchestration and monitoring platform.') }}</li>
            <li>{{ __('Coins dynamic QR is the processing rail in the current model.') }}</li>
            <li>{{ __('Your enabled gateway options are controlled per merchant account.') }}</li>
            <li>{{ __('Use your API key as a Bearer token on every merchant API call.') }}</li>
            <li>{{ __('Webhook processing and final payment status are owned by the platform backend — trust the webhook, not the browser.') }}</li>
        </ul>
    </div>

    <div class="{{ $cardWrap }}">
        <h2 class="{{ $sectionHeading }}">{{ __('Payment Flow') }}</h2>
        <p class="{{ $bodyText }}">{{ __('A payment always moves through these four steps. Implement them in this exact order.') }}</p>

        <ol class="mt-4 space-y-4 text-sm text-zinc-700 dark:text-zinc-300">
            <li>
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-6 w-6 flex-none items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white dark:bg-zinc-100 dark:text-zinc-900">1</span>
                    <div>
                        <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Create Payment') }}</p>
                        <p class="mt-1">{{ __('Your backend calls') }} <code class="{{ $fieldKey }}">POST /api/payments</code> {{ __('with the amount, currency, gateway, and your internal reference. Never call this from the browser.') }}</p>
                    </div>
                </div>
            </li>
            <li>
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-6 w-6 flex-none items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white dark:bg-zinc-100 dark:text-zinc-900">2</span>
                    <div>
                        <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Display QR or Redirect') }}</p>
                        <p class="mt-1">{{ __('Render') }} <code class="{{ $fieldKey }}">qr_data</code> {{ __('as a QR image for the customer to scan, or send them to') }} <code class="{{ $fieldKey }}">redirect_url</code> {{ __('if the gateway returned one. Show a countdown using') }} <code class="{{ $fieldKey }}">expires_at</code>.</p>
                    </div>
                </div>
            </li>
            <li>
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-6 w-6 flex-none items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white dark:bg-zinc-100 dark:text-zinc-900">3</span>
                    <div>
                        <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Wait for Webhook') }}</p>
                        <p class="mt-1">{{ __('GatewayHub notifies your server asynchronously when the payment is paid, failed, or expired. This is the source of truth.') }}</p>
                        <div class="{{ $calloutWarn }}">{{ __('Do NOT trust any status that the frontend observed. The browser can be closed, reloaded, or tampered with. Only the webhook (or a backend status check) is authoritative.') }}</div>
                    </div>
                </div>
            </li>
            <li>
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-6 w-6 flex-none items-center justify-center rounded-full bg-zinc-900 text-xs font-semibold text-white dark:bg-zinc-100 dark:text-zinc-900">4</span>
                    <div>
                        <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Verify via API') }}</p>
                        <p class="mt-1">{{ __('Before fulfilling the order, confirm the payment with') }} <code class="{{ $fieldKey }}">GET /api/payments/{payment_id}/status</code> {{ __('from your backend. Use this both as a fallback when webhooks are delayed and as a final guard at checkout completion.') }}</p>
                    </div>
                </div>
            </li>
        </ol>
    </div>

    <div class="{{ $cardWrap }}">
        <h2 class="{{ $sectionHeading }}">{{ __('Auth Header') }}</h2>
        <p class="{{ $bodyText }}">{{ __('All merchant API calls require your API key as a Bearer token. Send it from your server only.') }}</p>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}">
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span>
        </pre>
    </div>

    <div class="{{ $cardWrap }}">
        <h2 class="{{ $sectionHeading }}">{{ __('Get Enabled Gateways') }}</h2>
        <p class="{{ $bodyText }}">{{ __('Returns the payment options currently enabled for your merchant account. Use it to render only the methods the customer can actually pay with.') }}</p>

        <h3 class="{{ $subHeading }}">{{ __('Sample Request') }}</h3>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}"><span style="color:#34d399">GET</span> <span style="color:#f4f4f5">/api/gateways/enabled HTTP/1.1</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span>
<span style="color:#7dd3fc">Accept:</span> <span style="color:#f4f4f5">application/json</span></pre>

        <h3 class="{{ $subHeading }}">{{ __('Sample Response') }}</h3>
        <pre class="{{ $jsonCardClasses }}" style="{{ $jsonCardStyle }}">{
<span style="color:#67e8f9">"success"</span>: <span style="color:#6ee7b7">true</span>,
<span style="color:#67e8f9">"data"</span>: {
  <span style="color:#67e8f9">"gateways"</span>: [
    {
      <span style="color:#67e8f9">"code"</span>: <span style="color:#fcd34d">"gcash"</span>,
      <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"Gcash"</span>
    },
    {
      <span style="color:#67e8f9">"code"</span>: <span style="color:#fcd34d">"maya"</span>,
      <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"Maya"</span>
    },
    {
      <span style="color:#67e8f9">"code"</span>: <span style="color:#fcd34d">"qrph"</span>,
      <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"QRPH"</span>
    }
  ],
  <span style="color:#67e8f9">"count"</span>: <span style="color:#c4b5fd">3</span>
},
<span style="color:#67e8f9">"error"</span>: <span style="color:#fda4af">null</span>
}</pre>
    </div>

    <div class="{{ $cardWrap }}">
        <h2 class="{{ $sectionHeading }}">{{ __('Create Payment') }}</h2>
        <p class="{{ $bodyText }}">{{ __('Creates a new payment and returns the data you need to collect funds (QR or redirect URL).') }}</p>

        <h3 class="{{ $subHeading }}">{{ __('Sample Request') }}</h3>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}"><span style="color:#34d399">POST</span> <span style="color:#f4f4f5">/api/payments HTTP/1.1</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span>
<span style="color:#7dd3fc">Accept:</span> <span style="color:#f4f4f5">application/json</span>
<span style="color:#7dd3fc">Content-Type:</span> <span style="color:#f4f4f5">application/json</span></pre>

        <h3 class="{{ $subHeading }}">{{ __('JSON Payload') }}</h3>
        <pre class="{{ $jsonCardClasses }}" style="{{ $jsonCardStyle }}">{
<span style="color:#67e8f9">"amount"</span>: <span style="color:#c4b5fd">500.00</span>,
<span style="color:#67e8f9">"currency"</span>: <span style="color:#fcd34d">"PHP"</span>,
<span style="color:#67e8f9">"gateway"</span>: <span style="color:#fcd34d">"gcash"</span>,
<span style="color:#67e8f9">"reference"</span>: <span style="color:#fcd34d">"ORDER-20260228-0001"</span>
}</pre>

        <h3 class="{{ $subHeading }}">{{ __('cURL Example') }}</h3>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}"><span style="color:#f4f4f5">curl -X POST https://gatewayhub.io/api/payments \
  -H </span><span style="color:#fcd34d">"Authorization: Bearer YOUR_API_KEY"</span><span style="color:#f4f4f5"> \
  -H </span><span style="color:#fcd34d">"Content-Type: application/json"</span><span style="color:#f4f4f5"> \
  -H </span><span style="color:#fcd34d">"Accept: application/json"</span><span style="color:#f4f4f5"> \
  -d </span><span style="color:#fcd34d">'{"amount":500.00,"currency":"PHP","gateway":"gcash","reference":"ORDER-20260228-0001"}'</span></pre>

        <h3 class="{{ $subHeading }}">{{ __('Sample Response') }}</h3>
        <pre class="{{ $jsonCardClasses }}" style="{{ $jsonCardStyle }}">{
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

        <h3 class="{{ $subHeading }}">{{ __('Response Field Reference') }}</h3>
        <dl class="mt-2 space-y-3 text-sm text-zinc-700 dark:text-zinc-300">
            <div>
                <dt class="{{ $fieldKey }}">payment_id</dt>
                <dd class="mt-0.5">{{ __('Unique identifier for this payment. Store it against your order. Use it for every status check and to match incoming webhooks.') }}</dd>
            </div>
            <div>
                <dt class="{{ $fieldKey }}">qr_data</dt>
                <dd class="mt-0.5">{{ __('EMVCo-compatible QR payload string. Encode it as a QR code (e.g. via a QR library) and display the image to the customer. Do not modify the string.') }}</dd>
            </div>
            <div>
                <dt class="{{ $fieldKey }}">expires_at</dt>
                <dd class="mt-0.5">{{ __('ISO-8601 timestamp of when the QR or redirect session expires. After this moment the payment will be marked as expired and cannot be completed.') }}</dd>
            </div>
            <div>
                <dt class="{{ $fieldKey }}">redirect_url</dt>
                <dd class="mt-0.5">{{ __('If the gateway returns a hosted checkout page (e.g. wallet app deep link), send the customer here. When null, use qr_data instead.') }}</dd>
            </div>
            <div>
                <dt class="{{ $fieldKey }}">status</dt>
                <dd class="mt-0.5">{{ __('Current payment state. On creation this is always "pending". Final states arrive via webhook.') }}</dd>
            </div>
        </dl>
    </div>

    <div class="{{ $cardWrap }}">
        <h2 class="{{ $sectionHeading }}">{{ __('Get Payment Status') }}</h2>
        <p class="{{ $bodyText }}">{{ __('Fetch the current status of a payment from your backend. Call this before fulfilling an order, or as a fallback when a webhook is delayed.') }}</p>

        <h3 class="{{ $subHeading }}">{{ __('Sample Request') }}</h3>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}"><span style="color:#34d399">GET</span> <span style="color:#f4f4f5">/api/payments/{payment_id}/status HTTP/1.1</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span>
<span style="color:#7dd3fc">Accept:</span> <span style="color:#f4f4f5">application/json</span></pre>

        <h3 class="{{ $subHeading }}">{{ __('cURL Example') }}</h3>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}"><span style="color:#f4f4f5">curl -X GET https://gatewayhub.io/api/payments/</span><span style="color:#fcd34d">{payment_id}</span><span style="color:#f4f4f5">/status \
  -H </span><span style="color:#fcd34d">"Authorization: Bearer YOUR_API_KEY"</span><span style="color:#f4f4f5"> \
  -H </span><span style="color:#fcd34d">"Accept: application/json"</span></pre>
    </div>

    <div class="{{ $cardWrap }}">
        <h2 class="{{ $sectionHeading }}">{{ __('Webhook Handling') }}</h2>
        <p class="{{ $bodyText }}">{{ __('A webhook is a server-to-server notification. When the status of a payment changes, GatewayHub sends an HTTP POST to your endpoint so you do not have to poll.') }}</p>

        <div class="{{ $calloutDanger }}">
            <span class="font-semibold">{{ __('Rule of thumb:') }}</span>
            {{ __('Always trust the webhook over any frontend polling. The browser can lie; the webhook cannot.') }}
        </div>

        <h3 class="{{ $subHeading }}">{{ __('Endpoint') }}</h3>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}"><span style="color:#34d399">POST</span> <span style="color:#f4f4f5">/webhooks/coins HTTP/1.1</span>
<span style="color:#7dd3fc">Content-Type:</span> <span style="color:#f4f4f5">application/json</span>
<span style="color:#7dd3fc">X-COINS-SIGNATURE:</span> <span style="color:#fcd34d">&lt;hmac-sha256-signature&gt;</span></pre>

        <h3 class="{{ $subHeading }}">{{ __('How It Works') }}</h3>
        <ul class="mt-2 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
            <li>{{ __('GatewayHub sends updates asynchronously — usually within seconds of the payment event, but network delays can happen.') }}</li>
            <li>{{ __('Your endpoint must respond with HTTP 200 quickly (under ~5 seconds). Do heavy work in a queue.') }}</li>
            <li>{{ __('If your endpoint errors or times out, GatewayHub retries with backoff.') }}</li>
            <li>{{ __('Duplicate deliveries are possible. Make your handler idempotent by keying on payment_id (or the provider reference) and ignoring already-applied updates.') }}</li>
        </ul>

        <h3 class="{{ $subHeading }}">{{ __('Example Payload') }}</h3>
        <pre class="{{ $jsonCardClasses }}" style="{{ $jsonCardStyle }}">{
<span style="color:#67e8f9">"event"</span>: <span style="color:#fcd34d">"payment.updated"</span>,
<span style="color:#67e8f9">"requestId"</span>: <span style="color:#fcd34d">"ORDER-20260228-0001"</span>,
<span style="color:#67e8f9">"status"</span>: <span style="color:#fcd34d">"paid"</span>,
<span style="color:#67e8f9">"data"</span>: {
  <span style="color:#67e8f9">"payment_id"</span>: <span style="color:#fcd34d">"uuid-value"</span>,
  <span style="color:#67e8f9">"gateway"</span>: <span style="color:#fcd34d">"coins"</span>,
  <span style="color:#67e8f9">"amount"</span>: <span style="color:#c4b5fd">500</span>,
  <span style="color:#67e8f9">"currency"</span>: <span style="color:#fcd34d">"PHP"</span>,
  <span style="color:#67e8f9">"status"</span>: <span style="color:#fcd34d">"paid"</span>,
  <span style="color:#67e8f9">"paid_at"</span>: <span style="color:#fcd34d">"2026-02-28T12:01:42+08:00"</span>,
  <span style="color:#67e8f9">"reference"</span>: <span style="color:#fcd34d">"ORDER-20260228-0001"</span>
}
}</pre>

        <h3 class="{{ $subHeading }}">{{ __('Signature Verification') }}</h3>
        <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ __('Each delivery is signed with your webhook secret so you can be sure it came from GatewayHub.') }}</p>
        <ul class="mt-2 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
            <li>{{ __('Read the signature from the') }} <code class="{{ $fieldKey }}">X-COINS-SIGNATURE</code> {{ __('header.') }}</li>
            <li>{{ __('Compute an HMAC-SHA256 of the raw request body using your webhook secret.') }}</li>
            <li>{{ __('Compare the two values using a constant-time comparison (e.g.') }} <code class="{{ $fieldKey }}">hash_equals</code> {{ __('in PHP).') }}</li>
            <li>{{ __('If they do not match, return 401 and do not act on the payload.') }}</li>
        </ul>

        <div class="{{ $calloutInfo }}">
            <span class="font-semibold">{{ __('Tip:') }}</span>
            {{ __('After signature verification, re-check the payment with') }} <code class="font-mono">GET /api/payments/{payment_id}/status</code> {{ __('before releasing goods — this guards against replayed or spoofed payloads.') }}
        </div>
    </div>

    <div class="{{ $cardWrap }}">
        <h2 class="{{ $sectionHeading }}">{{ __('Security Notes') }}</h2>
        <ul class="mt-3 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
            <li>
                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Never expose your API key in the frontend.') }}</span>
                {{ __('Do not embed it in JavaScript, mobile apps, or public repositories. All merchant API calls must originate from your backend.') }}
            </li>
            <li>
                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Always verify payments from your backend.') }}</span>
                {{ __('Before shipping, granting credits, or unlocking a service, confirm the status with') }}
                <code class="{{ $fieldKey }}">GET /api/payments/{payment_id}/status</code>.
            </li>
            <li>
                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Do not trust client-side status.') }}</span>
                {{ __('A success screen in the browser is not proof of payment. The customer may have closed the tab, faked the redirect, or manipulated local state.') }}
            </li>
            <li>
                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Verify webhook signatures on every request.') }}</span>
                {{ __('Reject any payload whose HMAC does not match. Rotate the webhook secret if you suspect it leaked.') }}
            </li>
            <li>
                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Use HTTPS everywhere.') }}</span>
                {{ __('Your webhook endpoint and your checkout pages must be served over TLS.') }}
            </li>
            <li>
                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Keep handlers idempotent.') }}</span>
                {{ __('The same webhook may be delivered more than once. Apply the update only if the payment is not already in that state.') }}
            </li>
        </ul>
    </div>

    <div class="{{ $cardWrap }}">
        <h2 class="{{ $sectionHeading }}">{{ __('Error Info') }}</h2>
        <p class="{{ $bodyText }}">{{ __('All API errors use a consistent response envelope.') }}</p>

        <h3 class="{{ $subHeading }}">{{ __('Error Response Structure') }}</h3>
        <pre class="{{ $jsonCardClasses }}" style="{{ $jsonCardStyle }}">{
<span style="color:#67e8f9">"success"</span>: <span style="color:#fda4af">false</span>,
<span style="color:#67e8f9">"data"</span>: [],
<span style="color:#67e8f9">"error"</span>: <span style="color:#fcd34d">"Human-readable error message"</span>
}</pre>

        <h3 class="{{ $subHeading }}">{{ __('Common Status Codes') }}</h3>
        <ul class="mt-2 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
            <li><span class="font-semibold">401</span> - {{ __('Unauthenticated or invalid API key.') }}</li>
            <li><span class="font-semibold">403</span> - {{ __('Merchant account is inactive or gateway is not allowed.') }}</li>
            <li><span class="font-semibold">404</span> - {{ __('Resource not found.') }}</li>
            <li><span class="font-semibold">422</span> - {{ __('Validation error in request payload.') }}</li>
            <li><span class="font-semibold">429</span> - {{ __('Rate limit exceeded.') }}</li>
        </ul>
    </div>
</div>
