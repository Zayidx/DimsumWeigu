<?php

namespace App\Livewire\Purchasing;

use App\Models\GoodsReceiving;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchasingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class GoodsReceivingsIndex extends Component
{
    use WithPagination;

    public ?int $purchaseOrderId = null;
    public array $items = [];
    public string $notes = '';
    public bool $showForm = false;

    public function begin(int $id): void
    {
        $this->authorizePermission('goods-receivings-create');
        $order = $this->allowedOrders()->with(['items.product'])->whereKey($id)->firstOrFail();
        $this->purchaseOrderId = $order->id;
        $this->items = [];
        foreach ($order->items as $item) {
            $open = max(0, (float) $item->quantity_ordered - (float) $item->quantity_received);
            if ($open > 0) $this->items[$item->id] = ['quantity' => number_format($open, 3, '.', ''), 'accepted' => number_format($open, 3, '.', ''), 'batch_number' => '', 'expires_at' => '', 'qc_note' => ''];
        }
        $this->notes = '';
        $this->showForm = true;
    }

    public function closeForm(): void { $this->showForm = false; $this->purchaseOrderId = null; $this->items = []; $this->notes = ''; $this->resetValidation(); }

    public function receive(PurchasingService $service): void
    {
        $this->authorizePermission('goods-receivings-create');
        $this->validate([
            'purchaseOrderId' => ['required', 'integer', 'exists:purchase_orders,id'],
            'items' => ['required', 'array', 'min:1'], 'items.*.quantity' => ['required', 'numeric', 'min:0', 'max:999999999999.999'],
            'items.*.accepted' => ['required', 'numeric', 'min:0', 'max:999999999999.999'],
            'items.*.batch_number' => ['nullable', 'string', 'max:80'], 'items.*.expires_at' => ['nullable', 'date'],
            'items.*.qc_note' => ['nullable', 'string', 'max:1000'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $rows = array_filter($this->items, fn ($row) => (float) ($row['quantity'] ?? 0) > 0);
        $service->receive($this->purchaseOrderId, $rows, (string) Str::uuid(), $this->notes ?: null);
        $this->closeForm();
        $this->dispatch('toast', type: 'success', message: __('Penerimaan barang, stok, dan hutang pemasok berhasil dicatat.'));
    }

    public function render()
    {
        $this->authorizePermission('goods-receivings-access');
        return view('livewire.purchasing.goods-receivings-index', [
            'orders' => $this->allowedOrders()->with(['supplier:id,name', 'warehouse:id,name'])->withCount('items')->latest('ordered_at')->paginate(20),
            'receivings' => GoodsReceiving::query()->with(['supplier:id,name', 'warehouse:id,name', 'order:id,number'])->whereIn('warehouse_id', $this->allowedOrders()->select('warehouse_id'))->latest('received_at')->limit(25)->get(),
            'selectedOrder' => $this->purchaseOrderId ? $this->allowedOrders()->with('items.product')->whereKey($this->purchaseOrderId)->first() : null,
        ])->layout('layouts.dashboard', ['title' => __('Penerimaan barang')]);
    }

    private function allowedOrders(): Builder
    {
        $outletId = (int) session('active_outlet_id');
        return PurchaseOrder::query()->whereIn('status', ['ordered', 'partial'])->whereHas('warehouse', function (Builder $query) use ($outletId) {
            $query->where('outlet_id', $outletId);
            if (auth()->user()->hasRole('super-admin') || auth()->user()->can('warehouses-access')) $query->orWhereNull('outlet_id');
        });
    }
    private function authorizePermission(string $permission): void { abort_unless(auth()->user()?->can($permission), 403); }
}
