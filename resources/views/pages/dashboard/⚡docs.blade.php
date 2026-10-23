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
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('KemoPay Merchant API Docs') }}</h1>
        <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Everything you need to accept payments: authentication, creating payments, polling status, and handling webhooks.') }}</p>
    </div>

    <div class="{{ $cardWrap }}">
        <h2 class="{{ $sectionHeading }}">{{ __('Basic Platform Docs') }}</h2>
        <ul class="mt-3 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
            <li>{{ __('KemoPay is a centralized payment orchestration and monitoring platform.') }}</li>
            <li>{{ __('KemoPay provides QR Ph, GCash, Maya, GrabPay, and ShopeePay through PayMongo. Use only the gateway codes returned by the enabled gateways endpoint; the provider-only "paymongo" code is not a payment method.') }}</li>
            <li>{{ __('Your enabled gateway options are controlled per merchant account.') }}</li>
            <li>{{ __('Use your API key as a Bearer token on every merchant API call.') }}</li>
            <li>{{ __('Webhook processing and final payment status are owned by the platform backend — trust the webhook, not the browser.') }}</li>
            <li>{{ __('Merchant webhooks can be configured under API Credentials.') }}</li>
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
                        <p class="mt-1">{{ __('After you configure a webhook URL under API Credentials, KemoPay notifies your server whenever the payment status changes (for example pending → paid). That callback is the source of truth — not the browser.') }}</p>
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
        <p class="{{ $bodyText }}">{{ __('Returns methods enabled for your merchant account and active on KemoPay’s PayMongo account. A merchant toggle alone does not make a provider method available. Render only the gateway codes returned by this endpoint.') }}</p>

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
      <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"GCash"</span>
    },
    {
      <span style="color:#67e8f9">"code"</span>: <span style="color:#fcd34d">"maya"</span>,
      <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"Maya"</span>
    },
    {
      <span style="color:#67e8f9">"code"</span>: <span style="color:#fcd34d">"grab_pay"</span>,
      <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"GrabPay"</span>
    },
    {
      <span style="color:#67e8f9">"code"</span>: <span style="color:#fcd34d">"shopeepay"</span>,
      <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"ShopeePay"</span>
    },
    {
      <span style="color:#67e8f9">"code"</span>: <span style="color:#fcd34d">"qrph"</span>,
      <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"QR Ph"</span>
    }
  ],
  <span style="color:#67e8f9">"count"</span>: <span style="color:#c4b5fd">5</span>,
  <span style="color:#67e8f9">"provider_availability"</span>: {
    <span style="color:#67e8f9">"paymongo"</span>: {
      <span style="color:#67e8f9">"status"</span>: <span style="color:#fcd34d">"verified"</span>,
      <span style="color:#67e8f9">"available_methods"</span>: [<span style="color:#fcd34d">"qrph"</span>, <span style="color:#fcd34d">"gcash"</span>, <span style="color:#fcd34d">"maya"</span>, <span style="color:#fcd34d">"grab_pay"</span>, <span style="color:#fcd34d">"shopeepay"</span>],
      <span style="color:#67e8f9">"methods"</span>: {
        <span style="color:#67e8f9">"qrph"</span>: <span style="color:#fcd34d">"available"</span>,
        <span style="color:#67e8f9">"gcash"</span>: <span style="color:#fcd34d">"available"</span>,
        <span style="color:#67e8f9">"maya"</span>: <span style="color:#fcd34d">"available"</span>,
        <span style="color:#67e8f9">"grab_pay"</span>: <span style="color:#fcd34d">"available"</span>,
        <span style="color:#67e8f9">"shopeepay"</span>: <span style="color:#fcd34d">"available"</span>
      }
    }
  }
},
<span style="color:#67e8f9">"error"</span>: <span style="color:#fda4af">null</span>
}</pre>
        <p class="{{ $bodyText }}">{{ __('provider_availability.paymongo.status is verified when KemoPay can query the shared PayMongo account. Its methods map reports available, unavailable, or unverified for each method. Unsupported methods are omitted from gateways. If a method is not active on PayMongo, payment creation returns HTTP 422 without creating a payment; if capability lookup fails, it returns HTTP 503.') }}</p>
    </div>

    <div class="{{ $cardWrap }}">
        <h2 class="{{ $sectionHeading }}">{{ __('Create Payment') }}</h2>
        <p class="{{ $bodyText }}">{{ __('Creates a payment and returns the QR or redirect details. The request amount is the product value; KemoPay adds its configured platform fee and convenience fee to the customer total so the merchant receives the full product value. PayMongo methods require PHP 1.00 minimum; GCash, Maya, GrabPay, and ShopeePay allow up to PHP 100,000, while QR Ph allows up to PHP 9,999,999.99.') }}</p>

        <h3 class="{{ $subHeading }}">{{ __('Sample Request') }}</h3>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}"><span style="color:#34d399">POST</span> <span style="color:#f4f4f5">/api/payments HTTP/1.1</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span>
