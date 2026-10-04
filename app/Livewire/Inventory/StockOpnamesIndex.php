<?php

namespace App\Livewire\Inventory;

use App\Models\StockOpname;
use App\Models\Warehouse;
use App\Services\Inventory\StockOpnameService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class StockOpnamesIndex extends Component
{
    use WithPagination;
    public ?int $warehouseId = null;
    public ?int $opnameId = null;
    public array $counts = [];
    public string $notes = '';

    public function mount(): void { $this->warehouseId = session('active_warehouse_id') ? (int) session('active_warehouse_id') : null; }

    public function selectWarehouse(int $id): void { $this->warehouseForUser($id); $this->warehouseId = $id; session()->put('active_warehouse_id', $id); $this->opnameId = null; }

    public function create(StockOpnameService $service): void
    {
        $this->permit('stock-opnames-create');
        $opname = $service->createDraft((int) $this->warehouseId, $this->notes ?: null);
        $this->opnameId = $opname->id;
        $this->notes = '';
        $this->syncCounts($opname);
    }

    public function open(int $id): void
    {
        $opname = $this->opnameQuery()->whereKey($id)->with('items.product.unit')->firstOrFail();
        $this->opnameId = $opname->id;
        $this->syncCounts($opname);
    }

    public function saveCount(int $itemId, StockOpnameService $service): void
    {
        $this->permit('stock-opnames-create');
        $this->validate(['counts.'.$itemId.'.quantity' => ['required', 'numeric', 'min:0', 'max:999999999999.999'], 'counts.'.$itemId.'.notes' => ['nullable', 'string', 'max:500']]);
        $service->count((int) $this->opnameId, $itemId, (string) $this->counts[$itemId]['quantity'], $this->counts[$itemId]['notes'] ?? null);
        $this->dispatch('toast', type: 'success', message: __('Hasil hitung disimpan.'));
    }

    public function review(StockOpnameService $service): void { $this->permit('stock-opnames-review'); $service->review((int) $this->opnameId); $this->dispatch('toast', type: 'success', message: __('Opname siap difinalisasi.')); }
    public function finalize(StockOpnameService $service): void { $this->permit('stock-opnames-finalize'); $service->finalize((int) $this->opnameId); $this->dispatch('toast', type: 'success', message: __('Selisih opname diterapkan ke stok dan ledger.')); }

    public function render()
    {
        $this->permit('stock-opnames-access');
        $outletId = (int) session('active_outlet_id');
        $warehouses = Warehouse::query()->where('is_active', true)->where(function (Builder $query) use ($outletId) {
            $query->where('outlet_id', $outletId);
            if (auth()->user()->hasRole('super-admin') || auth()->user()->can('warehouses-access')) $query->orWhereNull('outlet_id');
        })->orderBy('name')->get(['id', 'name', 'outlet_id']);
        if (! $warehouses->contains('id', $this->warehouseId)) $this->warehouseId = $warehouses->first()?->id;
        $selected = $this->opnameId ? $this->opnameQuery()->with('items.product.unit')->whereKey($this->opnameId)->first() : null;
        if ($selected && $this->counts === []) $this->syncCounts($selected);
        return view('livewire.inventory.stock-opnames-index', [
            'warehouses' => $warehouses, 'selected' => $selected,
            'opnames' => $this->opnameQuery()->with('warehouse:id,name')->withCount('items')->latest()->paginate(20),
        ])->layout('layouts.dashboard', ['title' => __('Stok opname')]);
    }

    private function opnameQuery(): Builder
    {
        return StockOpname::query()->where('warehouse_id', (int) $this->warehouseId);
    }
    private function warehouseForUser(int $id): Warehouse
    {
        $outletId = (int) session('active_outlet_id');
        $warehouse = Warehouse::query()->whereKey($id)->where('is_active', true)->firstOrFail();
        if ($warehouse->outlet_id === $outletId) return $warehouse;
        abort_unless($warehouse->outlet_id === null && (auth()->user()->hasRole('super-admin') || auth()->user()->can('warehouses-access')), 404);
        return $warehouse;
    }
    private function syncCounts(StockOpname $opname): void
    {
        $this->counts = $opname->items->mapWithKeys(fn ($item) => [$item->id => [
            'quantity' => $item->counted_quantity === null ? '' : (string) $item->counted_quantity, 'notes' => $item->notes ?? '',
        ]])->all();
    }
    private function permit(string $permission): void { abort_unless(auth()->user()?->can($permission), 403); }
}
