<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockTransfer;
use App\Models\StockTransferBatchItem;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Support\AuditLogger;
use App\Support\FixedDecimal;
use App\Support\OutletAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function __construct(private readonly StockMutationService $stock, private readonly AuditLogger $audit) {}

    public function createDraft(int $sourceId, int $destinationId, array $items, ?string $notes = null): StockTransfer
    {
        abort_unless(auth()->user()?->can('transfers-create'), 403);
        if ($sourceId === $destinationId || $items === []) {
            throw ValidationException::withMessages(['items' => __('Pilih gudang berbeda dan tambahkan setidaknya satu produk.')]);
        }
        return DB::transaction(function () use ($sourceId, $destinationId, $items, $notes) {
            $source = $this->warehouseForUser($sourceId);
            $destination = $this->warehouseForUser($destinationId);
            $transfer = StockTransfer::query()->create([
                'number' => 'TR-'.now()->format('ymdHis').'-'.Str::upper(Str::random(5)),
                'source_warehouse_id' => $source->id, 'destination_warehouse_id' => $destination->id,
                'created_by' => auth()->id(), 'status' => 'draft', 'notes' => $notes, 'idempotency_key' => (string) Str::uuid(),
            ]);
            $seen = [];
            foreach ($items as $row) {
                $productId = (int) ($row['product_id'] ?? 0);
                $quantity = FixedDecimal::quantity((string) ($row['quantity'] ?? '0'));
                if (isset($seen[$productId]) || $quantity <= 0) {
                    throw ValidationException::withMessages(['items' => __('Produk tidak boleh duplikat dan jumlah harus positif.')]);
                }
                $seen[$productId] = true;
                $product = Product::query()->whereKey($productId)->where('is_active', true)->where('track_stock', true)->firstOrFail();
                StockTransferItem::query()->create([
                    'stock_transfer_id' => $transfer->id, 'product_id' => $product->id,
                    'quantity_sent' => FixedDecimal::quantityText($quantity), 'quantity_received' => 0,
                ]);
            }
            $this->audit->record('stock_transfer.draft_created', $transfer, null, ['number' => $transfer->number, 'source_warehouse_id' => $source->id, 'destination_warehouse_id' => $destination->id, 'item_count' => count($seen)]);
            return $transfer->load('items.product');
        }, attempts: 3);
    }

    public function send(int $transferId): StockTransfer
    {
        abort_unless(auth()->user()?->can('transfers-send'), 403);
        return DB::transaction(function () use ($transferId) {
            $transfer = StockTransfer::query()->with('items.product')->whereKey($transferId)->lockForUpdate()->firstOrFail();
            $source = $this->warehouseForUser($transfer->source_warehouse_id);
            $this->warehouseForUser($transfer->destination_warehouse_id);
            if ($transfer->status !== 'draft' || $transfer->items->isEmpty()) {
                throw ValidationException::withMessages(['transfer' => __('Hanya transfer draft yang dapat dikirim.')]);
            }
            foreach ($transfer->items as $item) {
                $quantity = FixedDecimal::quantity((string) $item->quantity_sent);
                $result = $this->stock->mutate($item->product_id, $source->id, '-'.FixedDecimal::quantityText($quantity), 'transfer_out',
                    'transfer:'.$transfer->id.':out:'.$item->id, $transfer->notes, null, StockTransfer::class, $transfer->id);
                foreach ($result['batch_allocations'] as $allocation) {
                    StockTransferBatchItem::query()->create([
                        'stock_transfer_item_id' => $item->id, 'product_batch_id' => $allocation['batch']->id,
                        'quantity_sent' => FixedDecimal::quantityText(abs($allocation['quantity_units'])), 'quantity_received' => 0,
                    ]);
                }
            }
            $transfer->forceFill(['status' => 'sent', 'sent_by' => auth()->id(), 'sent_at' => now()])->save();
            $this->audit->record('stock_transfer.sent', $transfer, ['status' => 'draft'], ['status' => 'sent']);
            return $transfer->refresh();
        }, attempts: 3);
    }

    public function receive(int $transferId, string $idempotencyKey): StockTransfer
    {
        abort_unless(auth()->user()?->can('transfers-receive'), 403);
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 96) {
            throw ValidationException::withMessages(['idempotencyKey' => __('Kunci penerimaan tidak valid.')]);
        }
        return DB::transaction(function () use ($transferId, $idempotencyKey) {
            $existing = StockTransfer::query()->where('receive_idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->id !== $transferId) throw ValidationException::withMessages(['idempotencyKey' => __('Kunci ini digunakan untuk transfer lain.')]);
                return $existing->load('items');
            }
            $transfer = StockTransfer::query()->with(['items.product', 'items.batches.batch'])->whereKey($transferId)->lockForUpdate()->firstOrFail();
            $this->warehouseForUser($transfer->source_warehouse_id);
            $destination = $this->warehouseForUser($transfer->destination_warehouse_id);
            if ($transfer->status !== 'sent') {
                throw ValidationException::withMessages(['transfer' => __('Transfer harus berstatus dikirim sebelum dapat diterima.')]);
            }
            foreach ($transfer->items as $item) {
                $quantity = FixedDecimal::quantity((string) $item->quantity_sent);
                if ($item->product->track_batches) {
                    $sum = $item->batches->sum(fn (StockTransferBatchItem $batchItem) => FixedDecimal::quantity((string) $batchItem->quantity_sent));
                    if ($sum !== $quantity) throw ValidationException::withMessages(['transfer' => __('Alokasi batch tidak cocok dengan jumlah transfer.')]);
                    foreach ($item->batches as $batchItem) {
                        $sourceBatch = $batchItem->batch;
                        if ($sourceBatch->expires_at?->isPast()) throw ValidationException::withMessages(['transfer' => __('Batch kedaluwarsa saat perjalanan harus diproses sebagai waste, bukan stok jual.')]);
                        $destinationBatch = ProductBatch::query()->firstOrCreate([
                            'product_id' => $item->product_id, 'warehouse_id' => $destination->id, 'batch_number' => $sourceBatch->batch_number,
                        ], ['manufactured_at' => $sourceBatch->manufactured_at, 'expires_at' => $sourceBatch->expires_at,
                            'quantity_received' => 0, 'quantity_remaining' => 0, 'status' => 'active']);
                        if ($destinationBatch->expires_at?->toDateString() !== $sourceBatch->expires_at?->toDateString()) {
                            throw ValidationException::withMessages(['transfer' => __('Nomor batch tujuan memiliki tanggal kedaluwarsa berbeda.')]);
                        }
                        $qty = FixedDecimal::quantity((string) $batchItem->quantity_sent);
                        $this->stock->mutate($item->product_id, $destination->id, FixedDecimal::quantityText($qty), 'transfer_in',
                            'transfer:'.$transfer->id.':in:'.$idempotencyKey.':'.$batchItem->id, $transfer->notes,
                            $destinationBatch->id, StockTransfer::class, $transfer->id);
                        $destinationBatch->quantity_received = FixedDecimal::quantityText(FixedDecimal::quantity((string) $destinationBatch->quantity_received) + $qty);
                        $destinationBatch->save();
                        $batchItem->quantity_received = $batchItem->quantity_sent;
                        $batchItem->save();
                    }
                } else {
                    $this->stock->mutate($item->product_id, $destination->id, FixedDecimal::quantityText($quantity), 'transfer_in',
                        'transfer:'.$transfer->id.':in:'.$idempotencyKey.':'.$item->id, $transfer->notes, null, StockTransfer::class, $transfer->id);
                }
                $item->quantity_received = $item->quantity_sent;
                $item->save();
            }
            $transfer->forceFill(['status' => 'completed', 'received_by' => auth()->id(), 'received_at' => now(), 'receive_idempotency_key' => $idempotencyKey])->save();
            $this->audit->record('stock_transfer.received', $transfer, ['status' => 'sent'], ['status' => 'completed', 'destination_warehouse_id' => $destination->id]);
            return $transfer->refresh();
        }, attempts: 3);
    }

    public function cancel(int $transferId): StockTransfer
    {
        abort_unless(auth()->user()?->can('transfers-cancel'), 403);
        return DB::transaction(function () use ($transferId) {
            $transfer = StockTransfer::query()->with(['items.product', 'items.batches.batch'])->whereKey($transferId)->lockForUpdate()->firstOrFail();
            $source = $this->warehouseForUser($transfer->source_warehouse_id);
            $this->warehouseForUser($transfer->destination_warehouse_id);
            if (! in_array($transfer->status, ['draft', 'sent'], true)) {
                throw ValidationException::withMessages(['transfer' => __('Transfer yang telah diterima atau dibatalkan tidak dapat diubah.')]);
            }
            if ($transfer->status === 'sent') {
                foreach ($transfer->items as $item) {
                    if ($item->product->track_batches) {
                        foreach ($item->batches as $batchItem) {
                            $batch = $batchItem->batch;
                            if ($batch->expires_at?->isPast()) throw ValidationException::withMessages(['transfer' => __('Batch kedaluwarsa tidak dapat dikembalikan ke stok jual; selesaikan sebagai waste.')]);
                            $qty = FixedDecimal::quantity((string) $batchItem->quantity_sent);
                            $this->stock->mutate($item->product_id, $source->id, FixedDecimal::quantityText($qty), 'transfer_cancel',
                                'transfer:'.$transfer->id.':cancel:'.$batchItem->id, $transfer->notes, $batch->id, StockTransfer::class, $transfer->id);
                            $batch->quantity_received = FixedDecimal::quantityText(FixedDecimal::quantity((string) $batch->quantity_received) + $qty);
                            $batch->save();
                        }
                    } else {
                        $qty = FixedDecimal::quantity((string) $item->quantity_sent);
                        $this->stock->mutate($item->product_id, $source->id, FixedDecimal::quantityText($qty), 'transfer_cancel',
                            'transfer:'.$transfer->id.':cancel:'.$item->id, $transfer->notes, null, StockTransfer::class, $transfer->id);
                    }
                }
            }
            $before = ['status' => $transfer->status];
            $transfer->forceFill(['status' => 'cancelled'])->save();
            $this->audit->record('stock_transfer.cancelled', $transfer, $before, ['status' => 'cancelled']);
            return $transfer->refresh();
        }, attempts: 3);
    }

    private function warehouseForUser(int $id): Warehouse
    {
        $warehouse = Warehouse::query()->whereKey($id)->where('is_active', true)->firstOrFail();
        if ($warehouse->outlet_id === null) {
            abort_unless(auth()->user()?->hasRole('super-admin') || auth()->user()?->can('warehouses-access'), 404);
        } else {
            app(OutletAccess::class)->require(auth()->user(), $warehouse->outlet_id);
        }
        return $warehouse;
    }
}
