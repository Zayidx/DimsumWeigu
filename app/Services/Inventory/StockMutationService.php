<?php

namespace App\Services\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductWarehouse;
use App\Models\StockMutation;
use App\Models\Warehouse;
use App\Support\AuditLogger;
use App\Support\FixedDecimal;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockMutationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Apply one idempotent stock mutation and keep the outlet balance/global aggregate in the same transaction. */
    public function mutate(
        int $productId,
        int $warehouseId,
        string $delta,
        string $type,
        string $idempotencyKey,
        ?string $reason = null,
        ?int $productBatchId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): array {
        $deltaUnits = FixedDecimal::toScaled($delta, 3, true);
        if ($deltaUnits === 0 || $idempotencyKey === '' || strlen($idempotencyKey) > 96) {
            throw ValidationException::withMessages(['stock' => __('Jumlah stok dan kunci idempotensi tidak valid.')]);
        }

        return DB::transaction(function () use ($productId, $warehouseId, $deltaUnits, $type, $idempotencyKey, $reason, $productBatchId, $referenceType, $referenceId) {
            $existing = StockMutation::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->product_id !== $productId || $existing->warehouse_id !== $warehouseId
                    || $existing->type !== $type || $existing->reference_type !== $referenceType
                    || $existing->reference_id !== $referenceId
                    || FixedDecimal::quantity((string) $existing->stock_after) - FixedDecimal::quantity((string) $existing->stock_before) !== $deltaUnits) {
                    throw ValidationException::withMessages(['idempotency_key' => __('Kunci idempotensi sudah digunakan untuk mutasi yang berbeda.')]);
                }

                return [
                    'balance' => ProductWarehouse::query()->where('product_id', $productId)->where('warehouse_id', $warehouseId)->firstOrFail(),
                    'batch_allocations' => [], 'replayed' => true,
                ];
            }

            $product = Product::query()->when(in_array($type, ['supplier_return', 'waste'], true), fn ($query) => $query->withTrashed())->lockForUpdate()->findOrFail($productId);
            $warehouse = Warehouse::query()->whereKey($warehouseId)->where('is_active', true)->firstOrFail();
            $this->assertWarehouseScope($warehouse);
            if ($deltaUnits > 0 && (! $product->is_semi_finished || ($warehouse->outlet_id !== null && $type !== 'transfer_in'))) {
                throw ValidationException::withMessages(['stock' => __('Gudang outlet hanya dapat menerima produk setengah jadi melalui transfer dari Gudang Bersama.')]);
            }
            if ($type === 'production_output' && ($warehouse->type !== 'central' || $warehouse->outlet_id !== null || ! $product->is_semi_finished)) {
                throw ValidationException::withMessages(['stock' => __('Hasil produksi hanya boleh menambah stok setengah jadi di Gudang Bersama.')]);
            }
            if ($type === 'transfer_out' && ($warehouse->type !== 'central' || $warehouse->outlet_id !== null || ! $product->is_semi_finished)) {
                throw ValidationException::withMessages(['stock' => __('Gudang Bersama hanya dapat mengirim produk setengah jadi ke outlet.')]);
            }
            if ($type === 'transfer_in' && ($warehouse->type === 'central' || $warehouse->outlet_id === null || ! $product->is_semi_finished)) {
                throw ValidationException::withMessages(['stock' => __('Stok transfer hanya dapat diterima sebagai produk setengah jadi di gudang outlet.')]);
            }
            $balance = ProductWarehouse::query()->firstOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                ['stock' => 0, 'reserved_stock' => 0, 'min_stock' => $product->min_stock, 'reorder_point' => $product->reorder_point],
            );
            $balance = ProductWarehouse::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
            $before = FixedDecimal::quantity((string) $balance->stock);
            $after = $before + $deltaUnits;

            if ($after < 0) {
                throw ValidationException::withMessages(['stock' => __('Stok tersedia tidak mencukupi.')]);
            }

            $allocations = [];
            if ($deltaUnits < 0) {
                $specificBatch = in_array($type, ['supplier_return', 'waste'], true) ? $productBatchId : null;
                if ($product->track_batches) {
                    $allocations = $this->allocateBatches($product, $warehouse, abs($deltaUnits), $specificBatch, in_array($type, ['supplier_return', 'waste'], true));
                }
                if ($product->track_batches && $allocations === []) {
                    throw ValidationException::withMessages(['stock' => __('Produk ini memerlukan batch aktif sebelum stok dapat dikurangi.')]);
                }
            } elseif ($productBatchId) {
                $batch = ProductBatch::query()->whereKey($productBatchId)->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->lockForUpdate()->firstOrFail();
                if ($batch->expires_at?->lt(today())) {
                    throw ValidationException::withMessages(['batch' => __('Batch kedaluwarsa tidak dapat menerima stok.')]);
                }
                $batchBefore = FixedDecimal::quantity((string) $batch->quantity_remaining);
                $batch->quantity_remaining = FixedDecimal::quantityText($batchBefore + $deltaUnits);
                $batch->status = 'active';
                $batch->save();
                $allocations[] = ['batch' => $batch, 'quantity_units' => $deltaUnits];
            } elseif ($product->track_batches && $deltaUnits > 0) {
                throw ValidationException::withMessages(['batch' => __('Batch wajib dipilih untuk produk ini.')]);
            }

            $balance->stock = FixedDecimal::quantityText($after);
            $balance->save();
            $globalUnits = ProductWarehouse::query()->where('product_id', $product->id)->get(['stock'])
                ->reduce(fn (int $total, ProductWarehouse $row) => $total + FixedDecimal::quantity((string) $row->stock), 0);
            $product->stock = FixedDecimal::quantityText($globalUnits);
            $product->save();

            if ($allocations === []) {
                $this->writeLedger($product, $warehouse, $type, $deltaUnits, $before, $after, $idempotencyKey, $referenceType, $referenceId, $reason);
            } else {
                $running = $before;
                foreach ($allocations as $allocation) {
                    $next = $running + $allocation['quantity_units'];
                    $this->writeLedger($product, $warehouse, $type, $allocation['quantity_units'], $running, $next,
                        $idempotencyKey.':'.$allocation['batch']->id, $referenceType, $referenceId, $reason, $allocation['batch']->id);
                    $running = $next;
                }
            }

            StockMutation::query()->create([
                'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'user_id' => auth()->id(),
                'type' => $type, 'quantity' => FixedDecimal::quantityText(abs($deltaUnits)),
                'stock_before' => FixedDecimal::quantityText($before), 'stock_after' => FixedDecimal::quantityText($after),
                'reason' => $reason, 'reference_type' => $referenceType, 'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
            ]);
            InventoryBalance::query()->updateOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                ['calculated_quantity' => FixedDecimal::quantityText($after), 'reconciled_at' => now()],
            );
            $this->audit->record('inventory.stock_mutated', $product, ['stock' => FixedDecimal::quantityText($before)], [
                'stock' => FixedDecimal::quantityText($after), 'warehouse_id' => $warehouse->id,
                'type' => $type, 'reference_type' => $referenceType, 'reference_id' => $referenceId,
            ]);

            return ['balance' => $balance->refresh(), 'batch_allocations' => $allocations, 'replayed' => false];
        }, attempts: 3);
    }

    private function allocateBatches(Product $product, Warehouse $warehouse, int $quantityUnits, ?int $specificBatchId, bool $allowExpired = false): array
    {
        $batches = ProductBatch::query()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)
            ->whereIn('status', $allowExpired ? ['active', 'expired'] : ['active'])->where('quantity_remaining', '>', 0)
            ->when(! $allowExpired, fn ($query) => $query->where(fn ($dates) => $dates->whereNull('expires_at')->orWhereDate('expires_at', '>=', today())))
            ->when($specificBatchId, fn ($query) => $query->whereKey($specificBatchId))
            ->orderByRaw('expires_at IS NULL')->orderBy('expires_at')->orderBy('id')->lockForUpdate()->get();

        if ($batches->isEmpty()) {
            return [];
        }

        $remaining = $quantityUnits;
        $allocations = [];
        foreach ($batches as $batch) {
            if ($remaining === 0) {
                break;
            }
            $available = FixedDecimal::quantity((string) $batch->quantity_remaining);
            $taken = min($available, $remaining);
            $batch->quantity_remaining = FixedDecimal::quantityText($available - $taken);
            if ($available === $taken) {
                $batch->status = 'depleted';
            }
            $batch->save();
            $allocations[] = ['batch' => $batch, 'quantity_units' => -$taken];
            $remaining -= $taken;
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages(['stock' => __('Stok batch yang belum kedaluwarsa tidak mencukupi.')]);
        }

        return $allocations;
    }

    private function writeLedger(Product $product, Warehouse $warehouse, string $type, int $deltaUnits, int $before, int $after, string $key, ?string $referenceType, ?int $referenceId, ?string $reason, ?int $batchId = null): void
    {
        InventoryLedger::query()->create([
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'product_batch_id' => $batchId,
            'user_id' => auth()->id(), 'type' => $type,
            'quantity_delta' => FixedDecimal::quantityText($deltaUnits),
            'quantity_before' => FixedDecimal::quantityText($before), 'quantity_after' => FixedDecimal::quantityText($after),
            'reference_type' => $referenceType, 'reference_id' => $referenceId, 'idempotency_key' => $key,
            'metadata' => ['reason' => $reason], 'created_at' => now(),
        ]);
    }

    private function assertWarehouseScope(Warehouse $warehouse): void
    {
        $user = auth()->user();
        if ($user?->hasRole('super-admin')) {
            return;
        }

        if (! $user) {
            throw new ModelNotFoundException;
        }

        if (! $warehouse->outlet_id) {
            abort_unless($warehouse->type === 'central' && $user->can('warehouses-access'), 404);

            return;
        }

        if (! $user->outlets()->whereKey($warehouse->outlet_id)->exists()) {
            throw new ModelNotFoundException;
        }
    }
}