<span style="color:#7dd3fc">Idempotency-Key:</span> <span style="color:#fcd34d">order-payment-1001</span>
<span style="color:#7dd3fc">Accept:</span> <span style="color:#f4f4f5">application/json</span>
<span style="color:#7dd3fc">Content-Type:</span> <span style="color:#f4f4f5">application/json</span></pre>
        <p class="{{ $bodyText }}">{{ __('An optional Idempotency-Key header makes retries of the same payment request return the original result. Do not reuse a key for a different payment.') }}</p>

        <h3 class="{{ $subHeading }}">{{ __('JSON Payload') }}</h3>
        <pre class="{{ $jsonCardClasses }}" style="{{ $jsonCardStyle }}">{
<span style="color:#67e8f9">"amount"</span>: <span style="color:#c4b5fd">500.00</span>,
<span style="color:#67e8f9">"currency"</span>: <span style="color:#fcd34d">"PHP"</span>,
<span style="color:#67e8f9">"gateway"</span>: <span style="color:#fcd34d">"gcash"</span>,
<span style="color:#67e8f9">"reference"</span>: <span style="color:#fcd34d">"ORDER-20260228-0001"</span>
}</pre>

        <h3 class="{{ $subHeading }}">{{ __('cURL Example') }}</h3>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}"><span style="color:#f4f4f5">curl -X POST {{ rtrim(config('app.url'), '/') }}/api/payments \
  -H </span><span style="color:#fcd34d">"Authorization: Bearer YOUR_API_KEY"</span><span style="color:#f4f4f5"> \
  -H </span><span style="color:#fcd34d">"Content-Type: application/json"</span><span style="color:#f4f4f5"> \
  -H </span><span style="color:#fcd34d">"Accept: application/json"</span><span style="color:#f4f4f5"> \
  -d </span><span style="color:#fcd34d">'{"amount":500.00,"currency":"PHP","gateway":"gcash","reference":"ORDER-20260228-0001"}'</span></pre>

        <h3 class="{{ $subHeading }}">{{ __('Sample Response') }}</h3>
        <pre class="{{ $jsonCardClasses }}" style="{{ $jsonCardStyle }}">{
<span style="color:#67e8f9">"success"</span>: <span style="color:#6ee7b7">true</span>,
<span style="color:#67e8f9">"data"</span>: {
  <span style="color:#67e8f9">"payment_id"</span>: <span style="color:#fcd34d">"uuid-value"</span>,
  <span style="color:#67e8f9">"transaction_id"</span>: <span style="color:#fcd34d">"uuid-value"</span>,
  <span style="color:#67e8f9">"gateway"</span>: <span style="color:#fcd34d">"gcash"</span>,
  <span style="color:#67e8f9">"amount"</span>: <span style="color:#c4b5fd">500</span>,
  <span style="color:#67e8f9">"product_amount"</span>: <span style="color:#c4b5fd">500</span>,
  <span style="color:#67e8f9">"currency"</span>: <span style="color:#fcd34d">"PHP"</span>,
  <span style="color:#67e8f9">"gross_amount"</span>: <span style="color:#c4b5fd">527.5</span>,
  <span style="color:#67e8f9">"gatewayhub_platform_fee_percent"</span>: <span style="color:#c4b5fd">1.5</span>,
  <span style="color:#67e8f9">"gatewayhub_platform_fee"</span>: <span style="color:#c4b5fd">7.5</span>,
  <span style="color:#67e8f9">"payer_fee_amount"</span>: <span style="color:#c4b5fd">27.5</span>,
  <span style="color:#67e8f9">"convenience_fee"</span>: <span style="color:#c4b5fd">20</span>,
  <span style="color:#67e8f9">"customer_total"</span>: <span style="color:#c4b5fd">527.5</span>,
  <span style="color:#67e8f9">"gatewayhub_net_amount"</span>: <span style="color:#c4b5fd">500</span>,
  <span style="color:#67e8f9">"status"</span>: <span style="color:#fcd34d">"pending"</span>,
  <span style="color:#67e8f9">"qr_data"</span>: <span style="color:#fcd34d">"000201..."</span>,
  <span style="color:#67e8f9">"expires_at"</span>: <span style="color:#fcd34d">"2026-02-28T12:00:00+08:00"</span>,
  <span style="color:#67e8f9">"redirect_url"</span>: <span style="color:#fda4af">null</span>,
  <span style="color:#67e8f9">"checkout_url"</span>: <span style="color:#fda4af">null</span>,
  <span style="color:#67e8f9">"merchant"</span>: {
    <span style="color:#67e8f9">"name"</span>: <span style="color:#fcd34d">"KemoPay Merchant"</span>,
    <span style="color:#67e8f9">"logo"</span>: <span style="color:#fcd34d">"https://..."</span>,
    <span style="color:#67e8f9">"theme_color"</span>: <span style="color:#fcd34d">"#1D4ED8"</span>
  }
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
                <dt class="{{ $fieldKey }}">transaction_id</dt>
                <dd class="mt-0.5">{{ __('Same value as payment_id for clients that expect a transaction identifier.') }}</dd>
            </div>
            <div>
                <dt class="{{ $fieldKey }}">checkout_url</dt>
                <dd class="mt-0.5">{{ __('Same as redirect_url when present; otherwise null.') }}</dd>
            </div>
            <div>
                <dt class="{{ $fieldKey }}">merchant</dt>
                <dd class="mt-0.5">{{ __('Branding for checkout: display name, logo URL, and theme color.') }}</dd>
            </div>
            <div>
                <dt class="{{ $fieldKey }}">qr_data</dt>
                <dd class="mt-0.5">{{ __('QR value returned by the gateway. It can be an EMVCo payload string that your app must encode, or a data URI image that can be used directly as an image source. Do not modify the value.') }}</dd>
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
            <div>
                <dt class="{{ $fieldKey }}">amount</dt>
                <dd class="mt-0.5">{{ __('The requested product value. It matches product_amount and remains the same amount sent in the request.') }}</dd>
            </div>
            <div>
                <dt class="{{ $fieldKey }}">gross_amount</dt>
                <dd class="mt-0.5">{{ __('The full customer charge: product value plus the KemoPay platform fee and convenience fee.') }}</dd>
            </div>
            <div>
                <dt class="{{ $fieldKey }}">product_amount</dt>
                <dd class="mt-0.5">{{ __("The product value requested by the merchant. The merchant's net after the KemoPay fee equals this amount.") }}</dd>
            </div>
            <div>
                <dt class="{{ $fieldKey }}">gatewayhub_platform_fee_percent</dt>
                <dd class="mt-0.5">{{ __('KemoPay platform fee percentage snapshotted for this payment. It is calculated from the product value, not from the customer total.') }}</dd>
            </div>
            <div>
                <dt class="{{ $fieldKey }}">gatewayhub_platform_fee</dt>
                <dd class="mt-0.5">{{ __('KemoPay platform fee added on top of the product value. Older payments may still show the fee that was deducted when they were paid.') }}</dd>
            </div>
            <div>
                <dt class="{{ $fieldKey }}">base_amount</dt>
                <dd class="mt-0.5">{{ __('Product value. Same value as amount and product_amount.') }}</dd>
            </div>
            <div>
                <dt class="{{ $fieldKey }}">platform_fee_rate</dt>
                <dd class="mt-0.5">{{ __('Snapshotted platform fee percentage points, such as 3.00 for 3%.') }}</dd>
            </div>
            <div>
                <dt class="{{ $fieldKey }}">platform_fee</dt>
                <dd class="mt-0.5">{{ __('Same amount as gatewayhub_platform_fee; the field name is retained for API compatibility.') }}</dd>
            </div>
            <div>
                <dt class="{{ $fieldKey }}">payer_fee_amount</dt>
                <dd class="mt-0.5">{{ __('Total amount added to the product value for the payer, including the platform fee and convenience fee.') }}</dd>
            </div>
            <div>
                <dt class="{{ $fieldKey }}">convenience_fee</dt>
                <dd class="mt-0.5">{{ __('Fixed convenience fee added on top of the base amount. Null on payments created before this fee.') }}</dd>
            </div>
            <div>
                <dt class="{{ $fieldKey }}">customer_total</dt>
                <dd class="mt-0.5">{{ __('Amount sent to PayMongo: product value plus platform fee plus convenience fee.') }}</dd>
            </div>
            <div>
                <dt class="{{ $fieldKey }}">gatewayhub_net_amount</dt>
                <dd class="mt-0.5">{{ __('Merchant settlement amount. For current payments this is the full product value because payer fees are added on top. Older payments keep their original net after the platform fee was deducted.') }}</dd>
            </div>
        </dl>
    </div>

    <div class="{{ $cardWrap }}">
        <h2 class="{{ $sectionHeading }}">{{ __('Merchant Wallet and Payouts') }}</h2>
        <p class="{{ $bodyText }}">{{ __("These endpoints read the merchant wallet and send available balance to a bank or e-wallet. The customer's total includes the KemoPay platform fee, so a paid payment credits the merchant's full product_amount. PayMongo processing fees are separate. Wallet settlement must be enabled; otherwise these routes return 404.") }}</p>

        <h3 class="{{ $subHeading }}">{{ __('Read balance and transaction history') }}</h3>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}"><span style="color:#34d399">GET</span> <span style="color:#f4f4f5">/api/wallets/balances?currency=PHP</span>
<span style="color:#34d399">GET</span> <span style="color:#f4f4f5">/api/wallets/transactions?currency=PHP&amp;per_page=20</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span></pre>
        <p class="{{ $bodyText }}">{{ __('Balance data includes wallet_balance, available_balance, settled_balance, pending_net_settlement, today_gross, today_platform_fees, today_net_settled, and as_of. Transaction history returns a transactions array and pagination object; per_page is 1–100 and defaults to 20.') }}</p>

        <h3 class="{{ $subHeading }}">{{ __('Find a receiving bank or e-wallet') }}</h3>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}"><span style="color:#34d399">GET</span> <span style="color:#f4f4f5">/api/wallets/payout-institutions?provider=instapay</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span></pre>
        <p class="{{ $bodyText }}">{{ __('provider must be instapay or pesonet. Use the institution provider_code as destination_account.bic.') }}</p>

        <h3 class="{{ $subHeading }}">{{ __('Create an external payout') }}</h3>
        <p class="{{ $bodyText }}">{{ __('InstaPay supports PHP 1.00-50,000.00; PESONet supports PHP 1.00-10,000,000.00. Funds are reserved from the merchant wallet while the transfer is processed. Use the same Idempotency-Key to retry the same payout.') }}</p>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}"><span style="color:#34d399">POST</span> <span style="color:#f4f4f5">/api/wallets/payouts</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span>
