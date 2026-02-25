@extends('layouts.admin')

@section('content')
<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Merchants</h1>
        <p class="mt-1 text-zinc-600 dark:text-zinc-400">Activate or deactivate merchants. Deactivated merchants cannot use the API or access the dashboard.</p>
        @if (session('status'))
            <flux:callout variant="success" class="mt-4">{{ session('status') }}</flux:callout>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 [&_tbody_tr:hover]:bg-zinc-50 dark:[&_tbody_tr:hover]:bg-zinc-700/50 [&_tbody_tr]:border-b [&_tbody_tr]:border-zinc-200/60 [&_tbody_tr:last-child]:border-b-0 dark:[&_tbody_tr]:border-zinc-600/40 [&_td]:py-4 [&_th]:font-semibold [&_th]:text-zinc-900 dark:[&_th]:text-zinc-100">
        <flux:table>
            <flux:table.columns :sticky="true">
                <flux:table.cell variant="strong">Name</flux:table.cell>
                <flux:table.cell variant="strong">Email</flux:table.cell>
                <flux:table.cell variant="strong">Status</flux:table.cell>
                <flux:table.cell variant="strong" class="w-0">Actions</flux:table.cell>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($merchants as $merchant)
                    <flux:table.row wire:key="merchant-{{ $merchant->id }}">
                        <flux:table.cell>{{ $merchant->name }}</flux:table.cell>
                        <flux:table.cell>{{ $merchant->email }}</flux:table.cell>
                        <flux:table.cell>
                            <x-status-badge :status="$merchant->is_active ? 'active' : 'inactive'" :label="$merchant->is_active ? 'Active' : 'Inactive'" />
                        </flux:table.cell>
                        <flux:table.cell>
                            <form action="{{ route('admin.merchants.toggle', ['user' => $merchant]) }}" method="POST" class="inline">
                                @csrf
                                @method('PATCH')
                                <flux:button type="submit" variant="{{ $merchant->is_active ? 'danger' : 'primary' }}" size="sm">
                                    {{ $merchant->is_active ? 'Deactivate' : 'Activate' }}
                                </flux:button>
                            </form>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500 dark:text-zinc-400">No merchants yet.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
@endsection
