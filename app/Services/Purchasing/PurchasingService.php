<?php

namespace App\Services\Purchasing;

use App\Models\GoodsReceiving;
use App\Models\GoodsReceivingItem;
use App\Models\Payable;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Inventory\StockMutationService;
use App\Support\AuditLogger;
use App\Support\FixedDecimal;
use App\Support\OutletAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchasingService
{
    public function __construct(private readonly AuditLogger $audit, private readonly StockMutationService $stock) {}

    public function createDraft(int $supplierId, int $warehouseId, array $items, ?string $notes = null, ?string $expectedAt = null): PurchaseOrder
    {
        abort_unless(auth()->user()?->can('purchase-orders-create'), 403);
        if ($items === []) {
            throw ValidationException::withMessages(['items' => __('Tambahkan setidaknya satu produk.')]);
        }

        return DB::transaction(function () use ($supplierId, $warehouseId, $items, $notes, $expectedAt) {
            $supplier = Supplier::query()->whereKey($supplierId)->where('is_active', true)->firstOrFail();
            $warehouse = $this->warehouseForUser($warehouseId);
            abort_unless($warehouse->type === 'central' && $warehouse->outlet_id === null, 403, __('Pembelian stok gudang hanya ditujukan ke Gudang Bersama.'));
            $order = PurchaseOrder::query()->create([
                'number' => 'PO-'.now()->format('ymdHis').'-'.Str::upper(Str::random(5)),
                'supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id, 'created_by' => auth()->id(),
                'status' => 'draft', 'expected_at' => $expectedAt, 'notes' => $notes,
            ]);
            $subtotal = $taxTotal = 0;
            foreach ($items as $row) {
                $product = Product::query()->whereKey((int) ($row['product_id'] ?? 0))->where('is_active', true)->where('track_stock', true)->where('is_semi_finished', true)->firstOrFail();
                $quantity = FixedDecimal::quantity((string) ($row['quantity'] ?? '0'));
                $unitCost = FixedDecimal::money((string) ($row['unit_cost'] ?? '0'));
                $taxRate = (int) round((float) ($row['tax_rate'] ?? 0) * 100);
                if ($quantity <= 0 || $unitCost < 0 || $taxRate < 0 || $taxRate > 10000) {
                    throw ValidationException::withMessages(['items' => __('Kuantitas, harga, atau pajak pembelian tidak valid.')]);
                }
                $base = (int) round($unitCost * $quantity / 1000);
                $tax = (int) round($base * $taxRate / 10000);
                $lineTotal = $base + $tax;
                PurchaseOrderItem::query()->create([
                    'purchase_order_id' => $order->id, 'product_id' => $product->id,
                    'quantity_ordered' => FixedDecimal::quantityText($quantity), 'quantity_received' => 0,
                    'unit_cost' => FixedDecimal::moneyText($unitCost), 'tax_rate' => number_format($taxRate / 100, 4, '.', ''),
                    'line_total' => FixedDecimal::moneyText($lineTotal),
                ]);
                $subtotal += $base;
                $taxTotal += $tax;
            }
            $order->forceFill(['subtotal' => FixedDecimal::moneyText($subtotal), 'tax_total' => FixedDecimal::moneyText($taxTotal), 'grand_total' => FixedDecimal::moneyText($subtotal + $taxTotal)])->save();
            $this->audit->record('purchase_order.created', $order, null, ['number' => $order->number, 'supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id, 'item_count' => count($items)]);

            return $order->load('items.product', 'supplier', 'warehouse');
        }, attempts: 3);
    }

    public function order(int $purchaseOrderId): PurchaseOrder
    {
        abort_unless(auth()->user()?->can('purchase-orders-order'), 403);

        return DB::transaction(function () use ($purchaseOrderId) {
            $order = PurchaseOrder::query()->with('items')->whereKey($purchaseOrderId)->lockForUpdate()->firstOrFail();
            $warehouse = $this->warehouseForUser($order->warehouse_id);
            abort_unless($warehouse->type === 'central' && $warehouse->outlet_id === null, 403, __('Pembelian stok hanya dapat diproses untuk Gudang Bersama.'));
            if ($order->status !== 'draft' || $order->items->isEmpty()) {
                throw ValidationException::withMessages(['order' => __('Hanya PO draft dengan item yang dapat dipesan.')]);
            }
            $before = ['status' => $order->status];
            $order->forceFill(['status' => 'ordered', 'ordered_at' => today()])->save();
            $this->audit->record('purchase_order.ordered', $order, $before, ['status' => $order->status, 'ordered_at' => $order->ordered_at?->toDateString()]);

            return $order->refresh();
        }, attempts: 3);
    }

    public function cancel(int $purchaseOrderId): PurchaseOrder
    {
        abort_unless(auth()->user()?->can('purchase-orders-cancel'), 403);

        return DB::transaction(function () use ($purchaseOrderId) {
            $order = PurchaseOrder::query()->whereKey($purchaseOrderId)->lockForUpdate()->firstOrFail();
            $this->warehouseForUser($order->warehouse_id);
            if (! in_array($order->status, ['draft', 'ordered'], true) || $order->receivings()->exists()) {
                throw ValidationException::withMessages(['order' => __('PO hanya dapat dibatalkan sebelum ada penerimaan.')]);
            }
            $before = ['status' => $order->status];
            $order->forceFill(['status' => 'cancelled'])->save();
            $this->audit->record('purchase_order.cancelled', $order, $before, ['status' => $order->status]);

            return $order->refresh();
        }, attempts: 3);
    }

    /** @param array<int, array{quantity:string,accepted:string,batch_number?:string,expires_at?:string,qc_note?:string}> $receivedItems */
    public function receive(int $purchaseOrderId, array $receivedItems, string $idempotencyKey, ?string $notes = null): GoodsReceiving
    {
        abort_unless(auth()->user()?->can('goods-receivings-create'), 403);
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 96) {
            throw ValidationException::withMessages(['idempotency_key' => __('Kunci penerimaan tidak valid.')]);
        }

        return DB::transaction(function () use ($purchaseOrderId, $receivedItems, $idempotencyKey, $notes) {
            $existing = GoodsReceiving::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->purchase_order_id !== $purchaseOrderId) {
                    throw ValidationException::withMessages(['idempotency_key' => __('Kunci ini sudah digunakan untuk PO lain.')]);
                }

                return $existing->load('items');
            }
            $order = PurchaseOrder::query()->with('items.product')->whereKey($purchaseOrderId)->lockForUpdate()->firstOrFail();
            $warehouse = $this->warehouseForUser($order->warehouse_id);
            abort_unless($warehouse->type === 'central' && $warehouse->outlet_id === null, 403, __('Penerimaan stok pembelian hanya dapat dicatat di Gudang Bersama.'));
            if (! in_array($order->status, ['ordered', 'partial'], true)) {
                throw ValidationException::withMessages(['order' => __('PO belum dipesan atau sudah selesai.')]);
            }
            if ($receivedItems === []) {
                throw ValidationException::withMessages(['items' => __('Masukkan jumlah barang yang diterima.')]);
            }
            $receiving = GoodsReceiving::query()->create([
                'number' => 'GR-'.now()->format('ymdHis').'-'.Str::upper(Str::random(5)),
                'purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id,
                'warehouse_id' => $warehouse->id, 'received_by' => auth()->id(),
                'status' => 'completed', 'received_at' => now(), 'idempotency_key' => $idempotencyKey, 'notes' => $notes,
            ]);
            $payableAmount = 0;
            foreach ($receivedItems as $orderItemId => $row) {
                $item = $order->items->firstWhere('id', (int) $orderItemId);
                if (! $item) {
                    throw ValidationException::withMessages(['items' => __('Produk penerimaan tidak termasuk dalam PO ini.')]);
                }
                $quantity = FixedDecimal::quantity((string) ($row['quantity'] ?? '0'));
                $accepted = FixedDecimal::quantity((string) ($row['accepted'] ?? '0'));
                $ordered = FixedDecimal::quantity((string) $item->quantity_ordered);
                $previouslyReceived = FixedDecimal::quantity((string) $item->quantity_received);
                if ($quantity <= 0 || $accepted < 0 || $accepted > $quantity || $quantity + $previouslyReceived > $ordered) {
                    throw ValidationException::withMessages(['items' => __('Jumlah diterima harus positif, QC tidak boleh melebihi jumlah barang, dan total tidak boleh melampaui pesanan.')]);
                }
                $rejected = $quantity - $accepted;
                $product = $item->product;
                $batch = null;
                if ($accepted > 0 && $product->track_batches) {
                    $batchNumber = trim((string) ($row['batch_number'] ?? ''));
                    if ($batchNumber === '') {
                        throw ValidationException::withMessages(['items' => __('Nomor batch wajib diisi untuk produk yang melacak batch.')]);
                    }
                    $expiresAt = $row['expires_at'] ?? null;
                    if ($expiresAt && $expiresAt < today()->toDateString()) {
                        throw ValidationException::withMessages(['items' => __('Batch yang diterima sudah kedaluwarsa.')]);
                    }
                    $batch = ProductBatch::query()->firstOrCreate([
                        'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'batch_number' => $batchNumber,
                    ], ['expires_at' => $expiresAt ?: null, 'quantity_received' => 0, 'quantity_remaining' => 0, 'status' => 'active']);
                }
                $baseCost = FixedDecimal::money((string) $item->unit_cost);
                $acceptedCost = (int) round($baseCost * $accepted / 1000);
                $taxRate = (int) round((float) $item->tax_rate * 100);
                $tax = (int) round($acceptedCost * $taxRate / 10000);
                $payableAmount += $acceptedCost + $tax;
                $receivingItem = GoodsReceivingItem::query()->create([
                    'goods_receiving_id' => $receiving->id, 'purchase_order_item_id' => $item->id,
                    'product_id' => $product->id, 'product_batch_id' => $batch?->id,
                    'quantity' => FixedDecimal::quantityText($quantity), 'accepted_quantity' => FixedDecimal::quantityText($accepted),
                    'rejected_quantity' => FixedDecimal::quantityText($rejected), 'unit_cost' => $item->unit_cost,
                    'qc_status' => $rejected === 0 ? 'accepted' : ($accepted === 0 ? 'rejected' : 'partial'), 'qc_note' => $row['qc_note'] ?? null,
                ]);
                if ($accepted > 0 && $product->track_stock) {
                    $this->stock->mutate($product->id, $warehouse->id, FixedDecimal::quantityText($accepted), 'purchase_receiving',
                        'receiving:'.$idempotencyKey.':'.$item->id, $row['qc_note'] ?? null, $batch?->id, GoodsReceiving::class, $receiving->id);
                    if ($batch) {
                        $batch->quantity_received = FixedDecimal::quantityText(FixedDecimal::quantity((string) $batch->quantity_received) + $accepted);
                        $batch->save();
                    }
                }
                $item->quantity_received = FixedDecimal::quantityText($previouslyReceived + $quantity);
                $item->save();
            }
            $fullyReceived = $order->items()->get()->every(fn (PurchaseOrderItem $item) => FixedDecimal::quantity((string) $item->quantity_received) >= FixedDecimal::quantity((string) $item->quantity_ordered));
            $order->forceFill(['status' => $fullyReceived ? 'completed' : 'partial'])->save();
            if ($payableAmount > 0) {
                Payable::query()->create([
                    'supplier_id' => $order->supplier_id, 'goods_receiving_id' => $receiving->id,
                    'reference' => 'AP-'.$receiving->number, 'amount' => FixedDecimal::moneyText($payableAmount),
                    'paid_amount' => 0, 'status' => 'unpaid', 'notes' => $notes,
                ]);
            }
            $this->audit->record('goods_receiving.completed', $receiving, null, [
                'number' => $receiving->number, 'purchase_order_id' => $order->id,
                'accepted_payable' => FixedDecimal::moneyText($payableAmount), 'item_count' => count($receivedItems),
            ]);

            return $receiving->load('items', 'order');
        }, attempts: 3);
    }

    public function warehouseForUser(int $warehouseId): Warehouse
    {
        $warehouse = Warehouse::query()->whereKey($warehouseId)->where('is_active', true)->firstOrFail();
        if ($warehouse->outlet_id === null) {
            abort_unless(auth()->user()?->hasRole('super-admin') || auth()->user()?->can('warehouses-access'), 404);
        } else {
            app(OutletAccess::class)->require(auth()->user(), $warehouse->outlet_id);
        }

        return $warehouse;
    }
}
