@extends('layouts.admin')

@section('content')
    <div class="flex h-full w-full flex-1 flex-col gap-6 px-4 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-zinc-200 bg-white px-8 py-7 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Audit Logs') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Append-only record of platform administration changes.') }}
            </p>
        </div>

        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
            <div class="grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <flux:field>
                    <flux:label for="from_date">{{ __('From') }}</flux:label>
                    <flux:input id="from_date" name="from_date" type="date" :value="$filters['from_date'] ?? ''" />
                </flux:field>
                <flux:field>
                    <flux:label for="to_date">{{ __('To') }}</flux:label>
                    <flux:input id="to_date" name="to_date" type="date" :value="$filters['to_date'] ?? ''" />
                </flux:field>
                <flux:field>
                    <flux:label for="action">{{ __('Action') }}</flux:label>
                    <flux:select id="action" name="action">
                        <option value="">{{ __('All actions') }}</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label for="merchant_id">{{ __('Merchant') }}</flux:label>
                    <flux:select id="merchant_id" name="merchant_id">
                        <option value="">{{ __('All merchants') }}</option>
                        @foreach ($merchants as $merchant)
                            <option value="{{ $merchant->id }}" @selected((string) ($filters['merchant_id'] ?? '') === (string) $merchant->id)>{{ $merchant->name }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label for="actor_user_id">{{ __('Actor') }}</flux:label>
                    <flux:select id="actor_user_id" name="actor_user_id">
                        <option value="">{{ __('All actors') }}</option>
                        @foreach ($actors as $actor)
                            <option value="{{ $actor->id }}" @selected((string) ($filters['actor_user_id'] ?? '') === (string) $actor->id)>{{ $actor->name }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <div class="flex items-center gap-2">
                    <flux:button type="submit" variant="primary">{{ __('Apply') }}</flux:button>
                    <flux:button variant="ghost" :href="route('admin.audit-logs.index')" wire:navigate>{{ __('Clear') }}</flux:button>
                </div>
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
            @if ($logs->isEmpty())
                <p class="px-6 py-10 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No audit records match these filters.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                        <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:bg-zinc-950/40 dark:text-zinc-400">
                            <tr>
                                <th class="px-6 py-3">{{ __('Date') }}</th>
                                <th class="px-6 py-3">{{ __('Actor') }}</th>
                                <th class="px-6 py-3">{{ __('Action') }}</th>
                                <th class="px-6 py-3">{{ __('Merchant') }}</th>
                                <th class="px-6 py-3">{{ __('Target') }}</th>
                                <th class="px-6 py-3">{{ __('Description') }}</th>
                                <th class="px-6 py-3">{{ __('IP address') }}</th>
                                <th class="px-6 py-3 text-end">{{ __('Details') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($logs as $log)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-zinc-600 dark:text-zinc-300">{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                                    <td class="px-6 py-4 text-zinc-900 dark:text-zinc-100">{{ $log->actorLabel() }}</td>
                                    <td class="px-6 py-4 font-medium text-zinc-900 dark:text-zinc-100">{{ $log->action }}</td>
                                    <td class="px-6 py-4 text-zinc-600 dark:text-zinc-300">{{ $log->merchantLabel() }}</td>
                                    <td class="px-6 py-4 text-zinc-600 dark:text-zinc-300">{{ $log->targetLabel() }}</td>
                                    <td class="px-6 py-4 text-zinc-600 dark:text-zinc-300">{{ $log->description }}</td>
                                    <td class="px-6 py-4 text-zinc-600 dark:text-zinc-300">{{ $log->ip_address ?: '—' }}</td>
                                    <td class="px-6 py-4 text-end">
                                        <flux:button variant="ghost" size="sm" :href="route('admin.audit-logs.show', $log)" wire:navigate>
                                            {{ __('View') }}
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($logs->hasPages())
            <div class="rounded-2xl border border-zinc-200 bg-white px-6 py-3.5 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
@endsection