<span style="color:#7dd3fc">Idempotency-Key:</span> <span style="color:#fcd34d">merchant-payout-1001</span>
<span style="color:#7dd3fc">Content-Type:</span> <span style="color:#f4f4f5">application/json</span>

{
  <span style="color:#67e8f9">"provider"</span>: <span style="color:#fcd34d">"instapay"</span>,
  <span style="color:#67e8f9">"amount"</span>: <span style="color:#c4b5fd">492.50</span>,
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
        <p class="{{ $bodyText }}">{{ __('A successful response contains data.payout with its ID, reference_number, amount, provider, status, masked destination account, provider fee, and failure details when present. Statuses are submitting, submission_unknown, pending, succeeded, or failed. If submission_unknown is returned, check the payout status and do not create a second payout with a new key.') }}</p>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}"><span style="color:#34d399">GET</span> <span style="color:#f4f4f5">/api/wallets/payouts?currency=PHP&amp;per_page=20</span>
<span style="color:#34d399">GET</span> <span style="color:#f4f4f5">/api/wallets/payouts/{payout_id}</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span></pre>
        <p class="{{ $calloutInfo }}">{{ __('Payouts are sent from the activated PayMongo wallet managed by KemoPay. KemoPay manages the internal PayMongo webhook for transfer.outward.successful and transfer.outward.failed. Merchants set their payment callback URL under API Credentials.') }}</p>
    </div>

    <div class="{{ $cardWrap }}">
        <h2 class="{{ $sectionHeading }}">{{ __('Your webhook endpoint (outbound to your server)') }}</h2>
        <p class="{{ $bodyText }}">{{ __('Configure your HTTPS callback URL and signing secret on the API Credentials page. KemoPay POSTs payment updates to your URL — this is what you implement in your backend.') }}</p>

        <h3 class="{{ $subHeading }}">{{ __('Event') }}</h3>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('payment.updated') }}</p>

        <h3 class="{{ $subHeading }}">{{ __('Signature Headers') }}</h3>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}"><span style="color:#7dd3fc">X-Merchant-Timestamp:</span> <span style="color:#f4f4f5">1700000000</span>
