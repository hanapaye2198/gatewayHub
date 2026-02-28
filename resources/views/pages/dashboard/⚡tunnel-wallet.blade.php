<?php

use App\Models\MerchantWalletSetting;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component {
    #[Layout('layouts.admin-livewire', ['title' => 'SurePay Settlement Dashboard'])]

    public string $displayCurrency = 'PHP';

    public string $logsSearch = '';

    public string $logsChannel = 'all';

    public int $logsPage = 1;

    public int $logsPerPage = 10;

    public function mount(): void
    {
        if (! config('surepay.features.wallet_settlement', false)) {
            abort(404);
        }

        $user = auth()->user();
        if ($user === null || $user->role !== 'admin') {
            abort(403);
        }
    }

    #[Computed]
    public function tunnelWalletBalanceTotal(): float
    {
        return (float) Wallet::query()
            ->where('wallet_type', Wallet::TYPE_MERCHANT_CLEARING)
            ->where('currency', $this->displayCurrency)
            ->whereHas('user', fn ($query) => $query->where('role', 'merchant'))
            ->sum('balance');
    }

    #[Computed]
    public function realWalletBalanceTotal(): float
    {
        return (float) Wallet::query()
            ->where('wallet_type', Wallet::TYPE_MERCHANT_REAL)
            ->where('currency', $this->displayCurrency)
            ->whereHas('user', fn ($query) => $query->where('role', 'merchant'))
            ->sum('balance');
    }

    #[Computed]
    public function todayGrossCollected(): float
    {
        $today = CarbonImmutable::now()->startOfDay();

        return (float) WalletTransaction::query()
            ->where('entry_type', WalletTransaction::ENTRY_PAYMENT_RECEIVED_GROSS)
            ->where('created_at', '>=', $today)
            ->whereHas('payment.user', fn ($query) => $query->where('role', 'merchant'))
            ->sum('amount');
    }

    #[Computed]
    public function todayNetSettled(): float
    {
        $today = CarbonImmutable::now()->startOfDay();

        return (float) WalletTransaction::query()
            ->whereIn('entry_type', [
                WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT,
                WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT_DIRECT,
            ])
            ->where('created_at', '>=', $today)
            ->whereHas('payment.user', fn ($query) => $query->where('role', 'merchant'))
            ->sum('amount');
    }

    #[Computed]
    public function pendingNetSettlementCount(): int
    {
        return WalletTransaction::query()
            ->where('entry_type', WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE)
            ->where('is_settled', false)
            ->whereHas('payment.user', fn ($query) => $query->where('role', 'merchant'))
            ->count();
    }

    #[Computed]
    public function tunnelFailureCount(): int
    {
        return WalletTransaction::query()
            ->where('entry_type', WalletTransaction::ENTRY_TUNNEL_FAILURE)
            ->whereHas('payment.user', fn ($query) => $query->where('role', 'merchant'))
            ->count();
    }

    #[Computed]
    public function configuredMerchantsCount(): int
    {
        return MerchantWalletSetting::query()
            ->whereHas('user', fn ($query) => $query->where('role', 'merchant'))
            ->count();
    }

    #[Computed]
    public function tunnelEnabledMerchantsCount(): int
    {
        return MerchantWalletSetting::query()
            ->where('tunnel_wallet_enabled', true)
            ->whereHas('user', fn ($query) => $query->where('role', 'merchant'))
            ->count();
    }

    #[Computed]
    public function merchantTunnelWalletData()
    {
        $today = CarbonImmutable::now()->startOfDay();

        $merchants = User::query()
            ->where('role', 'merchant')
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        $tunnelBalances = Wallet::query()
            ->where('wallet_type', Wallet::TYPE_MERCHANT_CLEARING)
            ->where('currency', $this->displayCurrency)
            ->whereIn('user_id', $merchants->pluck('id'))
            ->selectRaw('user_id, SUM(balance) as total_balance')
            ->groupBy('user_id')
            ->pluck('total_balance', 'user_id');

        $realBalances = Wallet::query()
            ->where('wallet_type', Wallet::TYPE_MERCHANT_REAL)
            ->where('currency', $this->displayCurrency)
            ->whereIn('user_id', $merchants->pluck('id'))
            ->selectRaw('user_id, SUM(balance) as total_balance')
            ->groupBy('user_id')
            ->pluck('total_balance', 'user_id');

        $pendingNetAmounts = WalletTransaction::query()
            ->join('payments', 'payments.id', '=', 'wallet_transactions.payment_id')
            ->where('wallet_transactions.entry_type', WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE)
            ->where('wallet_transactions.is_settled', false)
            ->whereIn('payments.user_id', $merchants->pluck('id'))
            ->selectRaw('payments.user_id as user_id, SUM(wallet_transactions.amount) as total_amount')
            ->groupBy('payments.user_id')
            ->pluck('total_amount', 'user_id');

        $todayGrossAmounts = WalletTransaction::query()
            ->join('payments', 'payments.id', '=', 'wallet_transactions.payment_id')
            ->where('wallet_transactions.entry_type', WalletTransaction::ENTRY_PAYMENT_RECEIVED_GROSS)
            ->where('wallet_transactions.created_at', '>=', $today)
            ->whereIn('payments.user_id', $merchants->pluck('id'))
            ->selectRaw('payments.user_id as user_id, SUM(wallet_transactions.amount) as total_amount')
            ->groupBy('payments.user_id')
            ->pluck('total_amount', 'user_id');

        $todayNetAmounts = WalletTransaction::query()
            ->join('payments', 'payments.id', '=', 'wallet_transactions.payment_id')
            ->whereIn('wallet_transactions.entry_type', [
                WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT,
                WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT_DIRECT,
            ])
            ->where('wallet_transactions.created_at', '>=', $today)
            ->whereIn('payments.user_id', $merchants->pluck('id'))
            ->selectRaw('payments.user_id as user_id, SUM(wallet_transactions.amount) as total_amount')
            ->groupBy('payments.user_id')
            ->pluck('total_amount', 'user_id');

        return $merchants->map(function (User $merchant) use (
            $tunnelBalances,
            $realBalances,
            $pendingNetAmounts,
            $todayGrossAmounts,
            $todayNetAmounts
        ): array {
            $merchantId = $merchant->id;

            return [
                'merchant' => $merchant->name,
                'tunnel_balance' => round((float) ($tunnelBalances[$merchantId] ?? 0), 2),
                'real_balance' => round((float) ($realBalances[$merchantId] ?? 0), 2),
                'pending_net_amount' => round((float) ($pendingNetAmounts[$merchantId] ?? 0), 2),
                'today_gross' => round((float) ($todayGrossAmounts[$merchantId] ?? 0), 2),
                'today_net' => round((float) ($todayNetAmounts[$merchantId] ?? 0), 2),
            ];
        })->values();
    }

    #[Computed]
    public function recentTunnelEntries()
    {
        return WalletTransaction::query()
            ->whereIn('entry_type', [
                WalletTransaction::ENTRY_PAYMENT_RECEIVED_GROSS,
                WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE,
                WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT,
                WalletTransaction::ENTRY_TUNNEL_FAILURE,
            ])
            ->whereHas('payment.user', fn ($query) => $query->where('role', 'merchant'))
            ->with(['payment.user'])
            ->latest('created_at')
            ->limit(15)
            ->get();
    }

    #[Computed]
    public function flowLogData(): array
    {
        $channels = [
            'user_to_surepay_wallet' => [],
        ];

        $payments = \App\Models\Payment::query()
            ->whereHas('user', fn ($query) => $query->where('role', 'merchant'))
            ->whereNotNull('raw_response')
            ->with('user:id,name')
            ->latest('updated_at')
            ->limit(150)
            ->get(['id', 'user_id', 'reference_id', 'raw_response', 'updated_at']);

        foreach ($payments as $payment) {
            $raw = $payment->raw_response;
            if (! is_array($raw)) {
                continue;
            }

            $flowLogs = $raw['flow_logs'] ?? [];
            if (is_array($flowLogs)) {
                foreach ($flowLogs as $sourceChannel => $entries) {
                    if (! is_array($entries)) {
                        continue;
                    }

                    foreach ($entries as $entry) {
                        if (! is_array($entry)) {
                            continue;
                        }

                        $channels['user_to_surepay_wallet'][] = [
                            'merchant' => $payment->user?->name ?? 'N/A',
                            'reference' => $payment->reference_id,
                            'status' => (string) ($entry['status'] ?? 'info'),
                            'stage' => (string) ($entry['stage'] ?? ''),
                            'amount' => $entry['amount'] ?? null,
                            'currency' => $entry['currency'] ?? null,
                            'logged_at' => (string) ($entry['logged_at'] ?? ''),
                            'message' => (string) ($entry['message'] ?? ''),
                            'source_channel' => (string) ($entry['source_channel'] ?? $sourceChannel),
                        ];
                    }
                }
            }

            $flowErrors = $raw['flow_errors'] ?? [];
            if (! is_array($flowErrors)) {
                continue;
            }

            foreach ($flowErrors as $sourceChannel => $errors) {
                if (! is_array($errors)) {
                    continue;
                }

                foreach ($errors as $error) {
                    if (! is_array($error)) {
                        continue;
                    }

                    $channels['user_to_surepay_wallet'][] = [
                        'merchant' => $payment->user?->name ?? 'N/A',
                        'reference' => $payment->reference_id,
                        'status' => 'failed',
                        'stage' => 'error',
                        'amount' => null,
                        'currency' => null,
                        'logged_at' => (string) ($error['logged_at'] ?? ''),
                        'message' => (string) ($error['message'] ?? 'Flow error'),
                        'source_channel' => (string) ($error['source_channel'] ?? $sourceChannel),
                    ];
                }
            }
        }

        foreach (array_keys($channels) as $channel) {
            usort($channels[$channel], function (array $a, array $b): int {
                return strcmp((string) ($b['logged_at'] ?? ''), (string) ($a['logged_at'] ?? ''));
            });

            $channels[$channel] = array_slice($channels[$channel], 0, 15);
        }

        return $channels;
    }

    #[Computed]
    public function flowLogRows(): array
    {
        $labels = [
            'user_to_surepay_wallet' => 'User to SurePay Collection Flow',
        ];

        $rows = [];
        foreach ($this->flowLogData as $channel => $entries) {
            foreach ($entries as $entry) {
                $rows[] = array_merge($entry, [
                    'channel' => $channel,
                    'channel_label' => $labels[$channel] ?? $channel,
                ]);
            }
        }

        usort($rows, function (array $a, array $b): int {
            return strcmp((string) ($b['logged_at'] ?? ''), (string) ($a['logged_at'] ?? ''));
        });

        return $rows;
    }

    #[Computed]
    public function filteredFlowLogRows(): array
    {
        $rows = $this->flowLogRows;

        if ($this->logsChannel !== 'all') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => ($row['channel'] ?? '') === $this->logsChannel));
        }

        $search = trim($this->logsSearch);
        if ($search === '') {
            return $rows;
        }

        return array_values(array_filter($rows, function (array $row) use ($search): bool {
            $haystack = implode(' | ', [
                (string) ($row['merchant'] ?? ''),
                (string) ($row['reference'] ?? ''),
                (string) ($row['status'] ?? ''),
                (string) ($row['stage'] ?? ''),
                (string) ($row['message'] ?? ''),
                (string) ($row['channel_label'] ?? ''),
                (string) ($row['source_channel'] ?? ''),
            ]);

            return Str::contains(Str::lower($haystack), Str::lower($search));
        }));
    }

    #[Computed]
    public function paginatedFlowLogRows(): array
    {
        $rows = $this->filteredFlowLogRows;
        $total = count($rows);
        $perPage = max(1, $this->logsPerPage);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min(max(1, $this->logsPage), $lastPage);

        $offset = ($currentPage - 1) * $perPage;
        $items = array_slice($rows, $offset, $perPage);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $currentPage,
            'last_page' => $lastPage,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => $total > 0 ? min($offset + $perPage, $total) : 0,
        ];
    }

    public function updatedLogsSearch(): void
    {
        $this->logsPage = 1;
    }

    public function updatedLogsChannel(): void
    {
        $this->logsPage = 1;
    }

    public function previousLogsPage(): void
    {
        $this->logsPage = max(1, $this->logsPage - 1);
    }

    public function nextLogsPage(): void
    {
        $this->logsPage++;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('SurePay Settlement Dashboard') }}</h1>
                <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Live admin overview of merchant clearing balances and settlement activity.') }}</p>
            </div>
            <flux:button variant="primary" :href="route('admin.surepay-wallets.index')" wire:navigate>
                {{ __('Manage Merchant Configurations') }}
            </flux:button>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Today Gross Collected') }}</p>
            <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($this->todayGrossCollected, 2) }} {{ $displayCurrency }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Today Net Settled') }}</p>
            <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($this->todayNetSettled, 2) }} {{ $displayCurrency }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Pending Net Settlements') }}</p>
            <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($this->pendingNetSettlementCount) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Settlement Processing Failures') }}</p>
            <p class="mt-2 font-mono text-2xl font-semibold tabular-nums {{ $this->tunnelFailureCount > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100' }}">{{ number_format($this->tunnelFailureCount) }}</p>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Clearing Balance') }}</p>
            <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($this->tunnelWalletBalanceTotal, 2) }} {{ $displayCurrency }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Merchant Net Balance') }}</p>
            <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($this->realWalletBalanceTotal, 2) }} {{ $displayCurrency }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Configured Merchants') }}</p>
            <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($this->configuredMerchantsCount) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Flow-Enabled Merchants') }}</p>
            <p class="mt-2 font-mono text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($this->tunnelEnabledMerchantsCount) }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 [&_tbody_tr:hover]:bg-zinc-50 dark:[&_tbody_tr:hover]:bg-zinc-700/50 [&_tbody_tr]:border-b [&_tbody_tr]:border-zinc-200/60 [&_tbody_tr:last-child]:border-b-0 dark:[&_tbody_tr]:border-zinc-600/40">
        <h2 class="mb-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('By Merchant Settlement Data') }}</h2>
        <flux:table>
            <flux:table.columns>
                <flux:table.cell variant="strong">{{ __('Merchant') }}</flux:table.cell>
                <flux:table.cell variant="strong" align="end">{{ __('Clearing Balance') }}</flux:table.cell>
                <flux:table.cell variant="strong" align="end">{{ __('Merchant Net Balance') }}</flux:table.cell>
                <flux:table.cell variant="strong" align="end">{{ __('Pending Net') }}</flux:table.cell>
                <flux:table.cell variant="strong" align="end">{{ __('Today Gross') }}</flux:table.cell>
                <flux:table.cell variant="strong" align="end">{{ __('Today Net') }}</flux:table.cell>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->merchantTunnelWalletData as $row)
                    <flux:table.row>
                        <flux:table.cell>{{ $row['merchant'] }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ number_format($row['tunnel_balance'], 2) }} {{ $displayCurrency }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ number_format($row['real_balance'], 2) }} {{ $displayCurrency }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ number_format($row['pending_net_amount'], 2) }} {{ $displayCurrency }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ number_format($row['today_gross'], 2) }} {{ $displayCurrency }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ number_format($row['today_net'], 2) }} {{ $displayCurrency }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500 dark:text-zinc-400">{{ __('No merchant settlement data yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 [&_tbody_tr:hover]:bg-zinc-50 dark:[&_tbody_tr:hover]:bg-zinc-700/50 [&_tbody_tr]:border-b [&_tbody_tr]:border-zinc-200/60 [&_tbody_tr:last-child]:border-b-0 dark:[&_tbody_tr]:border-zinc-600/40">
        <h2 class="mb-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Recent Settlement Entries') }}</h2>
        @if ($this->recentTunnelEntries->isEmpty())
            <p class="mb-3 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No settlement entries yet.') }}</p>
        @endif
        <flux:table>
            <flux:table.columns>
                <flux:table.cell variant="strong">{{ __('Merchant') }}</flux:table.cell>
                <flux:table.cell variant="strong">{{ __('Reference') }}</flux:table.cell>
                <flux:table.cell variant="strong">{{ __('Entry Type') }}</flux:table.cell>
                <flux:table.cell variant="strong" align="end">{{ __('Amount') }}</flux:table.cell>
                <flux:table.cell variant="strong">{{ __('Date') }}</flux:table.cell>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->recentTunnelEntries as $entry)
                    <flux:table.row>
                        <flux:table.cell>{{ $entry->payment?->user?->name ?? 'N/A' }}</flux:table.cell>
                        <flux:table.cell>{{ $entry->payment?->reference_id ?? 'N/A' }}</flux:table.cell>
                        <flux:table.cell>{{ $entry->entry_type }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono tabular-nums">{{ number_format($entry->amount, 2) }} {{ $entry->currency }}</flux:table.cell>
                        <flux:table.cell>{{ $entry->created_at->format('M j, Y g:i A') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500 dark:text-zinc-400">{{ __('No settlement entries yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Flow Logs') }}</h3>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('All merchant logs are treated as user-to-SurePay collection flow events.') }}</p>
            </div>
            <div class="grid gap-2 sm:grid-cols-2">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="logsSearch"
                    placeholder="{{ __('Search merchant, reference, status, stage, or message') }}"
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100"
                />
                <select
                    wire:model.live="logsChannel"
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100"
                >
                    <option value="all">{{ __('All channels') }}</option>
                    <option value="user_to_surepay_wallet">{{ __('User to SurePay Collection Flow') }}</option>
                </select>
            </div>
        </div>

        @php $page = $this->paginatedFlowLogRows; @endphp

        @if (empty($page['items']))
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No logs yet.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-[920px] w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-900/30">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Channel') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Merchant') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Reference') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Status / Stage') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Message') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Logged At') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($page['items'] as $row)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                                <td class="px-3 py-2 text-xs text-zinc-700 dark:text-zinc-300">{{ $row['channel_label'] }}</td>
                                <td class="px-3 py-2 text-xs text-zinc-700 dark:text-zinc-300">{{ $row['merchant'] }}</td>
                                <td class="px-3 py-2 text-xs text-zinc-700 dark:text-zinc-300">{{ $row['reference'] }}</td>
                                <td class="px-3 py-2 text-xs text-zinc-700 dark:text-zinc-300">
                                    <span class="uppercase">{{ $row['status'] }}</span>
                                    @if (($row['stage'] ?? '') !== '')
                                        · {{ $row['stage'] }}
                                    @endif
                                    @if ($row['amount'] !== null && $row['currency'] !== null)
                                        · {{ number_format((float) $row['amount'], 2) }} {{ $row['currency'] }}
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs text-zinc-700 dark:text-zinc-300">{{ $row['message'] ?: '—' }}</td>
                                <td class="px-3 py-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $row['logged_at'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                <p>{{ __('Showing :from-:to of :total', ['from' => $page['from'], 'to' => $page['to'], 'total' => $page['total']]) }}</p>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="previousLogsPage"
                        @disabled($page['page'] <= 1)
                        class="inline-flex h-8 items-center justify-center rounded-md border border-zinc-300 px-3 text-xs font-medium text-zinc-700 hover:bg-zinc-100 disabled:opacity-50 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700"
                    >
                        {{ __('Previous') }}
                    </button>
                    <span>{{ __('Page :page of :last', ['page' => $page['page'], 'last' => $page['last_page']]) }}</span>
                    <button
                        type="button"
                        wire:click="nextLogsPage"
                        @disabled($page['page'] >= $page['last_page'])
                        class="inline-flex h-8 items-center justify-center rounded-md border border-zinc-300 px-3 text-xs font-medium text-zinc-700 hover:bg-zinc-100 disabled:opacity-50 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700"
                    >
                        {{ __('Next') }}
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>



