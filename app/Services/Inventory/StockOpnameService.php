<?php

namespace App\Services\Inventory;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductWarehouse;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Warehouse;
use App\Support\AuditLogger;
use App\Support\FixedDecimal;
use App\Support\OutletAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockOpnameService
{
    public function __construct(private readonly StockMutationService $stock, private readonly AuditLogger $audit) {}

    public function createDraft(int $warehouseId, ?string $notes = null): StockOpname
    {
        abort_unless(auth()->user()?->can('stock-opnames-create'), 403);
        return DB::transaction(function () use ($warehouseId, $notes) {
            $warehouse = $this->warehouseForUser($warehouseId);
            $products = Product::query()->where('is_active', true)->where('track_stock', true)->orderBy('id')->lockForUpdate()->get();
            $opname = StockOpname::query()->create([
                'number' => 'OP-'.now()->format('ymdHis').'-'.Str::upper(Str::random(5)), 'warehouse_id' => $warehouse->id,
                'created_by' => auth()->id(), 'status' => 'draft', 'snapshot_at' => now(), 'notes' => $notes,
            ]);
            foreach ($products as $product) {
                $balance = ProductWarehouse::query()->firstOrCreate(
                    ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                    ['stock' => 0, 'reserved_stock' => 0, 'min_stock' => $product->min_stock, 'reorder_point' => $product->reorder_point],
                );
                $balance = ProductWarehouse::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
                StockOpnameItem::query()->create([
                    'stock_opname_id' => $opname->id, 'product_id' => $product->id,
                    'system_quantity' => $balance->stock, 'counted_quantity' => null, 'variance' => null,
                ]);
            }
            $this->audit->record('stock_opname.created', $opname, null, ['number' => $opname->number, 'warehouse_id' => $warehouse->id, 'item_count' => $products->count()]);
            return $opname->load('items.product');
        }, attempts: 3);
    }

    public function count(int $opnameId, int $itemId, string $quantity, ?string $notes = null): StockOpnameItem
    {
        abort_unless(auth()->user()?->can('stock-opnames-create'), 403);
        $count = FixedDecimal::quantity($quantity);
        if ($count < 0) throw ValidationException::withMessages(['quantity' => __('Jumlah hitung tidak boleh negatif.')]);
        return DB::transaction(function () use ($opnameId, $itemId, $count, $notes) {
            $opname = StockOpname::query()->whereKey($opnameId)->lockForUpdate()->firstOrFail();
            $this->warehouseForUser($opname->warehouse_id);
            if ($opname->status !== 'draft') throw ValidationException::withMessages(['opname' => __('Hitung hanya dapat diubah pada draft.')]);
            $item = StockOpnameItem::query()->where('stock_opname_id', $opname->id)->whereKey($itemId)->lockForUpdate()->firstOrFail();
            $item->forceFill(['counted_quantity' => FixedDecimal::quantityText($count), 'variance' => FixedDecimal::quantityText($count - FixedDecimal::quantity((string) $item->system_quantity)), 'notes' => $notes])->save();
            return $item->refresh();
        });
    }

    public function review(int $opnameId): StockOpname
    {
        abort_unless(auth()->user()?->can('stock-opnames-review'), 403);
        return DB::transaction(function () use ($opnameId) {
            $opname = StockOpname::query()->with('items')->whereKey($opnameId)->lockForUpdate()->firstOrFail();
            $this->warehouseForUser($opname->warehouse_id);
            if ($opname->status !== 'draft' || $opname->items->contains(fn (StockOpnameItem $item) => $item->counted_quantity === null)) {
                throw ValidationException::withMessages(['opname' => __('Lengkapi seluruh jumlah hitung sebelum review.')]);
            }
            $opname->forceFill(['status' => 'reviewed', 'reviewed_by' => auth()->id()])->save();
            $this->audit->record('stock_opname.reviewed', $opname, ['status' => 'draft'], ['status' => 'reviewed']);
            return $opname->refresh();
        }, attempts: 3);
    }

    public function finalize(int $opnameId): StockOpname
    {
        abort_unless(auth()->user()?->can('stock-opnames-finalize'), 403);
        return DB::transaction(function () use ($opnameId) {
            $opname = StockOpname::query()->with(['items.product'])->whereKey($opnameId)->lockForUpdate()->firstOrFail();
            $warehouse = $this->warehouseForUser($opname->warehouse_id);
            if ($opname->status !== 'reviewed' || $opname->items->contains(fn (StockOpnameItem $item) => $item->counted_quantity === null)) {
                throw ValidationException::withMessages(['opname' => __('Hanya opname yang sudah direview lengkap dapat difinalisasi.')]);
            }
            foreach ($opname->items->sortBy('product_id') as $item) {
                $product = Product::query()->lockForUpdate()->findOrFail($item->product_id);
                $balance = ProductWarehouse::query()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->lockForUpdate()->firstOrFail();
                $current = FixedDecimal::quantity((string) $balance->stock);
                $snapshot = FixedDecimal::quantity((string) $item->system_quantity);
                if ($current !== $snapshot) {
                    throw ValidationException::withMessages(['opname' => __('Stok berubah setelah snapshot opname. Buat opname baru agar selisih tidak diterapkan pada saldo yang salah.')]);
                }
                $counted = FixedDecimal::quantity((string) $item->counted_quantity);
                $delta = $counted - $current;
                if ($delta === 0) continue;
                $batch = null;
                if ($delta > 0 && $product->track_batches) {
                    $batchNumber = 'OP-'.$opname->number.'-'.$product->id;
                    $batch = ProductBatch::query()->create([
                        'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'batch_number' => $batchNumber,
                        'quantity_received' => 0, 'quantity_remaining' => 0, 'status' => 'active',
                    ]);
                }
                $this->stock->mutate($product->id, $warehouse->id, FixedDecimal::quantityText($delta), 'stock_opname',
                    'opname:'.$opname->id.':'.$item->id, $item->notes ?: $opname->notes, $batch?->id, StockOpname::class, $opname->id);
                if ($batch) {
                    $batch->quantity_received = FixedDecimal::quantityText($delta);
                    $batch->save();
                }
            }
            $opname->forceFill(['status' => 'finalized', 'finalized_by' => auth()->id(), 'finalized_at' => now()])->save();
            $this->audit->record('stock_opname.finalized', $opname, ['status' => 'reviewed'], ['status' => 'finalized', 'warehouse_id' => $warehouse->id]);
            return $opname->refresh();
        }, attempts: 3);
    }

    private function warehouseForUser(int $id): Warehouse
    {
        $warehouse = Warehouse::query()->whereKey($id)->where('is_active', true)->firstOrFail();
        if ($warehouse->outlet_id === null) abort_unless(auth()->user()?->hasRole('super-admin') || auth()->user()?->can('warehouses-access'), 404);
        else app(OutletAccess::class)->require(auth()->user(), $warehouse->outlet_id);
        return $warehouse;
    }
}