<span style="color:#7dd3fc">X-Merchant-Signature:</span> <span style="color:#f4f4f5">hash_hmac('sha256', timestamp + '.' + body, webhook_secret)</span></pre>

        <h3 class="{{ $subHeading }}">{{ __('Signature Details') }}</h3>
        <ul class="mt-3 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
            <li>{{ __('Use the raw request body bytes exactly as received (no re-encoding or whitespace changes).') }}</li>
            <li>{{ __('Signature is the hex output of HMAC-SHA256 for') }} <code class="font-mono">timestamp + '.' + raw_body</code> {{ __('using the signing secret from API Credentials.') }}</li>
            <li>{{ __('Compare the signature with a constant-time check and do not prefix with "sha256=".') }}</li>
        </ul>

        <h3 class="{{ $subHeading }}">{{ __('PHP Receiver Example') }}</h3>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Set KEMOPAY_WEBHOOK_SECRET in your server environment. Add your own idempotent order update where indicated.') }}</p>
        <pre class="{{ $jsonCardClasses }}" style="{{ $jsonCardStyle }}"><code>@verbatim
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
        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Define processPaymentUpdateIdempotently() in your application before enabling this endpoint. Return a 2xx response after recording the update; webhooks may be delivered more than once.') }}</p>

        <h3 class="{{ $subHeading }}">{{ __('Payload') }}</h3>
        <pre class="{{ $jsonCardClasses }}" style="{{ $jsonCardStyle }}">{
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
  <span style="color:#67e8f9">"gross_amount"</span>: <span style="color:#c4b5fd">527.5</span>,
  <span style="color:#67e8f9">"gatewayhub_platform_fee_percent"</span>: <span style="color:#c4b5fd">1.5</span>,
  <span style="color:#67e8f9">"gatewayhub_platform_fee"</span>: <span style="color:#c4b5fd">7.5</span>,
  <span style="color:#67e8f9">"payer_fee_amount"</span>: <span style="color:#c4b5fd">27.5</span>,
  <span style="color:#67e8f9">"convenience_fee"</span>: <span style="color:#c4b5fd">20</span>,
  <span style="color:#67e8f9">"customer_total"</span>: <span style="color:#c4b5fd">527.5</span>,
  <span style="color:#67e8f9">"gatewayhub_net_amount"</span>: <span style="color:#c4b5fd">500</span>,
  <span style="color:#67e8f9">"provider_reference"</span>: <span style="color:#fcd34d">"provider-id"</span>,
  <span style="color:#67e8f9">"paid_at"</span>: <span style="color:#fcd34d">"2026-02-28T12:02:00+08:00"</span>,
  <span style="color:#67e8f9">"created_at"</span>: <span style="color:#fcd34d">"2026-02-28T12:00:00+08:00"</span>,
  <span style="color:#67e8f9">"updated_at"</span>: <span style="color:#fcd34d">"2026-02-28T12:02:00+08:00"</span>
}
}</pre>
        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('The data.gateway field matches your payment gateway code (for example gcash, maya, grab_pay, shopeepay, or qrph).') }}</p>
        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('The fee fields retain their existing JSON names for API compatibility. KemoPay adds its configured platform fee to the customer total; provider processing or conversion fees remain separate.') }}</p>
    </div>

    <div class="{{ $cardWrap }}">
        <h2 class="{{ $sectionHeading }}">{{ __('Webhook Troubleshooting') }}</h2>
        <ul class="mt-3 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
            <li>{{ __('Signature mismatch: confirm you hash timestamp + "." + raw body with the signing secret from API Credentials.') }}</li>
            <li>{{ __('Time skew: ensure your server clock is accurate when comparing X-Merchant-Timestamp.') }}</li>
            <li>{{ __('Retries: the platform retries failed deliveries; return 2xx to stop retries.') }}</li>
        </ul>
    </div>

    <div class="{{ $cardWrap }}">
        <h2 class="{{ $sectionHeading }}">{{ __('Get Payment Status') }}</h2>
        <p class="{{ $bodyText }}">{{ __('Fetch the current status of a payment from your backend. Call this before fulfilling an order, or as a fallback when a webhook is delayed.') }}</p>
        <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">{{ __('The response includes product_amount, gross_amount, gatewayhub_platform_fee_percent, gatewayhub_platform_fee, and gatewayhub_net_amount. KemoPay adds its fee to the customer total; provider payload fee fields are not part of this API response.') }}</p>

        <h3 class="{{ $subHeading }}">{{ __('Sample Request') }}</h3>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}"><span style="color:#34d399">GET</span> <span style="color:#f4f4f5">/api/payments/{payment_id}/status HTTP/1.1</span>
