<?php

namespace App\Livewire\Admin;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class GatewayHub extends Component
{
    public string $search = '';

    public string $statusFilter = 'all';

    public string $gatewayTypeFilter = 'all';

    public string $merchantFilter = 'all';

    /** Search and filter for Merchant Gateway Access table only. */
    public string $merchantAccessSearch = '';

    public string $merchantAccessStatusFilter = 'all';

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    /** @var array<int, string> */
    public array $selectedGateways = [];

    /** @var array<int, string> */
    public array $selectedMerchants = [];

    public bool $selectAll = false;

    public string $bulkAction = '';

    public bool $showSuccessMessage = false;

    public string $successMessage = '';

    public bool $showConfirmation = false;

    public ?string $confirmationAction = null;

    /** @var array{message?: string} */
    public array $confirmationData = [];

    public ?Gateway $editingGateway = null;

    /** @var array<string, string> */
    public array $config = [];

    public bool $showConfigModal = false;

    /** @var array<int, array{action: string, subject: string, details: array, timestamp: \Illuminate\Support\Carbon, user: string}> */
    public array $activityLog = [];

    /** @var array<string, array{except?: string}> */
    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'gatewayTypeFilter' => ['except' => 'all'],
        'merchantFilter' => ['except' => 'all'],
    ];

    public function mount(): void
    {
        $this->loadActivityLog();
    }

    public function updatedSearch(): void
    {
        //
    }

    public function updatedStatusFilter(): void
    {
        //
    }

    public function updatedGatewayTypeFilter(): void
    {
        //
    }

    public function updatedMerchantFilter(): void
    {
        //
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selectedGateways = Gateway::pluck('id')->map(fn ($id) => (string) $id)->all();
        } else {
            $this->selectedGateways = [];
        }
    }

    public function toggleGatewayGlobal(int $gatewayId): void
    {
        $gateway = Gateway::findOrFail($gatewayId);

        DB::transaction(function () use ($gateway): void {
            $oldStatus = $gateway->is_global_enabled;
            $gateway->is_global_enabled = ! $gateway->is_global_enabled;
            $gateway->save();

            $this->logActivity(
                'Global toggle',
                $gateway->name,
                ['old_status' => $oldStatus, 'new_status' => $gateway->is_global_enabled]
            );

            if (! $gateway->is_global_enabled) {
                MerchantGateway::where('gateway_id', $gateway->id)
                    ->update(['is_enabled' => false]);
            }
        });

        $this->showSuccess('Gateway global status updated successfully!');
    }

    public function toggleMerchantGateway(int $gatewayId, int $merchantId): void
    {
        $gateway = Gateway::findOrFail($gatewayId);
        $merchant = User::findOrFail($merchantId);

        if (! $gateway->is_global_enabled) {
            $this->showError('Cannot enable because gateway is globally disabled.');

            return;
        }

        DB::transaction(function () use ($gateway, $merchant): void {
            $merchantGateway = MerchantGateway::firstOrNew([
                'gateway_id' => $gateway->id,
                'user_id' => $merchant->id,
            ]);

            $oldStatus = $merchantGateway->is_enabled ?? false;
            $merchantGateway->is_enabled = ! $oldStatus;
            $merchantGateway->save();

            $this->logActivity(
                'Merchant access toggle',
                $gateway->name,
                [
                    'merchant' => $merchant->name,
                    'old_status' => $oldStatus,
                    'new_status' => $merchantGateway->is_enabled,
                ]
            );
        });

        $this->showSuccess('Merchant gateway access updated!');
    }

    public function updateGatewayConfig(?int $gatewayId = null): void
    {
        $id = $gatewayId ?? $this->editingGateway?->id;
        if (! $id) {
            return;
        }

        $gateway = Gateway::findOrFail($id);
        $fields = config('gateway_credentials.'.$gateway->code, []);
        if (! is_array($fields) || $fields === []) {
            $this->showConfigModal = false;
            $this->editingGateway = null;
            $this->config = [];
            $this->showError('This payment option uses the shared Coins.ph platform configuration.');

            return;
        }

        $existingConfig = is_array($gateway->config_json) ? $gateway->config_json : [];
        $normalizedConfig = $this->normalizeConfig($this->config, $fields, $existingConfig);

        DB::transaction(function () use ($gateway, $normalizedConfig): void {
            $gateway->config_json = array_merge($gateway->config_json ?? [], $normalizedConfig);
            $gateway->save();

            $this->logActivity(
                'Config updated',
                $gateway->name,
                ['updated_fields' => array_keys($normalizedConfig)]
            );
        });

        $this->showConfigModal = false;
        $this->editingGateway = null;
        $this->config = [];
        $this->showSuccess('Gateway configuration updated successfully!');
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $existing
     * @return array<string, string>
     */
    private function normalizeConfig(array $incoming, array $fields, array $existing): array
    {
        $normalized = [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            $key = $field['key'] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }
            $value = $incoming[$key] ?? null;
            $isMasked = (bool) ($field['masked'] ?? false);
            if ($isMasked) {
                if (is_string($value) && trim($value) !== '') {
                    $normalized[$key] = trim($value);
                } else {
                    $normalized[$key] = (string) ($existing[$key] ?? '');
                }

                continue;
            }
            $normalized[$key] = is_string($value) ? trim($value) : (string) ($existing[$key] ?? '');
        }

        return $normalized;
    }

    public function bulkAction(): void
    {
        if ($this->selectedGateways === []) {
            $this->showError('Please select at least one gateway.');

            return;
        }

        match ($this->bulkAction) {
            'enable_global' => $this->confirmBulkAction('Enable selected gateways globally?', 'executeBulkEnableGlobal'),
            'disable_global' => $this->confirmBulkAction('Disable selected gateways globally?', 'executeBulkDisableGlobal'),
            'enable_for_merchants' => $this->handleBulkEnableForMerchants(),
            'disable_for_merchants' => $this->handleBulkDisableForMerchants(),
            default => null,
        };
    }

    private function handleBulkEnableForMerchants(): void
    {
        if ($this->selectedMerchants === []) {
            $this->showError('Please select at least one merchant.');

            return;
        }
        $this->confirmBulkAction('Enable selected gateways for selected merchants?', 'executeBulkEnableForMerchants');
    }

    private function handleBulkDisableForMerchants(): void
    {
        if ($this->selectedMerchants === []) {
            $this->showError('Please select at least one merchant.');

            return;
        }
        $this->confirmBulkAction('Disable selected gateways for selected merchants?', 'executeBulkDisableForMerchants');
    }

    public function executeBulkEnableGlobal(): void
    {
        DB::transaction(function (): void {
            Gateway::whereIn('id', $this->selectedGateways)
                ->update(['is_global_enabled' => true]);

            $this->logActivity('Bulk enable global', (string) count($this->selectedGateways).' gateways');
        });

        $this->resetBulkSelection();
        $this->showSuccess('Selected gateways enabled globally!');
    }

    public function executeBulkDisableGlobal(): void
    {
        DB::transaction(function (): void {
            Gateway::whereIn('id', $this->selectedGateways)
                ->update(['is_global_enabled' => false]);

            MerchantGateway::whereIn('gateway_id', $this->selectedGateways)
                ->update(['is_enabled' => false]);

            $this->logActivity('Bulk disable global', (string) count($this->selectedGateways).' gateways');
        });

        $this->resetBulkSelection();
        $this->showSuccess('Selected gateways disabled globally!');
    }

    public function executeBulkEnableForMerchants(): void
    {
        DB::transaction(function (): void {
            foreach ($this->selectedGateways as $gatewayId) {
                $gateway = Gateway::find($gatewayId);
                if ($gateway && $gateway->is_global_enabled) {
                    foreach ($this->selectedMerchants as $merchantId) {
                        MerchantGateway::updateOrCreate(
                            [
                                'gateway_id' => (int) $gatewayId,
                                'user_id' => (int) $merchantId,
                            ],
                            ['is_enabled' => true]
                        );
                    }
                }
            }

            $this->logActivity(
                'Bulk enable for merchants',
                (string) count($this->selectedGateways).' gateways for '.(string) count($this->selectedMerchants).' merchants'
            );
        });

        $this->resetBulkSelection();
        $this->showSuccess('Gateways enabled for selected merchants!');
    }

    public function executeBulkDisableForMerchants(): void
    {
        DB::transaction(function (): void {
            MerchantGateway::whereIn('gateway_id', $this->selectedGateways)
                ->whereIn('user_id', $this->selectedMerchants)
                ->update(['is_enabled' => false]);

            $this->logActivity(
                'Bulk disable for merchants',
                (string) count($this->selectedGateways).' gateways for '.(string) count($this->selectedMerchants).' merchants'
            );
        });

        $this->resetBulkSelection();
        $this->showSuccess('Gateways disabled for selected merchants!');
    }

    private function confirmBulkAction(string $message, string $action): void
    {
        $this->confirmationAction = $action;
        $this->confirmationData = ['message' => $message];
        $this->showConfirmation = true;
    }

    public function executeConfirmedAction(): void
    {
        if ($this->confirmationAction !== null) {
            $this->{$this->confirmationAction}();
        }
        $this->showConfirmation = false;
        $this->confirmationAction = null;
        $this->confirmationData = [];
    }

    public function resetBulkSelection(): void
    {
        $this->selectedGateways = [];
        $this->selectedMerchants = [];
        $this->selectAll = false;
        $this->bulkAction = '';
    }

    public function editConfig(int $gatewayId): void
    {
        $gateway = Gateway::findOrFail($gatewayId);
        $fields = config('gateway_credentials.'.$gateway->code, []);
        if (! is_array($fields) || $fields === []) {
            $this->showError('This payment option uses the shared Coins.ph platform configuration.');

            return;
        }

        $this->editingGateway = $gateway;
        $this->config = is_array($gateway->config_json) ? $gateway->config_json : [];
        $this->showConfigModal = true;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function logActivity(string $action, string $subject, array $details = []): void
    {
        $log = [
            'action' => $action,
            'subject' => $subject,
            'details' => $details,
            'timestamp' => now(),
            'user' => Auth::user()?->name ?? 'System',
        ];

        array_unshift($this->activityLog, $log);
        $this->activityLog = array_slice($this->activityLog, 0, 50);
        session(['gateway_activity_log' => $this->activityLog]);
    }

    private function loadActivityLog(): void
    {
        $this->activityLog = session('gateway_activity_log', []);
    }

    public function clearActivityLog(): void
    {
        $this->activityLog = [];
        session()->forget('gateway_activity_log');
        $this->showSuccess('Activity log cleared!');
    }

    private function showSuccess(string $message): void
    {
        $this->successMessage = $message;
        $this->showSuccessMessage = true;
        $this->dispatch('hide-success-message');
    }

    private function showError(string $message): void
    {
        $this->dispatch('show-error', message: $message);
    }

    public function render()
    {
        $gateways = Gateway::query()
            ->with(['merchantGateways' => fn ($q) => $q->whereIn('user_id', User::where('role', 'merchant')->pluck('id'))])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($q): void {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('code', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter !== 'all', function ($query): void {
                $query->where('is_global_enabled', $this->statusFilter === 'enabled');
            })
            ->when($this->gatewayTypeFilter !== 'all', function ($query): void {
                $query->where('code', $this->gatewayTypeFilter);
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->get();

        $merchants = User::query()
            ->where('role', 'merchant')
            ->when($this->merchantFilter !== 'all', function ($query): void {
                if ($this->merchantFilter === 'active') {
                    $query->where('is_active', true);
                } elseif ($this->merchantFilter === 'inactive') {
                    $query->where('is_active', false);
                }
            })
            ->when($this->search !== '', function ($query): void {
                $query->where('name', 'like', '%'.$this->search.'%');
            })
            ->orderBy('name')
            ->get();

        $credentialFields = config('gateway_credentials', []);

        $merchantAccessMerchants = $merchants
            ->when($this->merchantAccessSearch !== '', function ($collection) {
                $term = strtolower($this->merchantAccessSearch);

                return $collection->filter(fn ($m) => str_contains(strtolower($m->name), $term));
            })
            ->when($this->merchantAccessStatusFilter !== 'all', function ($collection) {
                $active = $this->merchantAccessStatusFilter === 'active';

                return $collection->filter(fn ($m) => $m->is_active === $active);
            })
            ->take(20)
            ->values();

        return view('livewire.admin.gateway-hub', [
            'gateways' => $gateways,
            'merchants' => $merchants,
            'merchantAccessMerchants' => $merchantAccessMerchants,
            'credentialFields' => $credentialFields,
        ]);
    }
}
