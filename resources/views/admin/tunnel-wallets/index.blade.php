@extends('layouts.admin')

@section('content')
<div class="flex h-full w-full flex-1 flex-col gap-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">SurePay Settlement Controls</h1>
            <p class="mt-1 text-zinc-600 dark:text-zinc-400">Admin-only settlement configuration and batch transfer controls.</p>
        </div>
        <button
            type="button"
            onclick="document.getElementById('tunnel-sending-config-modal').showModal()"
            class="group inline-flex h-10 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-800 shadow-sm transition hover:border-zinc-400 hover:bg-zinc-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-500/40 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:hover:border-zinc-500 dark:hover:bg-zinc-700"
        >
            <svg class="size-4 text-zinc-500 transition group-hover:text-zinc-700 dark:text-zinc-300 dark:group-hover:text-zinc-100" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 2a.75.75 0 0 1 .75.75v1.524a5.75 5.75 0 0 1 2.89 1.197l1.078-1.078a.75.75 0 1 1 1.06 1.06L14.7 6.532a5.75 5.75 0 0 1 1.026 2.468h1.524a.75.75 0 0 1 0 1.5h-1.524a5.75 5.75 0 0 1-1.026 2.468l1.078 1.079a.75.75 0 1 1-1.06 1.06l-1.079-1.078a5.75 5.75 0 0 1-2.468 1.026v1.524a.75.75 0 0 1-1.5 0v-1.524a5.75 5.75 0 0 1-2.468-1.026l-1.079 1.079a.75.75 0 0 1-1.06-1.06l1.078-1.079A5.75 5.75 0 0 1 4.274 10.5H2.75a.75.75 0 0 1 0-1.5h1.524A5.75 5.75 0 0 1 5.3 6.532L4.222 5.454a.75.75 0 0 1 1.06-1.06L6.36 5.47a5.75 5.75 0 0 1 2.89-1.197V2.75A.75.75 0 0 1 10 2Zm0 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Z" clip-rule="evenodd" />
            </svg>
            Configure Settlement Sending
        </button>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-300/70 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-700/60 dark:bg-emerald-900/20 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-lg border border-red-300/70 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-700/60 dark:bg-red-900/20 dark:text-red-300">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <p class="text-sm text-zinc-500 dark:text-zinc-400">Pending SurePay settlements</p>
        <p class="mt-2 font-mono text-3xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($pendingSettlements) }}</p>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Settlement Sending Configuration</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <p class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Interval</p>
                <p class="mt-1 font-mono text-lg tabular-nums text-zinc-900 dark:text-zinc-100">{{ $tunnelSendingSetting->intervalLabel() }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Tax Percentage</p>
                <p class="mt-1 font-mono text-lg tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format((float) $tunnelSendingSetting->tax_percentage, 2) }}%</p>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <table class="min-w-[1320px] w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-zinc-50 dark:bg-zinc-900/30">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Merchant</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 whitespace-nowrap">Auto Settle</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Currency</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 whitespace-nowrap">Client ID</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 whitespace-nowrap">Client Secret</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Webhook ID</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Updated</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($merchants as $merchant)
                    @php
                        $setting = $merchant->merchantWalletSetting;
                        $formId = 'merchant-tunnel-setting-' . $merchant->id;
                    @endphp
                    <tr>
                        <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100 align-top whitespace-nowrap">{{ $merchant->name }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300 align-top">
                            <input form="{{ $formId }}" type="hidden" name="auto_settle_to_real_wallet" value="0">
                            <input form="{{ $formId }}" type="checkbox" name="auto_settle_to_real_wallet" value="1" {{ ($setting?->auto_settle_to_real_wallet ?? true) ? 'checked' : '' }} class="size-4 rounded border-zinc-300">
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300 align-top">
                            <input form="{{ $formId }}" type="text" name="default_currency" value="{{ $setting?->default_currency ?? 'PHP' }}" class="w-24 rounded border border-zinc-300 px-2 py-1 text-xs dark:border-zinc-600 dark:bg-zinc-900">
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300 align-top">
                            <input form="{{ $formId }}" type="text" name="tunnel_client_id" value="{{ $setting?->tunnel_client_id ?? '' }}" class="w-64 rounded border border-zinc-300 px-2 py-1 text-xs dark:border-zinc-600 dark:bg-zinc-900">
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300 align-top">
                            <input form="{{ $formId }}" type="password" name="tunnel_client_secret" placeholder="Leave blank to keep current" class="w-64 rounded border border-zinc-300 px-2 py-1 text-xs dark:border-zinc-600 dark:bg-zinc-900">
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300 align-top">
                            <input form="{{ $formId }}" type="text" name="tunnel_webhook_id" value="{{ $setting?->tunnel_webhook_id ?? '' }}" class="w-56 rounded border border-zinc-300 px-2 py-1 text-xs dark:border-zinc-600 dark:bg-zinc-900">
                            <input form="{{ $formId }}" type="hidden" name="notes" value="{{ $setting?->notes ?? '' }}">
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300 align-top whitespace-nowrap">{{ $setting?->updated_at?->format('M j, Y g:i A') ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300 align-top">
                            <form id="{{ $formId }}" method="POST" action="{{ route('admin.surepay-wallets.update', $merchant) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="inline-flex h-8 items-center justify-center rounded-md border border-zinc-300 px-3 text-xs font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700">
                                    Save
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">No merchants found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

<dialog id="tunnel-sending-config-modal" class="w-full max-w-lg rounded-xl border border-zinc-200 p-0 shadow-xl backdrop:bg-black/30 dark:border-zinc-700 dark:bg-zinc-900">
    <form method="dialog" class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Configure Settlement Sending</h3>
            <button type="submit" class="text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-100">Close</button>
        </div>
    </form>
    <form id="tunnel-sending-config-form" method="POST" action="{{ route('admin.surepay-wallets.surepay-sending-setting') }}" class="space-y-4 px-6 py-5" onsubmit="const btn=document.getElementById('tunnel-sending-save-btn'); if(btn){btn.disabled=true; btn.textContent='Saving...';}">
        @csrf
        @method('PATCH')
        <div>
            <label for="batch_interval_value" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Batch Interval</label>
            <div class="grid grid-cols-3 gap-2">
                <input id="batch_interval_value" type="number" name="batch_interval_value" min="1" max="100000" value="{{ old('batch_interval_value', $tunnelSendingIntervalValue) }}" class="col-span-2 w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
                <select name="batch_interval_unit" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
                    <option value="seconds" @selected(old('batch_interval_unit', $tunnelSendingIntervalUnit) === 'seconds')>Seconds</option>
                    <option value="minutes" @selected(old('batch_interval_unit', $tunnelSendingIntervalUnit) === 'minutes')>Minutes</option>
                    <option value="days" @selected(old('batch_interval_unit', $tunnelSendingIntervalUnit) === 'days')>Days</option>
                    <option value="weeks" @selected(old('batch_interval_unit', $tunnelSendingIntervalUnit) === 'weeks')>Weeks</option>
                </select>
            </div>
            @error('batch_interval_value')
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
            @error('batch_interval_unit')
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="tax_percentage" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Tax Percentage</label>
            <input id="tax_percentage" type="number" step="0.01" min="0" max="100" name="tax_percentage" value="{{ old('tax_percentage', $tunnelSendingSetting->tax_percentage) }}" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
            @error('tax_percentage')
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="document.getElementById('tunnel-sending-config-modal').close()" class="inline-flex h-9 items-center justify-center rounded-md border border-zinc-300 px-3 text-sm text-zinc-700 hover:bg-zinc-100 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-800">Cancel</button>
            <button id="tunnel-sending-save-btn" type="submit" class="inline-flex h-9 items-center justify-center rounded-md border border-zinc-900 px-3 text-sm font-medium text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-70 dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200" style="background-color:#18181b;color:#ffffff;">Save</button>
        </div>
    </form>
</dialog>
@if ($errors->hasAny(['batch_interval_value', 'batch_interval_unit', 'tax_percentage']))
    <script>
        window.addEventListener('DOMContentLoaded', function () {
            document.getElementById('tunnel-sending-config-modal')?.showModal();
        });
    </script>
@endif
@endsection
