@extends('layouts.admin')

@section('content')
<div class="flex h-full w-full flex-1 flex-col gap-8 px-4 sm:px-6 lg:px-8">

    {{-- Header: title left, stats right --}}
    <div class="rounded-2xl border border-zinc-200/80 bg-white px-8 py-8 shadow-sm transition-shadow duration-200 hover:shadow-md sm:px-10 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100 sm:text-3xl">Gateways</h1>
                <p class="mt-3 text-base text-zinc-600 dark:text-zinc-400">Enable or disable gateways globally. Disabled gateways cannot be used by any merchant.</p>
            </div>
            <div class="flex flex-shrink-0 flex-wrap items-center gap-3">
                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3.5 py-1.5 text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                    <span class="size-2 rounded-full bg-emerald-500"></span>
                    {{ $gateways->where('is_global_enabled', true)->count() }} enabled
                </span>
                <span class="inline-flex items-center rounded-full bg-zinc-100 px-3.5 py-1.5 text-sm font-medium text-zinc-600 dark:bg-zinc-600 dark:text-zinc-300">
                    {{ $gateways->count() }} total
                </span>
            </div>
        </div>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" class="rounded-xl">
            {{ session('status') }}
        </flux:callout>
    @endif
    @if (session('error'))
        <flux:callout variant="danger" icon="x-circle" class="rounded-xl">
            {{ session('error') }}
        </flux:callout>
    @endif

    {{-- Table card with inner padding --}}
    <div class="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-sm transition-shadow duration-200 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800">
        <div class="overflow-x-auto px-6 py-6 sm:px-8 sm:py-6">
            <flux:table class="w-full min-w-[560px]">
                <flux:table.columns :sticky="true" class="[&_th]:px-5 [&_th]:pb-4 [&_th]:pt-0 [&_th]:text-left [&_th]:text-xs [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wider [&_th]:text-zinc-500 dark:[&_th]:text-zinc-400">
                    <flux:table.cell variant="strong">Gateway</flux:table.cell>
                    <flux:table.cell variant="strong">Code</flux:table.cell>
                    <flux:table.cell variant="strong">Status</flux:table.cell>
                    <flux:table.cell variant="strong" class="text-right">Actions</flux:table.cell>
                </flux:table.columns>

                <flux:table.rows class="[&_td]:border-t [&_td]:border-zinc-200/80 [&_td]:px-5 [&_td]:py-5 [&_td]:align-middle dark:[&_td]:border-zinc-600/50 [&_tbody_tr]:transition-colors [&_tbody_tr]:duration-150 [&_tbody_tr:hover]:bg-zinc-50 dark:[&_tbody_tr:hover]:bg-zinc-700/40">
                    @forelse ($gateways as $gateway)
                        <flux:table.row>
                            <flux:table.cell>
                                <div class="flex items-center gap-4">
                                    <div class="flex size-11 flex-shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-base font-semibold text-zinc-600 shadow-sm dark:bg-zinc-700 dark:text-zinc-300">
                                        {{ strtoupper(mb_substr($gateway->name, 0, 1)) }}
                                    </div>
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $gateway->name }}</span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <code class="rounded-lg bg-zinc-100 px-2.5 py-1.5 text-xs font-mono text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">{{ $gateway->code }}</code>
                            </flux:table.cell>

                            <flux:table.cell>
                                <x-status-badge :status="$gateway->is_global_enabled ? 'enabled' : 'disabled'" :label="$gateway->is_global_enabled ? 'Enabled' : 'Disabled'" />
                            </flux:table.cell>

                            <flux:table.cell class="text-right">
                                <form action="{{ route('admin.gateways.toggle', ['gateway' => $gateway]) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <flux:button
                                        type="submit"
                                        variant="{{ $gateway->is_global_enabled ? 'danger' : 'primary' }}"
                                        size="sm"
                                        class="min-w-[92px]"
                                    >
                                        {{ $gateway->is_global_enabled ? 'Disable' : 'Enable' }}
                                    </flux:button>
                                </form>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="border-t border-zinc-200/80 py-20 text-center dark:border-zinc-600/50">
                                <div class="flex flex-col items-center gap-4">
                                    <div class="flex size-16 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-800">
                                        <flux:icon name="credit-card" class="size-8 text-zinc-400 dark:text-zinc-500" />
                                    </div>
                                    <div>
                                        <p class="font-medium text-zinc-700 dark:text-zinc-300">No gateways configured</p>
                                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Add gateways via migrations or seeders to get started.</p>
                                    </div>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-sm transition-shadow duration-200 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800">
        <div class="border-b border-zinc-200/80 px-6 py-4 dark:border-zinc-700">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Per-Merchant Gateway Access</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Central control for enabling or disabling gateways per merchant.</p>
        </div>
        <div class="overflow-x-auto px-6 py-6 sm:px-8 sm:py-6">
            <table class="min-w-[860px] w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-900/30">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Merchant</th>
                        @foreach ($gateways as $gateway)
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                {{ $gateway->name }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($merchants as $merchant)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                            <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $merchant->name }}</td>
                            @foreach ($gateways as $gateway)
                                @php
                                    $merchantGateway = $gateway->merchantGateways->firstWhere('user_id', $merchant->id);
                                    $isEnabled = (bool) ($merchantGateway?->is_enabled ?? false);
                                @endphp
                                <td class="px-4 py-3 text-center">
                                    @if (! $gateway->is_global_enabled)
                                        <span class="inline-flex rounded-full bg-zinc-200 px-2.5 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">Global Off</span>
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
                            <td colspan="{{ $gateways->count() + 1 }}" class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                No merchants found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-sm transition-shadow duration-200 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800">
        <div class="border-b border-zinc-200/80 px-6 py-4 dark:border-zinc-700">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Platform Gateway Credentials</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Current model: customer-facing options (GCash, Maya, PayPal, PayQRPH) are collected through Coins dynamic QR. Configure Coins platform credentials only.</p>
        </div>
        <div class="space-y-6 px-6 py-6 sm:px-8 sm:py-6">
            @foreach ($gateways as $gateway)
                @php
                    $fields = $platformCredentialFields[$gateway->id] ?? [];
                    $config = $platformConfigs[$gateway->id] ?? [];
                @endphp
                @continue($fields === [])
                <div class="rounded-xl border border-zinc-200/80 p-4 dark:border-zinc-700">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $gateway->name }}</h3>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ strtoupper($gateway->code) }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.gateways.platform-config', ['gateway' => $gateway]) }}" class="space-y-4">
                        @csrf
                        @method('PATCH')
                        <div class="grid gap-4 md:grid-cols-2">
                            @foreach ($fields as $field)
                                @php
                                    $key = $field['key'] ?? null;
                                    $label = $field['label'] ?? $key;
                                    $type = $field['type'] ?? 'text';
                                    $required = (bool) ($field['required'] ?? false);
                                    $masked = (bool) ($field['masked'] ?? false);
                                    $value = is_string($key) ? ($config[$key] ?? '') : '';
                                @endphp
                                @if (is_string($key) && $key !== '')
                                    <div>
                                        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                            {{ $label }} @if($required)<span class="text-red-500">*</span>@endif
                                        </label>
                                        @if ($type === 'select')
                                            <select name="config[{{ $key }}]" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900">
                                                @foreach (($field['options'] ?? []) as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input
                                                type="{{ $type === 'password' ? 'password' : 'text' }}"
                                                name="config[{{ $key }}]"
                                                value="{{ $masked ? '' : $value }}"
                                                placeholder="{{ $masked ? 'Leave blank to keep current' : '' }}"
                                                class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                                            >
                                        @endif
                                        @error('config.'.$key)
                                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <div>
                            <flux:button type="submit" size="sm" variant="primary">Save Platform Credentials</flux:button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </div>

</div>
@endsection