<span style="color:#7dd3fc">Authorization:</span> <span style="color:#f4f4f5">Bearer</span> <span style="color:#fcd34d">YOUR_API_KEY</span>
<span style="color:#7dd3fc">Accept:</span> <span style="color:#f4f4f5">application/json</span></pre>

        <h3 class="{{ $subHeading }}">{{ __('cURL Example') }}</h3>
        <pre class="{{ $endpointCardClasses }}" style="{{ $endpointCardStyle }}"><span style="color:#f4f4f5">curl -X GET {{ rtrim(config('app.url'), '/') }}/api/payments/</span><span style="color:#fcd34d">{payment_id}</span><span style="color:#f4f4f5">/status \
  -H </span><span style="color:#fcd34d">"Authorization: Bearer YOUR_API_KEY"</span><span style="color:#f4f4f5"> \
  -H </span><span style="color:#fcd34d">"Accept: application/json"</span></pre>
        <h3 class="{{ $subHeading }}">{{ __('Sample Response') }}</h3>
        <pre class="{{ $jsonCardClasses }}" style="{{ $jsonCardStyle }}">{
<span style="color:#67e8f9">"success"</span>: <span style="color:#6ee7b7">true</span>,
<span style="color:#67e8f9">"data"</span>: {
  <span style="color:#67e8f9">"status"</span>: <span style="color:#fcd34d">"success"</span>,
  <span style="color:#67e8f9">"product_amount"</span>: <span style="color:#c4b5fd">500</span>,
  <span style="color:#67e8f9">"gross_amount"</span>: <span style="color:#c4b5fd">527.5</span>,
  <span style="color:#67e8f9">"gatewayhub_platform_fee_percent"</span>: <span style="color:#c4b5fd">1.5</span>,
  <span style="color:#67e8f9">"gatewayhub_platform_fee"</span>: <span style="color:#c4b5fd">7.5</span>,
  <span style="color:#67e8f9">"payer_fee_amount"</span>: <span style="color:#c4b5fd">27.5</span>,
  <span style="color:#67e8f9">"convenience_fee"</span>: <span style="color:#c4b5fd">20</span>,
  <span style="color:#67e8f9">"customer_total"</span>: <span style="color:#c4b5fd">527.5</span>,
  <span style="color:#67e8f9">"gatewayhub_net_amount"</span>: <span style="color:#c4b5fd">500</span>
},
<span style="color:#67e8f9">"error"</span>: <span style="color:#fda4af">null</span>
}</pre>
    </div>

    <div class="{{ $cardWrap }}">
        <h2 class="{{ $sectionHeading }}">{{ __('Provider webhooks (not your integration)') }}</h2>
        <p class="{{ $bodyText }}">{{ __('PayMongo sends payment events to KemoPay on its internal webhook endpoint. KemoPay verifies the provider signature before updating the payment — separate from the merchant callback URL you configure.') }}</p>

        <div class="{{ $calloutInfo }}">
            <span class="font-semibold">{{ __('Implement only your merchant callback') }}</span>
            {{ __('You do not host KemoPay provider webhook endpoints or verify PayMongo signatures on your server. Add your HTTPS URL under API Credentials and verify X-Merchant-Timestamp and X-Merchant-Signature as documented above.') }}
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
                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Verify your merchant callback signatures.') }}</span>
                {{ __('On your registered webhook URL, reject any POST whose X-Merchant-Signature does not match an HMAC of timestamp + raw body. Rotate the signing secret from API Credentials if it may have leaked.') }}
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
            <li><span class="font-semibold">409</span> - {{ __('Idempotency key conflicts with a different payout request or payout submission is still being reconciled.') }}</li>
            <li><span class="font-semibold">422</span> - {{ __('Validation error in request payload.') }}</li>
            <li><span class="font-semibold">429</span> - {{ __('Rate limit exceeded.') }}</li>
        </ul>
    </div>
</div>
