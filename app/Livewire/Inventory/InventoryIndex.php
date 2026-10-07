<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductWarehouse;
use App\Models\Warehouse;
use App\Services\Inventory\StockMutationService;
use App\Support\OutletAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class InventoryIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $warehouseId = null;

    public bool $showForm = false;

    public ?int $productId = null;

    public string $direction = 'in';

    public string $quantity = '';

    public string $reason = '';

    public string $batchNumber = '';

    public string $expiresAt = '';

    public string $ledgerSearch = '';

    public function mount(): void
    {
        $requestedWarehouse = request()->query('warehouse');
        $this->warehouseId = $requestedWarehouse && ctype_digit((string) $requestedWarehouse)
            ? (int) $requestedWarehouse
            : (session('active_warehouse_id') ? (int) session('active_warehouse_id') : null);
        $requestedSearch = request()->query('search', '');
        $this->search = is_string($requestedSearch) ? mb_substr(trim($requestedSearch), 0, 80) : '';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function selectWarehouse(int $warehouseId): void
    {
        $this->warehouseForUser($warehouseId);
        $this->warehouseId = $warehouseId;
        session()->put('active_warehouse_id', $warehouseId);
        $this->resetPage();
    }

    public function openAdjustment(int $productId): void
    {
        $this->authorizePermission('inventory-access');
        $warehouse = $this->warehouseForUser((int) $this->warehouseId);
        Product::query()->whereKey($productId)->firstOrFail();
        $this->productId = $productId;
        $this->reset(['direction', 'quantity', 'reason', 'batchNumber', 'expiresAt']);
        $this->direction = $warehouse->outlet_id === null ? 'in' : 'out';
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
    }

    public function adjust(StockMutationService $stock): void
    {
        $this->authorizePermission($this->direction === 'in' ? 'inventory-opening' : 'inventory-adjust');
        $data = $this->validate([
            'productId' => ['required', 'integer', 'exists:products,id'],
            'direction' => ['required', 'in:in,out'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.999'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'batchNumber' => ['nullable', 'string', 'max:80'],
            'expiresAt' => ['nullable', 'date', 'after:today'],
        ]);
        $warehouse = $this->warehouseForUser((int) $this->warehouseId);
        if ($warehouse->outlet_id !== null && $data['direction'] === 'in') {
            throw ValidationException::withMessages(['direction' => __('Stok outlet hanya dapat bertambah melalui penerimaan transfer dari Gudang Bersama.')]);
        }
        $product = Product::query()->whereKey($data['productId'])->firstOrFail();
        if (! $product->track_stock || ! $product->is_semi_finished) {
            throw ValidationException::withMessages(['productId' => __('Gudang hanya mengelola stok produk setengah jadi yang melacak stok.')]);
        }
        if ($product->track_batches && $data['direction'] === 'in' && blank($data['batchNumber'])) {
            throw ValidationException::withMessages(['batchNumber' => __('Nomor batch wajib diisi untuk produk ini.')]);
        }
        $delta = $data['direction'] === 'in' ? $data['quantity'] : '-'.$data['quantity'];
        $type = $data['direction'] === 'in' ? 'opening' : 'adjustment';

        DB::transaction(function () use ($data, $product, $warehouse, $delta, $type, $stock) {
            $batch = null;
            if ($product->track_batches && $data['direction'] === 'in') {
                $batch = ProductBatch::query()->firstOrCreate([
                    'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'batch_number' => trim($data['batchNumber']),
                ], [
                    'expires_at' => $data['expiresAt'] ?: null, 'quantity_received' => 0,
                    'quantity_remaining' => 0, 'status' => 'active',
                ]);
                if ($batch->status === 'expired' || ($batch->expires_at && $batch->expires_at->isPast())) {
                    throw ValidationException::withMessages(['batchNumber' => __('Batch kedaluwarsa tidak dapat digunakan.')]);
                }
            }
            $stock->mutate($product->id, $warehouse->id, $delta, $type, (string) Str::uuid(), $data['reason'], $batch?->id, 'manual_adjustment');
            if ($batch) {
                $batch->quantity_received = (float) $batch->quantity_received + (float) $data['quantity'];
                $batch->save();
            }
        }, attempts: 3);

        $this->closeForm();
        $this->dispatch('toast', type: 'success', message: __('Mutasi stok dan ledger berhasil disimpan.'));
    }

    public function render(OutletAccess $access)
    {
        $this->authorizePermission('inventory-access');
        $outletId = (int) session('active_outlet_id');
        $warehouses = Warehouse::query()->where('is_active', true)
            ->where(function (Builder $query) use ($outletId) {
                $query->where('outlet_id', $outletId);
                if (auth()->user()->hasRole('super-admin') || auth()->user()->can('warehouses-access')) {
                    $query->orWhereNull('outlet_id');
                }
            })->orderBy('name')->get();
        $warehouseId = $this->warehouseId && $warehouses->contains('id', $this->warehouseId)
            ? $this->warehouseId : ($warehouses->first()?->id);
        if ($warehouseId && $warehouseId !== $this->warehouseId) {
            $this->warehouseId = $warehouseId;
        }
        $stocks = ProductWarehouse::query()->with(['product.category:id,name', 'product.unit:id,name,symbol', 'warehouse:id,name'])
            ->where('warehouse_id', $warehouseId)
            ->whereHas('product', fn (Builder $query) => $query->where('is_active', true)->where('is_semi_finished', true))
            ->when($this->search !== '', fn (Builder $query) => $query->whereHas('product', fn (Builder $product) => $product->where('title', 'like', '%'.$this->search.'%')->orWhere('sku', 'like', '%'.$this->search.'%')->orWhere('barcode', 'like', '%'.$this->search.'%')))
            ->orderBy('stock')->paginate(25);
        $ledger = InventoryLedger::query()->with(['product:id,title,sku', 'warehouse:id,name'])
            ->when($warehouseId, fn (Builder $query) => $query->where('warehouse_id', $warehouseId))
            ->whereHas('product', fn (Builder $product) => $product->where('is_semi_finished', true))
            ->when($this->ledgerSearch !== '', fn (Builder $query) => $query->whereHas('product', fn (Builder $product) => $product->where('title', 'like', '%'.$this->ledgerSearch.'%')->orWhere('sku', 'like', '%'.$this->ledgerSearch.'%')))
            ->latest('created_at')->limit(20)->get();

        return view('livewire.inventory.inventory-index', compact('warehouses', 'warehouseId', 'stocks', 'ledger'))
            ->with('isSharedWarehouse', $warehouses->firstWhere('id', $warehouseId)?->type === 'central')
            ->layout('layouts.dashboard', ['title' => __('Inventori')]);
    }

    private function warehouseForUser(int $warehouseId): Warehouse
    {
        $warehouse = Warehouse::query()->whereKey($warehouseId)->where('is_active', true)->firstOrFail();
        $outletId = (int) session('active_outlet_id');
        if ($warehouse->outlet_id !== $outletId) {
            abort_unless($warehouse->outlet_id === null && (auth()->user()->hasRole('super-admin') || auth()->user()->can('warehouses-access')), 404);
        }
        if ($warehouse->outlet_id) {
            app(OutletAccess::class)->require(auth()->user(), $warehouse->outlet_id);
        }

        return $warehouse;
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
