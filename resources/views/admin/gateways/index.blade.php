@extends('layouts.admin')
@section('content')
<div class="flex h-full w-full flex-1 flex-col gap-6 px-4 sm:px-6 lg:px-8">

    {{-- Page Header --}}
    <div class="rounded-2xl border border-zinc-100 bg-gradient-to-br from-white to-zinc-50/60 px-8 py-7 shadow-sm dark:border-zinc-700/60 dark:from-zinc-800 dark:to-zinc-800/80">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-zinc-900 shadow-md dark:bg-zinc-100">
                    <flux:icon name="credit-card" class="size-6 text-white dark:text-zinc-900" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">Gateways</h1>
                    <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">Manage global gateway availability and merchant-level access.</p>
                </div>
            </div>
            <div class="flex flex-shrink-0 items-center gap-2.5">
                <div class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 dark:border-emerald-800/50 dark:bg-emerald-900/20">
                    <span class="size-2 rounded-full bg-emerald-500 shadow-sm shadow-emerald-400"></span>
                    <span class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">{{ $gateways->where('is_global_enabled', true)->count() }} enabled</span>
                </div>
                <div class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-100 px-4 py-2 dark:border-zinc-600 dark:bg-zinc-700/50">
                    <span class="text-sm font-semibold text-zinc-600 dark:text-zinc-300">{{ $gateways->count() }} total</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" class="rounded-xl border-emerald-200 bg-emerald-50 dark:border-emerald-800/40 dark:bg-emerald-900/20">
            {{ session('status') }}
        </flux:callout>
    @endif
    @if (session('error'))
        <flux:callout variant="danger" icon="x-circle" class="rounded-xl">
            {{ session('error') }}
        </flux:callout>
    @endif

    {{-- ── Section 1: Global Gateways ── --}}
    <div class="overflow-hidden rounded-2xl border border-zinc-100 bg-white shadow-sm dark:border-zinc-700/60 dark:bg-zinc-800">
        <div class="flex items-center gap-3 border-b border-zinc-100 px-7 py-5 dark:border-zinc-700/60">
            <div class="flex size-8 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                <flux:icon name="globe-alt" class="size-4 text-zinc-500 dark:text-zinc-400" />
            </div>
            <div>
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Global Gateway Control</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Disabled gateways cannot be used by any merchant.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[520px]">
                <thead>
                    <tr class="border-b border-zinc-100 bg-zinc-50/70 dark:border-zinc-700/60 dark:bg-zinc-900/30">
                        <th class="px-7 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Gateway</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Code</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Status</th>
                        <th class="px-7 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
                    @forelse ($gateways as $gateway)
                        <tr class="group transition-colors duration-100 hover:bg-zinc-50/80 dark:hover:bg-zinc-700/25">
                            <td class="px-7 py-4">
                                <div class="flex items-center gap-3.5">
                                    <div class="flex size-9 flex-shrink-0 items-center justify-center rounded-xl bg-zinc-900 text-sm font-bold text-white shadow-sm dark:bg-zinc-100 dark:text-zinc-900">
                                        {{ strtoupper(mb_substr($gateway->name, 0, 1)) }}
                                    </div>
                                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $gateway->name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <code class="rounded-lg border border-zinc-200 bg-zinc-50 px-2.5 py-1 text-xs font-mono font-medium text-zinc-600 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">{{ $gateway->code }}</code>
                            </td>
                            <td class="px-5 py-4">
                                @if ($gateway->is_global_enabled)
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-800/40 dark:bg-emerald-900/20 dark:text-emerald-400">
                                        <span class="size-1.5 rounded-full bg-emerald-500"></span>Enabled
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-100 px-3 py-1 text-xs font-semibold text-zinc-500 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">
                                        <span class="size-1.5 rounded-full bg-zinc-400"></span>Disabled
                                    </span>
                                @endif
                            </td>
                            <td class="px-7 py-4 text-right">
                                <form action="{{ route('admin.gateways.toggle', ['gateway' => $gateway]) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <flux:button
                                        type="submit"
                                        variant="{{ $gateway->is_global_enabled ? 'danger' : 'primary' }}"
                                        size="sm"
                                        class="min-w-[88px]"
                                    >
                                        {{ $gateway->is_global_enabled ? 'Disable' : 'Enable' }}
                                    </flux:button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-7 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="flex size-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-700/50">
                                        <flux:icon name="credit-card" class="size-7 text-zinc-400" />
                                    </div>
                                    <p class="font-semibold text-zinc-700 dark:text-zinc-300">No gateways configured</p>
                                    <p class="text-sm text-zinc-400 dark:text-zinc-500">Add gateways via migrations or seeders to get started.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Section 2: Per-Merchant Gateway Access ── --}}
    <div class="overflow-hidden rounded-2xl border border-zinc-100 bg-white shadow-sm dark:border-zinc-700/60 dark:bg-zinc-800">
        <div class="flex items-center gap-3 border-b border-zinc-100 px-7 py-5 dark:border-zinc-700/60">
            <div class="flex size-8 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                <flux:icon name="building-storefront" class="size-4 text-zinc-500 dark:text-zinc-400" />
            </div>
            <div>
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Per-Merchant Gateway Access</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Central control for enabling or disabling gateways per merchant.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px]">
                <thead>
                    <tr class="border-b border-zinc-100 bg-zinc-50/70 dark:border-zinc-700/60 dark:bg-zinc-900/30">
                        <th class="px-7 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Merchant</th>
                        @foreach ($gateways as $gateway)
                            <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                                {{ $gateway->name }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
                    @forelse ($merchants as $merchant)
                        <tr class="transition-colors duration-100 hover:bg-zinc-50/80 dark:hover:bg-zinc-700/25">
                            <td class="px-7 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-8 flex-shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-xs font-bold text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                                        {{ strtoupper(mb_substr($merchant->name, 0, 1)) }}
                                    </div>
                                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $merchant->name }}</span>
                                </div>
                            </td>
                            @foreach ($gateways as $gateway)
                                @php
                                    $merchantGateway = $gateway->merchantGateways->firstWhere('user_id', $merchant->id);
                                    $isEnabled = (bool) ($merchantGateway?->is_enabled ?? false);
                                @endphp
                                <td class="px-5 py-4 text-center">
                                    @if (! $gateway->is_global_enabled)
                                        <span class="inline-flex items-center rounded-full border border-zinc-200 bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-400 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-500">
                                            Global Off
                                        </span>
                                    @else
                                        <form method="POST" action="{{ route('admin.gateways.merchant-update', ['gateway' => $gateway, 'user' => $merchant]) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="is_enabled" value="{{ $isEnabled ? 0 : 1 }}">
                                            <flux:button type="submit" size="sm" variant="{{ $isEnabled ? 'danger' : 'primary' }}">
                                                {{ $isEnabled ? 'Disable' : 'Enable' }}
                                            </flux:button>
                                        </form>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $gateways->count() + 1 }}" class="px-7 py-12 text-center text-sm text-zinc-400 dark:text-zinc-500">
                                No merchants found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Section 3: Platform Gateway Credentials ── --}}
    <div class="overflow-hidden rounded-2xl border border-zinc-100 bg-white shadow-sm dark:border-zinc-700/60 dark:bg-zinc-800">
        <div class="flex items-center gap-3 border-b border-zinc-100 px-7 py-5 dark:border-zinc-700/60">
            <div class="flex size-8 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                <flux:icon name="key" class="size-4 text-zinc-500 dark:text-zinc-400" />
            </div>
            <div>
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Platform Gateway Credentials</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Customer-facing options (GCash, Maya, PayPal, PayQRPH) are collected via Coins dynamic QR. Configure Coins platform credentials only.</p>
            </div>
        </div>

        <div class="space-y-4 px-7 py-6">
            @foreach ($gateways as $gateway)
                @php
                    $fields = $platformCredentialFields[$gateway->id] ?? [];
                    $config = $platformConfigs[$gateway->id] ?? [];
                @endphp
                @continue($fields === [])
                <div class="overflow-hidden rounded-xl border border-zinc-200/80 dark:border-zinc-700/60">
                    <div class="flex items-center gap-3 border-b border-zinc-100 bg-zinc-50/60 px-5 py-3.5 dark:border-zinc-700/60 dark:bg-zinc-900/20">
                        <div class="flex size-8 items-center justify-center rounded-lg bg-zinc-900 text-xs font-bold text-white dark:bg-zinc-100 dark:text-zinc-900">
                            {{ strtoupper(mb_substr($gateway->name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $gateway->name }}</p>
                            <p class="text-xs font-mono text-zinc-400 dark:text-zinc-500">{{ strtoupper($gateway->code) }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.gateways.platform-config', ['gateway' => $gateway]) }}" class="px-5 py-5">
                        @csrf
                        @method('PATCH')
                        <div class="grid gap-4 md:grid-cols-2">
                            @foreach ($fields as $field)
                                @php
                                    $key     = $field['key']      ?? null;
                                    $label   = $field['label']    ?? $key;
                                    $type    = $field['type']     ?? 'text';
                                    $required = (bool) ($field['required'] ?? false);
                                    $masked  = (bool) ($field['masked']   ?? false);
                                    $value   = is_string($key) ? ($config[$key] ?? '') : '';
                                @endphp
                                @if (is_string($key) && $key !== '')
                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                            {{ $label }}@if($required)<span class="ml-0.5 text-red-500">*</span>@endif
                                        </label>
                                        @if ($type === 'select')
                                            <select name="config[{{ $key }}]" class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm transition-colors focus:border-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-200 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:ring-zinc-700">
                                                @foreach (($field['options'] ?? []) as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input
                                                type="{{ $type === 'password' ? 'password' : 'text' }}"
                                                name="config[{{ $key }}]"
                                                value="{{ $masked ? '' : $value }}"
                                                placeholder="{{ $masked ? 'Leave blank to keep current value' : '' }}"
                                                class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm transition-colors placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-200 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-600 dark:focus:ring-zinc-700"
                                            >
                                        @endif
                                        @error('config.'.$key)
                                            <p class="text-xs text-red-500 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <div class="mt-5 flex justify-end">
                            <flux:button type="submit" size="sm" variant="primary">
                                Save Credentials
                            </flux:button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </div>

</div>
@endsection
