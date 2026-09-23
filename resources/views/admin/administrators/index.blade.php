@extends('layouts.admin')

@section('content')
    <div class="flex h-full w-full flex-1 flex-col gap-6 px-4 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-zinc-200 bg-white px-8 py-7 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Administrators') }}</h1>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Platform administrators can manage merchants and operational data. They cannot change the platform fee or other administrator accounts.') }}
                    </p>
                </div>
                <flux:button variant="primary" icon="plus" :href="route('admin.administrators.create')" wire:navigate>
                    {{ __('Create administrator') }}
                </flux:button>
            </div>

            @if (session('status'))
                <flux:callout variant="success" icon="check-circle" class="mt-5">
                    {{ session('status') }}
                </flux:callout>
            @endif
        </div>

        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
            @if ($users->isEmpty())
                <p class="px-6 py-10 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No platform administrators yet.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                        <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:bg-zinc-950/40 dark:text-zinc-400">
                            <tr>
                                <th class="px-6 py-3">{{ __('Name') }}</th>
                                <th class="px-6 py-3">{{ __('Email') }}</th>
                                <th class="px-6 py-3">{{ __('Role') }}</th>
                                <th class="px-6 py-3">{{ __('Status') }}</th>
                                <th class="px-6 py-3">{{ __('Created') }}</th>
                                <th class="px-6 py-3 text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($users as $user)
                                <tr>
                                    <td class="px-6 py-4 font-medium text-zinc-900 dark:text-zinc-100">{{ $user->name }}</td>
                                    <td class="px-6 py-4 text-zinc-600 dark:text-zinc-300">{{ $user->email }}</td>
                                    <td class="px-6 py-4 text-zinc-600 dark:text-zinc-300">{{ __('Admin') }}</td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $user->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400' : 'bg-red-50 text-red-700 dark:bg-red-950/30 dark:text-red-400' }}">
                                            {{ $user->is_active ? __('Active') : __('Disabled') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-zinc-600 dark:text-zinc-300">{{ $user->created_at?->format('M d, Y') }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <flux:button variant="ghost" size="sm" :href="route('admin.administrators.edit', $user)" wire:navigate>
                                                {{ __('Edit') }}
                                            </flux:button>
                                            <form action="{{ route('admin.administrators.toggle', $user) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                @if ($user->is_active)
                                                    <flux:button type="submit" variant="danger" size="sm">{{ __('Disable') }}</flux:button>
                                                @else
                                                    <flux:button type="submit" variant="primary" size="sm">{{ __('Enable') }}</flux:button>
                                                @endif
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
