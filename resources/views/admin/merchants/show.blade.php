@extends('layouts.admin')

@section('content')
    <div class="flex h-full w-full flex-1 flex-col gap-6 px-4 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-zinc-200 bg-white px-8 py-7 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <flux:button variant="ghost" icon="arrow-left" :href="route('admin.merchants.index')" wire:navigate class="-ms-2">
                        {{ __('Merchants') }}
                    </flux:button>
                    <h1 class="mt-4 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ $merchant->name }}</h1>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $merchant->email }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium {{ $merchant->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400' : 'bg-red-50 text-red-700 dark:bg-red-950/30 dark:text-red-400' }}">
                        {{ $merchant->is_active ? __('Active') : __('Suspended') }}
                    </span>
                    <flux:button variant="ghost" icon="users" :href="route('admin.merchants.users.index', $merchant)" wire:navigate>
                        {{ __('Manage Users') }}
                    </flux:button>
                    <flux:button variant="ghost" :href="route('admin.merchants.edit', $merchant)" wire:navigate>
                        {{ __('Edit') }}
                    </flux:button>
                    <form action="{{ route('admin.merchants.toggle', $merchant) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        @if ($merchant->is_active)
                            <flux:button type="submit" variant="danger">{{ __('Suspend') }}</flux:button>
                        @else
                            <flux:button type="submit" variant="primary">{{ __('Activate') }}</flux:button>
                        @endif
                    </form>
                    <flux:button variant="primary" :href="route('admin.payments.index', ['merchant_id' => $merchant->id])" wire:navigate>
                        {{ __('View payments') }}
                    </flux:button>
                    @if ($merchant->is_active)
                        <form action="{{ route('admin.merchants.access', $merchant) }}" method="POST">
                            @csrf
                            <flux:button type="submit" variant="primary">{{ __('Access Merchant') }}</flux:button>
                        </form>
                    @else
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Access Merchant is available for active merchants only.') }}</p>
                    @endif
                </div>
            </div>

            @if (session('status'))
                <flux:callout variant="success" icon="check-circle" class="mt-5">
                    {{ session('status') }}
                </flux:callout>
            @endif
            @if (session('error'))
                <flux:callout variant="danger" icon="exclamation-triangle" class="mt-5">
                    {{ session('error') }}
                </flux:callout>
            @endif
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Users') }}</p>
                <p class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($merchant->users_count) }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Payments') }}</p>
                <p class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($merchant->payments_count) }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Created') }}</p>
                <p class="mt-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $merchant->created_at?->format('M d, Y') }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('API credentials') }}</p>
                <p class="mt-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                    {{ $apiKeyConfigured ? __('Configured') : __('Not configured') }}
                </p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Profile') }}</h2>
                <dl class="mt-4 grid gap-4 text-sm">
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Display name') }}</dt>
                        <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $merchant->getDisplayName() }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('QR display name') }}</dt>
                        <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $merchant->qr_display_name ?: __('Not set') }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Theme color') }}</dt>
                        <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $merchant->getThemeColor() }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Webhook URL') }}</dt>
                        <dd class="mt-1 break-all text-zinc-900 dark:text-zinc-100">{{ $merchant->webhook_url ?: __('Not set') }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Webhook signing secret') }}</dt>
                        <dd class="mt-1 text-zinc-900 dark:text-zinc-100">
                            {{ $webhookSecretConfigured ? __('Configured') : __('Not configured') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Platform fee') }}</dt>
                        <dd class="mt-1 text-zinc-900 dark:text-zinc-100">
                            {{ $merchantPlatformFeePercentage !== null ? $merchantPlatformFeePercentage.'%' : __('Global default :rate%', ['rate' => $globalPlatformFeePercentage]) }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900">
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Enabled gateways') }}</h2>
                @if ($enabledGateways->isEmpty())
                    <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No gateways are enabled for this merchant.') }}</p>
                @else
                    <ul class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($enabledGateways as $merchantGateway)
                            <li class="flex items-center justify-between py-3 text-sm">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $merchantGateway->gateway?->name ?? __('Gateway') }}</span>
                                <span class="text-zinc-500 dark:text-zinc-400">{{ $merchantGateway->gateway?->code }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if (auth()->user()?->isSuperAdmin())
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900 lg:col-span-2">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Merchant platform fee') }}</h2>
                    <p class="mt-2 max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('Leave this blank to use the global default of :rate%. The fee is calculated from the original transaction amount and added on top.', ['rate' => $globalPlatformFeePercentage]) }}
                    </p>
                    <form method="POST" action="{{ route('admin.merchants.platform-fee.update', $merchant) }}" class="mt-5 flex max-w-xl flex-col gap-5">
                        @csrf
                        @method('PUT')
                        <flux:field>
                            <flux:label for="percentage">{{ __('Override percentage') }}</flux:label>
                            <flux:input
                                id="percentage"
                                name="percentage"
                                type="number"
                                inputmode="decimal"
                                step="0.01"
                                min="0"
                                max="100"
                                :value="old('percentage', $merchantPlatformFeePercentage)"
                            />
                            <flux:error name="percentage" />
                        </flux:field>
                        <div>
                            <flux:button type="submit" variant="primary">{{ __('Save platform fee') }}</flux:button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection
