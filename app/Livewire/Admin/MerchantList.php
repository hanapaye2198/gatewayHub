<?php

namespace App\Livewire\Admin;

use App\Models\Merchant;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class MerchantList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    /** @var array<string, array{except: string}> */
    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'sortField' => ['except' => 'name'],
        'sortDirection' => ['except' => 'asc'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'sortField', 'sortDirection']);
    }

    public function render()
    {
        $query = Merchant::query();

        if ($this->search !== '') {
            $query->where(function ($q): void {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->statusFilter !== 'all') {
            $query->where('is_active', $this->statusFilter === 'active');
        }

        $query->orderBy($this->sortField, $this->sortDirection);

        $merchants = $query->with('users')->paginate(10);

        $baseQuery = Merchant::query();

        return view('livewire.admin.merchant-list', [
            'merchants' => $merchants,
            'activeCount' => (clone $baseQuery)->where('is_active', true)->count(),
            'inactiveCount' => (clone $baseQuery)->where('is_active', false)->count(),
        ]);
    }
}
