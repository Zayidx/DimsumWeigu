<?php

namespace App\Services\Production;

use App\Models\Ingredient;
use App\Models\IngredientGoodsReceiving;
use App\Models\IngredientGoodsReceivingItem;
use App\Models\IngredientPurchaseOrder;
use App\Models\Payable;
use App\Models\Product;
use App\Models\ProductionRequest;
use App\Models\ProductionRequestMaterial;
use App\Models\RecipeVersion;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\AuditLogger;
use App\Support\FixedDecimal;
use App\Support\OutletAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductionRequestService
{
    public function create(User $actor, int $outletId, int $warehouseId, array $lines, ?string $notes = null, ?string $idempotencyKey = null): ProductionRequest
    {
        $this->assertActor($actor);
        abort_unless($actor->can('production-requests-create'), 403);
        abort_unless($actor->can('warehouses-access'), 403);
        if (count($lines) < 1 || count($lines) > 100) {
            throw ValidationException::withMessages(['items' => __('Pilih 1 sampai 100 menu untuk permintaan produksi.')]);
        }

        $idempotencyKey ??= (string) Str::uuid();
        if (strlen($idempotencyKey) > 96) {
            throw ValidationException::withMessages(['idempotency_key' => __('Kunci permintaan tidak valid.')]);
        }
        $payloadHash = hash('sha256', json_encode([$actor->id, $outletId, $warehouseId, $lines, $notes], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $outletId, $warehouseId, $lines, $notes, $idempotencyKey, $payloadHash): ProductionRequest {
            $existing = ProductionRequest::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->payload_hash !== $payloadHash || (int) $existing->created_by !== $actor->id) {
                    throw ValidationException::withMessages(['idempotency_key' => __('Kunci permintaan sudah dipakai untuk data berbeda.')]);
                }
                app(OutletAccess::class)->require($actor, $existing->outlet_id);

                return $existing->load(['items.product', 'materials.ingredient']);
            }
            app(OutletAccess::class)->require($actor, $outletId);
            $warehouse = Warehouse::query()->whereKey($warehouseId)->where('is_active', true)->lockForUpdate()->firstOrFail();
            abort_unless($warehouse->type === 'central' || (int) $warehouse->outlet_id === $outletId, 403);
            $request = ProductionRequest::query()->create([
                'number' => 'PR-'.now()->format('Y').'-'.strtoupper(Str::random(10)), 'outlet_id' => $outletId,
                'warehouse_id' => $warehouseId, 'created_by' => $actor->id, 'status' => 'requested', 'notes' => $notes,
                'idempotency_key' => $idempotencyKey, 'payload_hash' => $payloadHash,
            ]);

            $requirements = [];
            foreach ($lines as $line) {
                $productId = (int) ($line['product_id'] ?? 0);
                $quantityMilli = $this->quantity((string) ($line['quantity'] ?? '0'));
                if ($quantityMilli < 1) {
                    throw ValidationException::withMessages(['items' => __('Jumlah produksi minimal 0,001.')]);
                }
                $product = Product::query()->whereKey($productId)->where('is_active', true)->where('is_semi_finished', true)->first();
                $recipe = $product ? RecipeVersion::query()->where('product_id', $productId)->where('unit_id', $product->unit_id)->where('is_active', true)->with('items.ingredient', 'productItems')->lockForUpdate()->first() : null;
                if (! $recipe || $recipe->items->isEmpty() || $recipe->productItems->isNotEmpty()) {
                    throw ValidationException::withMessages(['items' => __('Produk setengah jadi harus memiliki resep bahan baku aktif.')]);
                }
                $request->items()->create(['product_id' => $productId, 'recipe_version_id' => $recipe->id, 'quantity' => FixedDecimal::quantityText($quantityMilli)]);
                foreach ($recipe->items as $recipeItem) {
                    if (! $recipeItem->ingredient || ! $recipeItem->ingredient->is_active) {
                        throw ValidationException::withMessages(['items' => __('Resep mengandung bahan nonaktif atau terhapus.')]);
                    }
                    $recipeMilli = RecipeCostService::effectiveQuantityMilli(
                        FixedDecimal::quantity((string) $recipeItem->quantity),
                        (string) ($recipeItem->waste_percent ?? '0'),
                    );
                    if ($recipeMilli > intdiv(PHP_INT_MAX, $quantityMilli)) {
                        throw ValidationException::withMessages(['items' => __('Jumlah produksi melebihi batas aman perhitungan.')]);
                    }
                    $requiredMilli = intdiv(($recipeMilli * $quantityMilli) + 500, 1000);
                    $ingredientId = $recipeItem->ingredient_id;
                    if (($requirements[$ingredientId] ?? 0) > PHP_INT_MAX - $requiredMilli) {
                        throw ValidationException::withMessages(['items' => __('Total kebutuhan bahan terlalu besar untuk dihitung dengan aman.')]);
                    }
                    $requirements[$ingredientId] = ($requirements[$ingredientId] ?? 0) + $requiredMilli;
                }
            }

            foreach ($requirements as $ingredientId => $requiredMilli) {
                $ingredient = Ingredient::query()->whereKey($ingredientId)->lockForUpdate()->firstOrFail();
                DB::table('ingredient_warehouses')->insertOrIgnore(['ingredient_id' => $ingredientId, 'warehouse_id' => $warehouseId, 'stock' => '0.000', 'reserved_stock' => '0.000', 'created_at' => now(), 'updated_at' => now()]);
                $balance = DB::table('ingredient_warehouses')->where('ingredient_id', $ingredientId)->where('warehouse_id', $warehouseId)->lockForUpdate()->first();
                $onHandMilli = FixedDecimal::quantity((string) $balance->stock);
                $reservedMilli = FixedDecimal::quantity((string) $balance->reserved_stock);
                $availableMilli = max(0, $onHandMilli - $reservedMilli);
                $reservedForRequest = min($availableMilli, $requiredMilli);
                DB::table('ingredient_warehouses')->where('id', $balance->id)->update(['reserved_stock' => FixedDecimal::quantityText($reservedMilli + $reservedForRequest), 'updated_at' => now()]);
                $priceMicro = FixedDecimal::toScaled((string) $ingredient->buy_price, 8);
                $estimatedCents = self::costCents($requiredMilli, $priceMicro);
                ProductionRequestMaterial::query()->create([
                    'production_request_id' => $request->id, 'ingredient_id' => $ingredientId,
                    'required_quantity' => FixedDecimal::quantityText($requiredMilli),
                    'available_quantity' => FixedDecimal::quantityText($availableMilli),
                    'purchase_quantity' => FixedDecimal::quantityText(max(0, $requiredMilli - $reservedForRequest)),
                    'reserved_quantity' => FixedDecimal::quantityText($reservedForRequest),
                    'unit_cost' => (string) $ingredient->buy_price, 'estimated_cost' => FixedDecimal::moneyText($estimatedCents),
                ]);
            }
            app(AuditLogger::class)->record('production_request.created', $request, null, ['number' => $request->number, 'lines' => count($lines), 'materials' => count($requirements)]);

            return $request->load(['items.product', 'materials.ingredient']);
        });
    }

    /** Finance approves and issues one raw-material purchase order for the live shortage. */
    public function approveAndPurchase(User $actor, int $requestId, ?int $supplierId, ?string $invoiceReference = null, array $unitCosts = []): ?IngredientPurchaseOrder
    {
        abort_unless($actor->can('production-requests-approve'), 403);
        abort_unless($actor->can('purchase-orders-create'), 403);
        $this->assertActor($actor);

        return DB::transaction(function () use ($actor, $requestId, $supplierId, $invoiceReference, $unitCosts): ?IngredientPurchaseOrder {
            $request = ProductionRequest::query()->with('materials')->lockForUpdate()->findOrFail($requestId);
            app(OutletAccess::class)->require($actor, $request->outlet_id);
            if (! in_array($request->status, ['requested', 'purchasing'], true)) {
                throw ValidationException::withMessages(['request' => __('Permintaan ini sudah diproses.')]);
            }
            if (IngredientPurchaseOrder::query()->where('production_request_id', $request->id)->whereIn('status', ['ordered', 'partially_received'])->exists()) {
                throw ValidationException::withMessages(['request' => __('Purchase order berjalan harus diselesaikan penerimaannya sebelum membuat PO tambahan.')]);
            }
            $warehouse = Warehouse::query()->whereKey($request->warehouse_id)->where('is_active', true)->firstOrFail();
            $totalCents = 0;
            $purchaseLines = [];
            foreach ($request->materials as $material) {
                $ingredient = Ingredient::query()->whereKey($material->ingredient_id)->lockForUpdate()->firstOrFail();
                DB::table('ingredient_warehouses')->insertOrIgnore(['ingredient_id' => $ingredient->id, 'warehouse_id' => $warehouse->id, 'stock' => '0.000', 'reserved_stock' => '0.000', 'created_at' => now(), 'updated_at' => now()]);
                $balance = DB::table('ingredient_warehouses')->where('ingredient_id', $ingredient->id)->where('warehouse_id', $warehouse->id)->lockForUpdate()->first();
                $onHand = FixedDecimal::quantity((string) $balance->stock);
                $allReserved = FixedDecimal::quantity((string) $balance->reserved_stock);
                $ownReserved = FixedDecimal::quantity((string) $material->reserved_quantity);
                $free = max(0, $onHand - $allReserved);
                $extraReservation = min($free, max(0, FixedDecimal::quantity((string) $material->required_quantity) - $ownReserved));
                $available = $ownReserved + $extraReservation;
                if ($extraReservation > 0) {
                    DB::table('ingredient_warehouses')->where('id', $balance->id)->update(['reserved_stock' => FixedDecimal::quantityText($allReserved + $extraReservation), 'updated_at' => now()]);
                    $material->update(['reserved_quantity' => FixedDecimal::quantityText($available)]);
                }
                $needed = FixedDecimal::quantity((string) $material->required_quantity);
                $shortage = max(0, $needed - $available);
                $material->update(['available_quantity' => FixedDecimal::quantityText($available), 'purchase_quantity' => FixedDecimal::quantityText($shortage)]);
                if ($shortage === 0) {
                    continue;
                }
                $rawPrice = (string) ($unitCosts[$ingredient->id] ?? $ingredient->buy_price);
                try {
                    $priceMicro = FixedDecimal::toScaled($rawPrice, 8);
                } catch (\InvalidArgumentException) {
                    throw ValidationException::withMessages(['unitCosts.'.$ingredient->id => __('Harga harus angka dengan maksimal delapan desimal.')]);
                }
                $lineCents = self::costCents($shortage, $priceMicro);
                $purchaseLines[] = ['ingredient_id' => $ingredient->id, 'quantity' => $shortage, 'unit_cost' => FixedDecimal::fromScaled($priceMicro, 8), 'line_total' => FixedDecimal::moneyText($lineCents)];
                $totalCents += $lineCents;
            }
            if ($purchaseLines === []) {
                $request->update(['approved_by' => $actor->id, 'status' => 'approved']);
                app(AuditLogger::class)->record('production_request.approved_from_stock', $request, ['status' => $request->getOriginal('status')], ['status' => 'approved']);

                return null;
            }
            $supplier = Supplier::query()->whereKey($supplierId)->where('is_active', true)->firstOrFail();
            $order = IngredientPurchaseOrder::query()->create([
                'number' => 'BPO-'.now()->format('Y').'-'.strtoupper(Str::random(10)), 'production_request_id' => $request->id,
                'supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id, 'ordered_by' => $actor->id,
                'status' => 'ordered', 'invoice_reference' => $invoiceReference,
            ]);
            foreach ($purchaseLines as $line) {
                $order->items()->create([
                    'ingredient_id' => $line['ingredient_id'], 'quantity' => FixedDecimal::quantityText($line['quantity']),
                    'unit_cost' => $line['unit_cost'], 'line_total' => $line['line_total'],
                ]);
            }
            $order->update(['total' => FixedDecimal::moneyText($totalCents)]);
            $request->update(['approved_by' => $actor->id, 'status' => 'purchasing']);
            app(AuditLogger::class)->record('ingredient_purchase.ordered', $order, null, ['request_id' => $request->id, 'total' => $order->total]);

            return $order->load('items.ingredient');
        });
    }

    /** Confirms QC and records only accepted quantities in the warehouse ledger. */
    public function receive(User $actor, int $purchaseOrderId, array $receivedLines, ?string $invoiceReference = null, ?string $notes = null, ?string $idempotencyKey = null): IngredientGoodsReceiving
    {
        $this->assertActor($actor);
        abort_unless($actor->can('goods-receivings-create'), 403);
        abort_unless($actor->can('warehouses-access'), 403);
        $idempotencyKey ??= (string) Str::uuid();
        if (strlen($idempotencyKey) > 96) {
            throw ValidationException::withMessages(['idempotency_key' => __('Kunci penerimaan tidak valid.')]);
        }
        $payloadHash = hash('sha256', json_encode([$actor->id, $purchaseOrderId, $receivedLines, $invoiceReference, $notes], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $purchaseOrderId, $receivedLines, $invoiceReference, $notes, $idempotencyKey, $payloadHash): IngredientGoodsReceiving {
            $existing = IngredientGoodsReceiving::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->payload_hash !== $payloadHash || (int) $existing->received_by !== $actor->id) {
                    throw ValidationException::withMessages(['idempotency_key' => __('Kunci penerimaan sudah dipakai untuk data berbeda.')]);
                }
                $existingOrder = $existing->purchaseOrder()->with('request')->firstOrFail();
                app(OutletAccess::class)->require($actor, $existingOrder->request->outlet_id);

                return $existing->load('items');
            }
            $order = IngredientPurchaseOrder::query()->with('items')->lockForUpdate()->findOrFail($purchaseOrderId);
            app(OutletAccess::class)->require($actor, $order->request()->value('outlet_id'));
            if (! in_array($order->status, ['ordered', 'partially_received'], true)) {
                throw ValidationException::withMessages(['purchaseOrder' => __('Purchase order ini tidak dapat diterima lagi.')]);
            }
            $byId = collect($receivedLines)->keyBy(fn ($line) => (int) ($line['purchase_order_item_id'] ?? 0));
            if ($byId->count() !== count($receivedLines) || $byId->keys()->diff($order->items->pluck('id'))->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => __('Baris penerimaan tidak cocok dengan purchase order.')]);
            }
            $receiving = IngredientGoodsReceiving::query()->create([
                'number' => 'BGR-'.now()->format('Y').'-'.strtoupper(Str::random(10)), 'ingredient_purchase_order_id' => $order->id,
                'warehouse_id' => $order->warehouse_id, 'received_by' => $actor->id, 'status' => 'completed',
                'idempotency_key' => $idempotencyKey, 'payload_hash' => $payloadHash,
                'invoice_reference' => $invoiceReference ?: $order->invoice_reference, 'notes' => $notes, 'completed_at' => now(),
            ]);
            $totalCents = 0;
            foreach ($order->items as $purchaseItem) {
                $input = $byId->get($purchaseItem->id);
                if (! $input) {
                    continue;
                }
                $sent = $this->quantity((string) ($input['sent_quantity'] ?? '0'));
                $accepted = $this->quantity((string) ($input['accepted_quantity'] ?? '0'));
                $qc = (string) ($input['qc_status'] ?? 'good');
                if ($sent < 0 || $accepted < 0 || $accepted > $sent || ! in_array($qc, ['good', 'damaged', 'short', 'wrong_item', 'unusable'], true)) {
                    throw ValidationException::withMessages(['items' => __('Jumlah penerimaan atau status QC tidak valid.')]);
                }
                $orderedMilli = FixedDecimal::quantity((string) $purchaseItem->quantity);
                $previouslyAccepted = FixedDecimal::quantity((string) DB::table('ingredient_goods_receiving_items')->where('ingredient_purchase_order_item_id', $purchaseItem->id)->sum('accepted_quantity'));
                $remainingOrder = max(0, $orderedMilli - $previouslyAccepted);
                if ($accepted > $remainingOrder || $sent > $remainingOrder) {
                    throw ValidationException::withMessages(['items' => __('Jumlah diterima tidak boleh melebihi purchase order.')]);
                }
                if (in_array($qc, ['wrong_item', 'unusable'], true) && $accepted > 0) {
                    throw ValidationException::withMessages(['items' => __('Bahan salah atau tidak dapat dipakai tidak boleh menambah stok diterima.')]);
                }
                $receiptLine = IngredientGoodsReceivingItem::query()->create([
                    'ingredient_goods_receiving_id' => $receiving->id, 'ingredient_purchase_order_item_id' => $purchaseItem->id,
                    'ingredient_id' => $purchaseItem->ingredient_id, 'sent_quantity' => FixedDecimal::quantityText($sent),
                    'accepted_quantity' => FixedDecimal::quantityText($accepted), 'qc_status' => $qc,
                    'unit_cost' => (string) $purchaseItem->unit_cost, 'notes' => $input['notes'] ?? null,
                ]);
                if ($accepted > 0) {
                    $ingredient = Ingredient::query()->whereKey($purchaseItem->ingredient_id)->lockForUpdate()->firstOrFail();
                    $balance = DB::table('ingredient_warehouses')->where('ingredient_id', $ingredient->id)->where('warehouse_id', $order->warehouse_id)->lockForUpdate()->first();
                    $before = $balance ? FixedDecimal::quantity((string) $balance->stock) : 0;
                    $after = $before + $accepted;
                    DB::table('ingredient_warehouses')->updateOrInsert(
                        ['ingredient_id' => $ingredient->id, 'warehouse_id' => $order->warehouse_id],
                        ['stock' => FixedDecimal::quantityText($after), 'updated_at' => now(), 'created_at' => $balance?->created_at ?? now()],
                    );
                    DB::table('ingredient_inventory_ledgers')->insert([
                        'ingredient_id' => $ingredient->id, 'warehouse_id' => $order->warehouse_id, 'user_id' => $actor->id,
                        'type' => 'purchase_receipt', 'quantity_delta' => FixedDecimal::quantityText($accepted),
                        'quantity_before' => FixedDecimal::quantityText($before), 'quantity_after' => FixedDecimal::quantityText($after),
                        'reference_type' => IngredientGoodsReceiving::class, 'reference_id' => $receiving->id,
                        'idempotency_key' => 'BGR-'.$receiving->id.'-'.$purchaseItem->id, 'created_at' => now(),
                    ]);
                    $this->updateWeightedCost($ingredient, $accepted, FixedDecimal::toScaled((string) $purchaseItem->unit_cost, 8));
                    $requestMaterial = $order->request->materials()->where('ingredient_id', $ingredient->id)->lockForUpdate()->first();
                    if ($requestMaterial) {
                        $needed = FixedDecimal::quantity((string) $requestMaterial->required_quantity);
                        $reserved = FixedDecimal::quantity((string) $requestMaterial->reserved_quantity);
                        $reserveAdded = min($accepted, max(0, $needed - $reserved));
                        if ($reserveAdded > 0) {
                            $balanceNow = DB::table('ingredient_warehouses')->where('ingredient_id', $ingredient->id)->where('warehouse_id', $order->warehouse_id)->lockForUpdate()->first();
                            $reservedNow = FixedDecimal::quantity((string) $balanceNow->reserved_stock);
                            DB::table('ingredient_warehouses')->where('id', $balanceNow->id)->update(['reserved_stock' => FixedDecimal::quantityText($reservedNow + $reserveAdded), 'updated_at' => now()]);
                            $requestMaterial->update(['reserved_quantity' => FixedDecimal::quantityText($reserved + $reserveAdded)]);
                            $reserved += $reserveAdded;
                        }
                        $requestMaterial->update(['purchase_quantity' => FixedDecimal::quantityText(max(0, $needed - $reserved))]);
                    }
                    $totalCents += self::costCents($accepted, FixedDecimal::toScaled((string) $purchaseItem->unit_cost, 8));
                }
            }
            if ($receiving->items()->doesntExist()) {
                throw ValidationException::withMessages(['items' => __('Pilih minimal satu baris barang yang diterima.')]);
            }
            if ($totalCents > 0) {
                Payable::query()->create([
                    'supplier_id' => $order->supplier_id, 'ingredient_goods_receiving_id' => $receiving->id,
                    'reference' => $invoiceReference ?: $receiving->number, 'amount' => FixedDecimal::moneyText($totalCents),
                    'paid_amount' => '0.00', 'status' => 'unpaid', 'notes' => 'Pembelian bahan baku '.$order->number,
                ]);
            }
            $inspectedLines = DB::table('ingredient_goods_receiving_items')->whereIn('ingredient_purchase_order_item_id', $order->items->pluck('id'))->distinct()->count('ingredient_purchase_order_item_id');
            $order->update(['status' => $inspectedLines >= $order->items->count() ? 'received' : 'partially_received']);
            $request = $order->request;
            $stillShort = $request->materials()->whereColumn('reserved_quantity', '<', 'required_quantity')->exists();
            $request->update(['status' => $order->status === 'received' && ! $stillShort ? 'received' : 'purchasing']);
            app(AuditLogger::class)->record('ingredient_goods_receiving.completed', $receiving, null, ['purchase_order_id' => $order->id, 'accepted_cost' => FixedDecimal::moneyText($totalCents)]);

            return $receiving->load('items');
        });
    }

    private function updateWeightedCost(Ingredient $ingredient, int $receivedMilli, int $newUnitCostMicro): void
    {
        $existingStock = FixedDecimal::quantity((string) $ingredient->stock);
        $currentCost = FixedDecimal::toScaled((string) $ingredient->buy_price, 8);
        $totalStock = $existingStock + $receivedMilli;
        if ($totalStock > intdiv(PHP_INT_MAX, max(1, max($currentCost, $newUnitCostMicro)))) {
            throw ValidationException::withMessages(['amount' => __('Saldo bahan terlalu besar untuk menghitung biaya rata-rata dengan aman.')]);
        }
        $weighted = ($existingStock * $currentCost) + ($receivedMilli * $newUnitCostMicro);
        $average = intdiv($weighted, $totalStock);
        if ($weighted % $totalStock >= intdiv($totalStock + 1, 2)) {
            $average++;
        }
        $ingredient->update(['stock' => FixedDecimal::quantityText($totalStock), 'buy_price' => FixedDecimal::fromScaled($average, 8)]);
    }

    private static function costCents(int $quantityMilli, int $unitCostMicro): int
    {
        if ($quantityMilli < 0 || $unitCostMicro < 0 || $unitCostMicro > 0 && $quantityMilli > intdiv(PHP_INT_MAX - 500000000, $unitCostMicro)) {
            throw ValidationException::withMessages(['amount' => __('Jumlah atau biaya bahan terlalu besar untuk dihitung dengan aman.')]);
        }

        return intdiv(($quantityMilli * $unitCostMicro) + 500000000, 1000000000);
    }

    private function quantity(string $value): int
    {
        try {
            return FixedDecimal::quantity($value);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['quantity' => __('Jumlah harus berupa angka positif dengan maksimal tiga desimal.')]);
        }
    }

    private function assertActor(User $actor): void
    {
        abort_unless((int) auth()->id() === $actor->id, 403);
    }
}
