<?php

namespace App\Livewire\Inventory;

use App\Models\CashierShift;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\CashierStockMovementService;
use App\Support\OutletAccess;
use Illuminate\Support\Str;
use Livewire\Component;

class CashierStockTransfersIndex extends Component
{
    public string $direction = 'pickup';

    public array $items = [];

    public string $notes = '';

    public bool $isReturnGood = false;

    public string $idempotencyKey = '';

    public function mount(): void
    {
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->items = [['product_id' => '', 'quantity' => '1']];
        $this->notes = '';
        $this->isReturnGood = false;
        $this->idempotencyKey = (string) Str::uuid();
    }

    public function addItem(): void
    {
        abort_unless(auth()->user()->can('cashier-stock-move'), 403);
        $this->items[] = ['product_id' => '', 'quantity' => '1'];
    }

    public function removeItem(int $index): void
    {
        abort_unless(auth()->user()->can('cashier-stock-move'), 403);
        if (count($this->items) > 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    public function save(CashierStockMovementService $service): void
    {
        abort_unless(auth()->user()->can('cashier-stock-move'), 403);
        $data = $this->validate([
            'direction' => ['required', 'in:pickup,return'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'notes' => [$this->direction === 'return' ? 'required' : 'nullable', 'string', 'max:2000'],
            'isReturnGood' => ['boolean'],
        ]);
        $actor = User::query()->findOrFail(auth()->id());
        $outletId = (int) session('active_outlet_id');
        app(OutletAccess::class)->require($actor, $outletId);
        $transfer = $service->move($actor, $outletId, $data['direction'], $data['items'], $data['notes'] ?? null,
            $this->idempotencyKey, $data['direction'] === 'return' && $this->isReturnGood ? 'good' : null);
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: __('Perpindahan stok :number selesai dan tercatat di kedua gudang.', ['number' => $transfer->number]));
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('cashier-stock-move'), 403);
        $outletId = (int) session('active_outlet_id');
        app(OutletAccess::class)->require(auth()->user(), $outletId);
        $central = Warehouse::query()->where('type', 'central')->whereNull('outlet_id')->where('is_active', true)->firstOrFail();
        $shiftWarehouseId = CashierShift::query()->where('open_user_id', auth()->id())->where('outlet_id', $outletId)
            ->where('status', 'open')->latest('opened_at')->value('warehouse_id');
        $branch = Warehouse::query()->where('outlet_id', $outletId)->where('type', '!=', 'central')->where('is_active', true)
            ->when($shiftWarehouseId, fn ($query) => $query->whereKey($shiftWarehouseId))
            ->orderByDesc('is_sales_enabled')->orderBy('id')->firstOrFail();
        $products = Product::query()->where('is_active', true)->where('is_semi_finished', true)->where('track_stock', true)->orderBy('title')->get(['id', 'title', 'sku']);
        $balances = ProductWarehouse::query()->whereIn('warehouse_id', [$central->id, $branch->id])->whereIn('product_id', $products->pluck('id'))
            ->get()->groupBy('warehouse_id');
        $transfers = StockTransfer::query()->with(['items.product:id,title', 'sourceWarehouse:id,name', 'destinationWarehouse:id,name'])
            ->where('created_by', auth()->id())->whereIn('direction', ['pickup', 'return'])
            ->where(fn ($query) => $query->where('source_warehouse_id', $branch->id)->orWhere('destination_warehouse_id', $branch->id))
            ->latest()->limit(30)->get();

        return view('livewire.inventory.cashier-stock-transfers-index', compact('products', 'balances', 'transfers', 'central', 'branch'))
            ->layout('layouts.dashboard', ['title' => __('Ambil & kembalikan stok')]);
    }
}
