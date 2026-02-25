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

</div>
@endsection
