<?php

namespace App\Livewire\Purchasing;

use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchasingService;
use App\Support\FixedDecimal;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class PurchaseOrdersIndex extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $supplierId = null;

    public ?int $warehouseId = null;

    public string $expectedAt = '';

    public string $notes = '';

    public array $items = [];

    public function create(): void
    {
        $this->authorizePermission('purchase-orders-create');
        $this->resetForm();
        $this->items = [['product_id' => '', 'quantity' => '1', 'unit_cost' => '0', 'tax_rate' => '0']];
        $this->showForm = true;
    }

    public function addItem(): void
    {
        $this->authorizePermission('purchase-orders-create');
        $this->items[] = ['product_id' => '', 'quantity' => '1', 'unit_cost' => '0', 'tax_rate' => '0'];
    }

    public function removeItem(int $index): void
    {
        $this->authorizePermission('purchase-orders-create');
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function closeForm(): void
    {
        $this->resetForm();
    }

    public function loadReorderItems(): void
    {
        $this->authorizePermission('purchase-orders-create');
        $this->validate(['warehouseId' => ['required', 'integer', 'exists:warehouses,id']]);
        Warehouse::query()->whereKey((int) $this->warehouseId)->where('is_active', true)->where('type', 'central')->whereNull('outlet_id')->firstOrFail();
        $balances = ProductWarehouse::query()->with('product:id,title,buy_price,tax_rate,max_stock,is_active')
            ->where('warehouse_id', (int) $this->warehouseId)->whereColumn('stock', '<=', 'reorder_point')
            ->whereHas('product', fn (Builder $query) => $query->where('is_active', true))->orderBy('product_id')->get();
        $this->items = $balances->map(function (ProductWarehouse $balance): array {
            $current = FixedDecimal::quantity((string) $balance->stock);
            $reorder = FixedDecimal::quantity((string) $balance->reorder_point);
            $max = FixedDecimal::quantity((string) ($balance->product->max_stock ?: 0));
            $target = $max > $current ? $max : $current + max($reorder, 1000);

            return ['product_id' => (string) $balance->product_id, 'quantity' => FixedDecimal::quantityText(max(1, $target - $current)), 'unit_cost' => (string) $balance->product->buy_price, 'tax_rate' => (string) ($balance->product->tax_rate ?? 0)];
        })->values()->all();
        if ($this->items === []) {
            $this->addError('items', __('Tidak ada produk aktif di bawah reorder point pada gudang ini.'));
        } else {
            $this->resetValidation('items');
        }
    }

    public function saveDraft(PurchasingService $service): void
    {
        $this->authorizePermission('purchase-orders-create');
        $data = $this->validate([
            'supplierId' => ['required', 'integer', 'exists:suppliers,id'],
            'warehouseId' => ['required', 'integer', 'exists:warehouses,id'],
            'expectedAt' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.999'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'items.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
        $service->createDraft((int) $data['supplierId'], (int) $data['warehouseId'], $data['items'], $data['notes'] ?? null, $data['expectedAt'] ?: null);
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: __('PO draft berhasil dibuat.'));
    }

    public function order(int $id, PurchasingService $service): void
    {
        $service->order($id);
        $this->dispatch('toast', type: 'success', message: __('Purchase order ditandai sebagai dipesan.'));
    }

    public function cancel(int $id, PurchasingService $service): void
    {
        $service->cancel($id);
        $this->dispatch('toast', type: 'success', message: __('Purchase order dibatalkan.'));
    }

    public function render()
    {
        $this->authorizePermission('purchase-orders-access');
        $warehouseQuery = Warehouse::query()->where('is_active', true)->where('type', 'central')->whereNull('outlet_id');
        $warehouseIds = (clone $warehouseQuery)->pluck('id');

        return view('livewire.purchasing.purchase-orders-index', [
            'orders' => PurchaseOrder::query()->with(['supplier:id,name,code', 'warehouse:id,name'])->withCount('items')->whereIn('warehouse_id', $warehouseIds)->latest()->paginate(20),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'warehouses' => (clone $warehouseQuery)->orderBy('name')->get(['id', 'name', 'outlet_id']),
            'products' => Product::query()->where('is_active', true)->where('is_semi_finished', true)->orderBy('title')->get(['id', 'title', 'sku', 'buy_price', 'tax_rate']),
        ])->layout('layouts.dashboard', ['title' => __('Purchase order')]);
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->supplierId = null;
        $this->warehouseId = null;
        $this->expectedAt = '';
        $this->notes = '';
        $this->items = [];
        $this->resetValidation();
    }
}
