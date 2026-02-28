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
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Merchant Docs') }}</h1>
        <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('API request and response references for client-side checkout integration.') }}</p>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Basic Platform Docs') }}</h2>
        <ul class="mt-3 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
            <li>{{ __('GatewayHub is a centralized payment orchestration and monitoring platform.') }}</li>
            <li>{{ __('Coins dynamic QR is the processing rail in the current model.') }}</li>
            <li>{{ __('Your enabled gateway options are controlled per merchant account.') }}</li>
            <li>{{ __('Use your API key as Bearer token for merchant API calls.') }}</li>
            <li>{{ __('Webhook processing and payment status changes are managed by the platform backend.') }}</li>
        </ul>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Auth Header') }}</h2>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}">
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span>
        </pre>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Get Enabled Gateways') }}</h2>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Use this endpoint in your client website checkout page to render only allowed payment options.') }}</p>

        <h3 class="mt-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Sample Request') }}</h3>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}"><span style="color:#34d399">GET</span> <span style="color:#f4f4f5">/api/gateways/enabled HTTP/1.1</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span>
<span style="color:#7dd3fc">Accept:</span> <span style="color:#f4f4f5">application/json</span></pre>

        <h3 class="mt-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Sample Response') }}</h3>
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

    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Create Payment') }}</h2>

        <h3 class="mt-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Sample Request') }}</h3>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}"><span style="color:#34d399">POST</span> <span style="color:#f4f4f5">/api/payments HTTP/1.1</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span>
<span style="color:#7dd3fc">Accept:</span> <span style="color:#f4f4f5">application/json</span>
<span style="color:#7dd3fc">Content-Type:</span> <span style="color:#f4f4f5">application/json</span></pre>

        <h3 class="mt-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('JSON Payload') }}</h3>
        <pre class="{{ $jsonCardClasses }}" style="{{ $jsonCardStyle }}">{
<span style="color:#67e8f9">"amount"</span>: <span style="color:#c4b5fd">500.00</span>,
<span style="color:#67e8f9">"currency"</span>: <span style="color:#fcd34d">"PHP"</span>,
<span style="color:#67e8f9">"gateway"</span>: <span style="color:#fcd34d">"gcash"</span>,
<span style="color:#67e8f9">"reference"</span>: <span style="color:#fcd34d">"ORDER-20260228-0001"</span>
}</pre>

        <h3 class="mt-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Sample Response') }}</h3>
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
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Get Payment Status') }}</h2>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}"><span style="color:#34d399">GET</span> <span style="color:#f4f4f5">/api/payments/{payment_id}/status HTTP/1.1</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span>
<span style="color:#7dd3fc">Accept:</span> <span style="color:#f4f4f5">application/json</span></pre>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Error Info') }}</h2>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('All API errors use a consistent response envelope.') }}</p>

        <h3 class="mt-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Error Response Structure') }}</h3>
        <pre class="{{ $jsonCardClasses }}" style="{{ $jsonCardStyle }}">{
<span style="color:#67e8f9">"success"</span>: <span style="color:#fda4af">false</span>,
<span style="color:#67e8f9">"data"</span>: [],
<span style="color:#67e8f9">"error"</span>: <span style="color:#fcd34d">"Human-readable error message"</span>
}</pre>

        <h3 class="mt-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Common Status Codes') }}</h3>
        <ul class="mt-2 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
            <li><span class="font-semibold">401</span> - {{ __('Unauthenticated or invalid API key.') }}</li>
            <li><span class="font-semibold">403</span> - {{ __('Merchant account is inactive or gateway is not allowed.') }}</li>
            <li><span class="font-semibold">404</span> - {{ __('Resource not found.') }}</li>
            <li><span class="font-semibold">422</span> - {{ __('Validation error in request payload.') }}</li>
            <li><span class="font-semibold">429</span> - {{ __('Rate limit exceeded.') }}</li>
        </ul>
    </div>
</div>
