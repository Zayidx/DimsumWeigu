<?php

namespace App\Livewire\Inventory;

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\Inventory\StockTransferService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class StockTransfersIndex extends Component
{
    use WithPagination;
    public bool $showForm = false;
    public ?int $sourceWarehouseId = null;
    public ?int $destinationWarehouseId = null;
    public string $notes = '';
    public array $items = [];

    public function create(): void { $this->permit('transfers-create'); $this->resetForm(); $this->items = [['product_id' => '', 'quantity' => '1']]; $this->showForm = true; }
    public function addItem(): void { $this->permit('transfers-create'); $this->items[] = ['product_id' => '', 'quantity' => '1']; }
    public function removeItem(int $index): void { $this->permit('transfers-create'); unset($this->items[$index]); $this->items = array_values($this->items); }
    public function closeForm(): void { $this->resetForm(); }

    public function saveDraft(StockTransferService $service): void
    {
        $this->permit('transfers-create');
        $data = $this->validate([
            'sourceWarehouseId' => ['required', 'integer', 'exists:warehouses,id'], 'destinationWarehouseId' => ['required', 'integer', 'different:sourceWarehouseId', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string', 'max:2000'], 'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id', 'distinct'], 'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.999'],
        ]);
        $service->createDraft((int) $data['sourceWarehouseId'], (int) $data['destinationWarehouseId'], $data['items'], $data['notes'] ?? null);
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: __('Draft transfer stok dibuat.'));
    }

    public function send(int $id, StockTransferService $service): void { $service->send($id); $this->dispatch('toast', type: 'success', message: __('Stok dikurangi dari gudang sumber dan transfer dikirim.')); }
    public function receive(int $id, StockTransferService $service): void { $service->receive($id, (string) Str::uuid()); $this->dispatch('toast', type: 'success', message: __('Transfer diterima dan stok tujuan diperbarui.')); }
    public function cancel(int $id, StockTransferService $service): void { $service->cancel($id); $this->dispatch('toast', type: 'success', message: __('Transfer dibatalkan dan stok dikembalikan bila sudah dikirim.')); }

    public function render()
    {
        $this->permit('transfers-access');
        $outletId = (int) session('active_outlet_id');
        $warehouses = Warehouse::query()->where('is_active', true)->where(function (Builder $query) use ($outletId) {
            $query->where('outlet_id', $outletId);
            if (auth()->user()->hasRole('super-admin') || auth()->user()->can('warehouses-access')) $query->orWhereNull('outlet_id');
        })->orderBy('name')->get(['id', 'name', 'outlet_id']);
        $ids = $warehouses->pluck('id');
        $transfers = StockTransfer::query()->with(['sourceWarehouse:id,name', 'destinationWarehouse:id,name', 'items.product:id,title,sku'])
            ->where(fn (Builder $query) => $query->whereIn('source_warehouse_id', $ids)->orWhereIn('destination_warehouse_id', $ids))
            ->latest()->paginate(20);
        return view('livewire.inventory.stock-transfers-index', [
            'transfers' => $transfers, 'warehouses' => $warehouses,
            'products' => Product::query()->where('is_active', true)->where('track_stock', true)->orderBy('title')->get(['id', 'title', 'sku']),
        ])->layout('layouts.dashboard', ['title' => __('Transfer stok')]);
    }

    private function permit(string $permission): void { abort_unless(auth()->user()?->can($permission), 403); }
    private function resetForm(): void { $this->showForm = false; $this->sourceWarehouseId = null; $this->destinationWarehouseId = null; $this->notes = ''; $this->items = []; $this->resetValidation(); }
}
