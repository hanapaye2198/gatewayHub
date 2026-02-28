@extends('layouts.admin')

@section('content')
<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">SurePay Admin Dashboard</h1>
    </div>

    <div class="grid gap-5 md:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Collections</p>
            <p class="mt-2 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
                PHP {{ number_format($totalCollections, 2) }}
            </p>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <form method="GET" action="{{ route('admin.index') }}" class="space-y-3">
                <div>
                    <label for="client_id" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Client Filter
                    </label>
                    <select id="client_id" name="client_id" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100">
                        <option value="">All clients</option>
                        @foreach ($clientRows as $clientRow)
                            <option value="{{ $clientRow['id'] }}" @selected((string) $selectedClientId === (string) $clientRow['id'])>
                                {{ $clientRow['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary">Apply Filter</flux:button>
                    <a href="{{ route('admin.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-zinc-300 px-3 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700">
                        Clear
                    </a>
                </div>
            </form>

            <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">
                {{ $selectedClientName ? 'Filtered client: '.$selectedClientName : 'Filtered client: All clients' }}
            </p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
                PHP {{ number_format($filteredCollections, 2) }}
            </p>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">All Clients</h2>
            <flux:button variant="outline" :href="route('admin.gateways.index')">Configure Gateways Per Client</flux:button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr>
                        <th class="px-4 py-2 text-left font-semibold text-zinc-500 dark:text-zinc-400">Client</th>
                        <th class="px-4 py-2 text-right font-semibold text-zinc-500 dark:text-zinc-400">Total Collections</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($clientRows as $clientRow)
                        <tr>
                            <td class="px-4 py-2 text-zinc-900 dark:text-zinc-100">{{ $clientRow['name'] }}</td>
                            <td class="px-4 py-2 text-right font-mono tabular-nums text-zinc-900 dark:text-zinc-100">
                                PHP {{ number_format($clientRow['total_collections'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">No clients found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
