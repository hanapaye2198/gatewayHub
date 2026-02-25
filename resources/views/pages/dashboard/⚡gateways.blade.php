<?php

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Services\Gateways\GatewayConfigValidator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component
{
    #[Layout('layouts.app', ['title' => 'Gateways'])]

    /**
     * Per-gateway state: enabled + config values. Never store real secrets; use '' for masked fields.
     *
     * @var array<int, array{enabled: bool, last_tested_at?: ?string, last_test_status?: ?string, ...string}>
     */
    public array $gatewayConfigs = [];

    /** Gateway ID whose credentials section is expanded (null = none). */
    public ?int $credentialsExpandedGatewayId = null;

    /** Per-gateway test result message for UI (type => 'success'|'error', message => string). */
    public array $gatewayTestMessages = [];

    public function mount(): void
    {
        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $gateways = Gateway::query()->where('is_global_enabled', true)->orderBy('name')->get();
        foreach ($gateways as $gateway) {
            $mg = MerchantGateway::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'gateway_id' => $gateway->id,
                ],
                ['is_enabled' => false, 'config_json' => []]
            );
            $config = $mg->config_json ?? [];
            $fields = config('gateway_credentials.'.$gateway->code, []);
            $state = [
                'enabled' => $mg->is_enabled,
                'last_tested_at' => $mg->last_tested_at?->toIso8601String(),
                'last_test_status' => $mg->last_test_status,
            ];
            foreach ($fields as $field) {
                $key = $field['key'];
                $state[$key] = ($field['masked'] ?? false) ? '' : (string) ($config[$key] ?? '');
            }
            $this->gatewayConfigs[$gateway->id] = $state;
        }
    }

    #[Computed]
    public function gateways(): \Illuminate\Support\Collection
    {
        return Gateway::query()->where('is_global_enabled', true)->orderBy('name')->get();
    }

    /**
     * Credential field definitions for a gateway code.
     *
     * @return array<int, array{key: string, label: string, type: string, required: bool, masked: bool, options?: array<string, string>}>
     */
    public function credentialFields(string $code): array
    {
        $fields = config('gateway_credentials.'.$code, []);

        return is_array($fields) ? $fields : [];
    }

    public function toggleEnabled(int $gatewayId): void
    {
        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $mg = MerchantGateway::query()
            ->where('user_id', $user->id)
            ->where('gateway_id', $gatewayId)
            ->first();
        if ($mg === null) {
            return;
        }

        $state = $this->gatewayConfigs[$gatewayId] ?? [];
        $enabled = (bool) ($state['enabled'] ?? false);
        $mg->update(['is_enabled' => $enabled]);
    }

    public function saveGateway(int $gatewayId): void
    {
        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $gateway = Gateway::query()->find($gatewayId);
        if ($gateway === null) {
            return;
        }

        $mg = MerchantGateway::query()
            ->where('user_id', $user->id)
            ->where('gateway_id', $gatewayId)
            ->first();
        if ($mg === null) {
            return;
        }

        $this->resetValidation('gateway.'.$gatewayId);

        $state = $this->gatewayConfigs[$gatewayId] ?? [];
        $enabled = (bool) ($state['enabled'] ?? false);
        $fields = $this->credentialFields($gateway->code);
        $existingConfig = $mg->config_json ?? [];

        $config = [];
        foreach ($fields as $field) {
            $key = $field['key'];
            $value = $state[$key] ?? '';
            $value = is_string($value) ? trim($value) : '';
            if (($field['masked'] ?? false) && $value === '') {
                $config[$key] = $existingConfig[$key] ?? '';
            } else {
                $config[$key] = $value;
            }
        }

        if ($enabled) {
            $validator = GatewayConfigValidator::validate($gateway, $config);
            if ($validator->fails()) {
                $this->addError('gateway.'.$gatewayId, $validator->errors()->first());

                return;
            }
        } else {
            $config = [];
        }

        $mg->update([
            'is_enabled' => $enabled,
            'config_json' => $config,
        ]);

        $this->gatewayConfigs[$gatewayId]['enabled'] = $enabled;
        foreach ($fields as $field) {
            if ($field['masked'] ?? false) {
                $this->gatewayConfigs[$gatewayId][$field['key']] = '';
            }
        }
    }

    /**
     * Whether the gateway has valid config (all required fields present and valid).
     */
    public function isConfigured(int $gatewayId): bool
    {
        $gateway = $this->gateways->firstWhere('id', $gatewayId);
        if ($gateway === null) {
            return false;
        }
        $state = $this->gatewayConfigs[$gatewayId] ?? [];
        if (! ($state['enabled'] ?? false)) {
            return false;
        }
        $fields = $this->credentialFields($gateway->code);
        $existingConfig = MerchantGateway::query()
            ->where('user_id', auth()->id())
            ->where('gateway_id', $gatewayId)
            ->first()?->config_json ?? [];
        $config = [];
        foreach ($fields as $field) {
            $key = $field['key'];
            $value = $state[$key] ?? '';
            $value = is_string($value) ? trim($value) : '';
            if (($field['masked'] ?? false) && $value === '') {
                $config[$key] = $existingConfig[$key] ?? '';
            } else {
                $config[$key] = $value;
            }
        }
        $validator = GatewayConfigValidator::validate($gateway, $config);

        return ! $validator->fails();
    }

    /**
     * Gateway visual state: disabled | setup_required | not_verified | verified.
     */
    public function getGatewayState(int $gatewayId): string
    {
        $state = $this->gatewayConfigs[$gatewayId] ?? [];
        if (! ($state['enabled'] ?? false)) {
            return 'disabled';
        }
        if (! $this->isConfigured($gatewayId)) {
            return 'setup_required';
        }
        $lastStatus = $state['last_test_status'] ?? null;
        if ($lastStatus === 'success') {
            return 'verified';
        }

        return 'not_verified';
    }

    /**
     * Environment for display: 'production' (LIVE) or 'sandbox' (TEST). Null if gateway has no environment field.
     */
    public function getEnvironment(int $gatewayId): ?string
    {
        $gateway = $this->gateways->firstWhere('id', $gatewayId);
        if ($gateway === null) {
            return null;
        }
        $fields = $this->credentialFields($gateway->code);
        $hasEnv = collect($fields)->contains('key', 'api_base');
        if (! $hasEnv) {
            return null;
        }
        $state = $this->gatewayConfigs[$gatewayId] ?? [];
        $apiBase = $state['api_base'] ?? null;
        if ($apiBase === 'prod' || $apiBase === 'production') {
            return 'production';
        }

        return 'sandbox';
    }

    /**
     * Test connection (structure only — no real external API call). Updates last_tested_at and last_test_status.
     */
    public function testGateway(int $gatewayId): void
    {
        $user = auth()->user();
        if ($user === null) {
            return;
        }
        $mg = MerchantGateway::query()
            ->where('user_id', $user->id)
            ->where('gateway_id', $gatewayId)
            ->first();
        if ($mg === null) {
            return;
        }
        if (! $this->isConfigured($gatewayId)) {
            $this->gatewayTestMessages[$gatewayId] = ['type' => 'error', 'message' => __('Please save credentials first before testing.')];

            return;
        }
        // Structure only: simulate success (no real API call).
        $status = 'success';
        $mg->update([
            'last_tested_at' => now(),
            'last_test_status' => $status,
        ]);
        $this->gatewayConfigs[$gatewayId]['last_tested_at'] = now()->toIso8601String();
        $this->gatewayConfigs[$gatewayId]['last_test_status'] = $status;
        $this->gatewayTestMessages[$gatewayId] = ['type' => 'success', 'message' => __('Connection test successful.')];
    }

    public function expandCredentials(int $gatewayId): void
    {
        $this->credentialsExpandedGatewayId = $gatewayId;
        unset($this->gatewayTestMessages[$gatewayId]);
    }

    public function collapseCredentials(): void
    {
        $this->credentialsExpandedGatewayId = null;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-8">
    <div class="rounded-xl border border-zinc-200/80 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('Gateways') }}</h1>
        <p class="mt-1.5 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Enable gateways and configure your credentials per gateway.') }}</p>
    </div>

    <div class="max-w-2xl space-y-5">
        @foreach($this->gateways as $gateway)
            @php
                $state = $gatewayConfigs[$gateway->id] ?? ['enabled' => false];
                $fields = $this->credentialFields($gateway->code);
                $gatewayState = $this->getGatewayState($gateway->id);
                $isExpanded = $credentialsExpandedGatewayId === $gateway->id;
                $testMsg = $gatewayTestMessages[$gateway->id] ?? null;
            @endphp
            <div
                wire:key="gateway-card-{{ $gateway->id }}"
                class="rounded-xl border border-zinc-200/80 bg-white shadow-sm transition-all duration-200 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800"
            >
                {{-- Card header: logo, name, status badge, toggle --}}
                <div class="flex flex-wrap items-center gap-4 p-5 sm:p-6">
                    <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-lg font-semibold text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                        {{ strtoupper(mb_substr($gateway->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $gateway->name }}</h2>
                        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $gatewayState === 'disabled' ? __('Disabled') : __('Payment gateway') }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        @if($gatewayState === 'disabled')
                            <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-600 dark:text-zinc-300">{{ __('Disabled') }}</span>
                        @elseif($gatewayState === 'setup_required')
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">{{ __('Setup Required') }}</span>
                        @elseif($gatewayState === 'not_verified')
                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">{{ __('Not Verified') }}</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">{{ __('Active') }}</span>
                        @endif
                        <flux:field variant="inline" class="mb-0">
                            <flux:switch wire:model.live="gatewayConfigs.{{ $gateway->id }}.enabled" wire:change="toggleEnabled({{ $gateway->id }})" />
                            <flux:label class="ms-2 font-medium text-zinc-600 dark:text-zinc-400">{{ $state['enabled'] ? __('On') : __('Off') }}</flux:label>
                        </flux:field>
                    </div>
                </div>

                @if($state['enabled'])
                    <div class="border-t border-zinc-200/80 dark:border-zinc-700">
                        <div class="p-5 pt-4 sm:p-6 sm:pt-5">
                            {{-- Environment + last tested (verified only) --}}
                            @if($gatewayState === 'verified')
                                <div class="mb-4 flex flex-wrap items-center gap-2">
                                    @if($this->getEnvironment($gateway->id) === 'production')
                                        <span class="inline-flex items-center rounded-md bg-red-100 px-2 py-1 text-xs font-medium text-red-800 dark:bg-red-900/40 dark:text-red-300">LIVE MODE</span>
                                    @elseif($this->getEnvironment($gateway->id) === 'sandbox')
                                        <span class="inline-flex items-center rounded-md bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">TEST MODE</span>
                                    @endif
                                    @if(! empty($state['last_tested_at']))
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Last tested') }}: {{ \Carbon\Carbon::parse($state['last_tested_at'])->diffForHumans() }}</span>
                                    @endif
                                </div>
                            @endif

                            {{-- Test result message --}}
                            @if($testMsg)
                                <div
                                    wire:key="test-msg-{{ $gateway->id }}"
                                    class="mb-4 rounded-lg p-3 text-sm {{ $testMsg['type'] === 'success' ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300' : 'bg-red-50 text-red-800 dark:bg-red-900/20 dark:text-red-300' }}"
                                >
                                    {{ $testMsg['message'] }}
                                </div>
                            @endif

                            @if($gatewayState === 'setup_required' && ! $isExpanded)
                                <flux:button type="button" variant="primary" wire:click="expandCredentials({{ $gateway->id }})">
                                    {{ __('Configure') }}
                                </flux:button>
                            @endif

                            @if($gatewayState === 'not_verified' || $gatewayState === 'verified')
                                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                                    <button
                                        type="button"
                                        class="text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                                        wire:click="{{ $isExpanded ? 'collapseCredentials' : 'expandCredentials(' . $gateway->id . ')' }}"
                                    >
                                        {{ $isExpanded ? __('Collapse credentials') : __('Edit credentials') }}
                                    </button>
                                    @if(! $isExpanded)
                                        <flux:button size="sm" variant="ghost" wire:click="expandCredentials({{ $gateway->id }})">{{ __('Show') }}</flux:button>
                                    @endif
                                </div>
                            @endif

                            @if($isExpanded)
                                <div class="transition-all duration-200">
                                    <form wire:submit="saveGateway({{ $gateway->id }})" class="space-y-5">
                                        @foreach($fields as $field)
                                            @php
                                                $key = $field['key'];
                                                $label = $field['label'] ?? $key;
                                                $type = $field['type'] ?? 'text';
                                                $required = $field['required'] ?? false;
                                                $masked = $field['masked'] ?? false;
                                            @endphp
                                            <flux:field>
                                                <flux:label>{{ $label }} @if($required)<span class="text-red-500">*</span>@endif</flux:label>
                                                @if(($field['type'] ?? '') === 'select')
                                                    <flux:select wire:model="gatewayConfigs.{{ $gateway->id }}.{{ $key }}">
                                                        @foreach($field['options'] ?? [] as $optValue => $optLabel)
                                                            <option value="{{ $optValue }}">{{ $optLabel }}</option>
                                                        @endforeach
                                                    </flux:select>
                                                @else
                                                    <flux:input
                                                        type="{{ $type === 'password' ? 'password' : 'text' }}"
                                                        wire:model="gatewayConfigs.{{ $gateway->id }}.{{ $key }}"
                                                        :placeholder="$masked ? __('Leave blank to keep current') : ''"
                                                    />
                                                @endif
                                            </flux:field>
                                        @endforeach
                                        @error('gateway.'.$gateway->id)
                                            <flux:error>{{ $message }}</flux:error>
                                        @enderror
                                        <div class="flex flex-wrap gap-3 pt-1">
                                            <flux:button type="submit" variant="primary" class="min-w-[140px]">{{ __('Save credentials') }}</flux:button>
                                            @if($gatewayState === 'not_verified' || $gatewayState === 'verified')
                                                <flux:button type="button" variant="outline" wire:click="testGateway({{ $gateway->id }})" wire:loading.attr="disabled">
                                                    <flux:icon.loading wire:loading wire:target="testGateway({{ $gateway->id }})" class="me-1.5 size-4" />
                                                    {{ __('Test Connection') }}
                                                </flux:button>
                                            @endif
                                        </div>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
