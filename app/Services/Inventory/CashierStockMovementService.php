<?php

namespace App\Services\Inventory;

use App\Models\CashierShift;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductWarehouse;
use App\Models\StockTransfer;
use App\Models\StockTransferBatchItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\AuditLogger;
use App\Support\FixedDecimal;
use App\Support\OutletAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CashierStockMovementService
{
    public function __construct(private readonly StockMutationService $stock, private readonly AuditLogger $audit) {}

    public function move(User $actor, int $outletId, string $direction, array $lines, ?string $notes, string $idempotencyKey, ?string $condition = null): StockTransfer
    {
        abort_unless(auth()->id() === $actor->id && $actor->can('cashier-stock-move'), 403);
        app(OutletAccess::class)->require($actor, $outletId);
        abort_unless((int) session('active_outlet_id') === $outletId, 403);
        if (! in_array($direction, ['pickup', 'return'], true) || $lines === [] || strlen($idempotencyKey) < 8 || strlen($idempotencyKey) > 96) {
            throw ValidationException::withMessages(['items' => __('Jenis perpindahan, produk, atau kunci transaksi tidak valid.')]);
        }
        $notes = trim((string) $notes);
        if (mb_strlen($notes) > 2000 || ($direction === 'return' && ($notes === '' || $condition !== 'good'))) {
            throw ValidationException::withMessages(['notes' => __('Pengembalian wajib beralasan dan hanya boleh berisi stok yang dinyatakan layak.')]);
        }
        $quantities = [];
        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            try {
                $quantity = FixedDecimal::quantity((string) ($line['quantity'] ?? '0'));
            } catch (\InvalidArgumentException) {
                throw ValidationException::withMessages(['items' => __('Jumlah harus positif dengan maksimal tiga desimal.')]);
            }
            if ($productId < 1 || $quantity < 1 || isset($quantities[$productId])) {
                throw ValidationException::withMessages(['items' => __('Produk harus unik dan jumlahnya positif.')]);
            }
            $quantities[$productId] = $quantity;
        }
        ksort($quantities);
        $payloadHash = hash('sha256', json_encode([$actor->id, $outletId, $direction, $quantities, $notes, $condition], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $outletId, $direction, $quantities, $notes, $idempotencyKey, $condition, $payloadHash): StockTransfer {
            $existing = StockTransfer::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->created_by !== $actor->id || $existing->payload_hash !== $payloadHash || $existing->status !== 'completed') {
                    throw ValidationException::withMessages(['idempotencyKey' => __('Kunci transaksi sudah dipakai untuk perpindahan berbeda.')]);
                }

                return $existing->load('items.product');
            }
            $central = Warehouse::query()->where('type', 'central')->whereNull('outlet_id')->where('is_active', true)->lockForUpdate()->firstOrFail();
            $shiftWarehouseId = CashierShift::query()->where('open_user_id', $actor->id)->where('outlet_id', $outletId)
                ->where('status', 'open')->latest('opened_at')->value('warehouse_id');
            $branch = Warehouse::query()->where('outlet_id', $outletId)->where('type', '!=', 'central')->where('is_active', true)
                ->when($shiftWarehouseId, fn ($query) => $query->whereKey($shiftWarehouseId))
                ->orderByDesc('is_sales_enabled')->orderBy('id')->lockForUpdate()->firstOrFail();
            $source = $direction === 'pickup' ? $central : $branch;
            $destination = $direction === 'pickup' ? $branch : $central;
            $transfer = StockTransfer::query()->create([
                'number' => 'TR-'.now()->format('ymdHis').'-'.Str::upper(Str::random(5)),
                'source_warehouse_id' => $source->id, 'destination_warehouse_id' => $destination->id,
                'created_by' => $actor->id, 'status' => 'draft', 'direction' => $direction,
                'condition' => $direction === 'return' ? $condition : null,
                'notes' => $notes ?: null, 'idempotency_key' => $idempotencyKey, 'payload_hash' => $payloadHash,
            ]);
            foreach ($quantities as $productId => $quantity) {
                $product = Product::query()->whereKey($productId)->where('is_active', true)->where('track_stock', true)->where('is_semi_finished', true)->firstOrFail();
                $balance = ProductWarehouse::query()->where('product_id', $product->id)->where('warehouse_id', $source->id)->lockForUpdate()->first();
                $available = $balance
                    ? FixedDecimal::quantity((string) $balance->stock) - FixedDecimal::quantity((string) $balance->reserved_stock)
                    : 0;
                if ($available < $quantity) {
                    throw ValidationException::withMessages(['items' => __('Stok tersedia :product di :warehouse tidak cukup.', ['product' => $product->title, 'warehouse' => $source->name])]);
                }
                $item = $transfer->items()->create([
                    'product_id' => $product->id,
                    'quantity_sent' => FixedDecimal::quantityText($quantity),
                    'quantity_received' => '0.000',
                ]);
                $movement = $direction === 'pickup' ? 'cashier_pickup' : 'cashier_return';
                $out = $this->stock->mutate($product->id, $source->id, '-'.FixedDecimal::quantityText($quantity), $movement.'_out',
                    'cashier-transfer:'.$transfer->id.':out:'.$item->id, $notes ?: null, null, StockTransfer::class, $transfer->id);
                if ($product->track_batches) {
                    foreach ($out['batch_allocations'] as $allocation) {
                        $sourceBatch = $allocation['batch'];
                        $moved = abs($allocation['quantity_units']);
                        $destinationBatch = ProductBatch::query()->firstOrCreate([
                            'product_id' => $product->id, 'warehouse_id' => $destination->id, 'batch_number' => $sourceBatch->batch_number,
                        ], [
                            'manufactured_at' => $sourceBatch->manufactured_at, 'expires_at' => $sourceBatch->expires_at,
                            'quantity_received' => '0.000', 'quantity_remaining' => '0.000', 'status' => 'active',
                        ]);
                        if ($destinationBatch->expires_at?->toDateString() !== $sourceBatch->expires_at?->toDateString()) {
                            throw ValidationException::withMessages(['items' => __('Tanggal kedaluwarsa batch tujuan tidak sesuai.')]);
                        }
                        $this->stock->mutate($product->id, $destination->id, FixedDecimal::quantityText($moved), $movement.'_in',
                            'cashier-transfer:'.$transfer->id.':in:'.$item->id.':'.$sourceBatch->id, $notes ?: null,
                            $destinationBatch->id, StockTransfer::class, $transfer->id);
                        $destinationBatch->quantity_received = FixedDecimal::quantityText(FixedDecimal::quantity((string) $destinationBatch->quantity_received) + $moved);
                        $destinationBatch->save();
                        StockTransferBatchItem::query()->create([
                            'stock_transfer_item_id' => $item->id, 'product_batch_id' => $sourceBatch->id,
                            'quantity_sent' => FixedDecimal::quantityText($moved), 'quantity_received' => FixedDecimal::quantityText($moved),
                        ]);
                    }
                } else {
                    $this->stock->mutate($product->id, $destination->id, FixedDecimal::quantityText($quantity), $movement.'_in',
                        'cashier-transfer:'.$transfer->id.':in:'.$item->id, $notes ?: null, null, StockTransfer::class, $transfer->id);
                }
                $item->quantity_received = FixedDecimal::quantityText($quantity);
                $item->save();
            }
            $transfer->update(['status' => 'completed', 'sent_by' => $actor->id, 'received_by' => $actor->id, 'sent_at' => now(), 'received_at' => now()]);
            $this->audit->record('stock_transfer.cashier_'.$direction, $transfer, null, ['outlet_id' => $outletId, 'direction' => $direction, 'items' => count($quantities)]);

            return $transfer->load('items.product');
        }, attempts: 3);
    }
}
