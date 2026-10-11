<?php

namespace App\Services\Production;

use App\Models\Ingredient;
use App\Models\ProductBatch;
use App\Models\ProductionOrder;
use App\Models\ProductionRequest;
use App\Models\ProductionShift;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockMutationService;
use App\Support\AuditLogger;
use App\Support\FixedDecimal;
use App\Support\OutletAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductionOrderService
{
    public function startFromRequest(User $actor, int $requestId, ?int $productionShiftId = null): ProductionOrder
    {
        $this->assertActor($actor);
        abort_unless($actor->can('production-orders-create'), 403);

        return DB::transaction(function () use ($actor, $requestId, $productionShiftId): ProductionOrder {
            $request = ProductionRequest::query()->with(['items.recipe.items.ingredient'])->lockForUpdate()->findOrFail($requestId);
            app(OutletAccess::class)->require($actor, $request->outlet_id);
            $warehouse = Warehouse::query()->whereKey($request->warehouse_id)->where('is_active', true)->firstOrFail();
            abort_unless($warehouse->type === 'central' && $warehouse->outlet_id === null, 403, __('Produksi hanya dapat dilakukan di Gudang Bersama.'));
            $shift = $productionShiftId ? ProductionShift::query()->whereKey($productionShiftId)->where('warehouse_id', $warehouse->id)
                ->where('outlet_id', $request->outlet_id)->where('user_id', $actor->id)->where('status', 'open')->lockForUpdate()->firstOrFail() : null;
            if (! $shift) {
                throw ValidationException::withMessages(['shift' => __('Buka shift produksi sebelum memulai produksi.')]);
            }
            $existing = ProductionOrder::query()->where('production_request_id', $request->id)->first();
            if ($existing) {
                return $existing->load(['items.product', 'items.materials.ingredient']);
            }
            if (! in_array($request->status, ['approved', 'received'], true)) {
                throw ValidationException::withMessages(['request' => __('Permintaan harus disetujui dan seluruh bahan harus diterima sebelum produksi.')]);
            }
            $order = ProductionOrder::query()->create([
                'number' => 'PROD-'.now()->format('Y').'-'.strtoupper(Str::random(10)), 'production_request_id' => $request->id,
                'production_shift_id' => $productionShiftId,
                'outlet_id' => $request->outlet_id, 'warehouse_id' => $request->warehouse_id,
                'created_by' => $actor->id, 'status' => 'in_production', 'started_at' => now(), 'notes' => $request->notes,
            ]);
            foreach ($request->items as $requestItem) {
                $recipe = $requestItem->recipe;
                if (! $recipe || $recipe->items->isEmpty()) {
                    throw ValidationException::withMessages(['recipe' => __('Versi resep pada permintaan tidak tersedia.')]);
                }
                $plannedOutput = FixedDecimal::quantity((string) $requestItem->quantity);
                $orderItem = $order->items()->create([
                    'product_id' => $requestItem->product_id, 'recipe_version_id' => $recipe->id,
                    'planned_quantity' => FixedDecimal::quantityText($plannedOutput), 'produced_quantity' => '0.000', 'unit_cost' => '0.00',
                ]);
                foreach ($recipe->items as $recipeItem) {
                    $unitQuantity = RecipeCostService::effectiveQuantityMilli(
                        FixedDecimal::quantity((string) $recipeItem->quantity),
                        (string) ($recipeItem->waste_percent ?? '0'),
                    );
                    try {
                        $plannedMaterial = RecipeCostService::requiredQuantityMilli(
                            $unitQuantity, $plannedOutput, FixedDecimal::quantity((string) $recipe->yield_quantity),
                        );
                    } catch (\InvalidArgumentException) {
                        throw ValidationException::withMessages(['materials' => __('Kebutuhan bahan atau hasil standar resep tidak valid.')]);
                    }
                    $ingredient = Ingredient::query()->whereKey($recipeItem->ingredient_id)->lockForUpdate()->firstOrFail();
                    $orderItem->materials()->create([
                        'production_order_id' => $order->id, 'recipe_version_id' => $recipe->id,
                        'ingredient_id' => $ingredient->id, 'planned_quantity' => FixedDecimal::quantityText($plannedMaterial),
                        'actual_quantity' => '0.000', 'remaining_quantity' => FixedDecimal::quantityText($plannedMaterial),
                        'unit_cost_snapshot' => (string) $ingredient->buy_price, 'actual_cost' => '0.00',
                    ]);
                }
            }
            $requiredByIngredient = $order->items()->with('materials')->get()->flatMap->materials
                ->groupBy('ingredient_id')->map(fn ($rows) => $rows->sum(fn ($row) => FixedDecimal::quantity((string) $row->planned_quantity)));
            foreach ($requiredByIngredient as $ingredientId => $required) {
                $balance = DB::table('ingredient_warehouses')->where('ingredient_id', $ingredientId)
                    ->where('warehouse_id', $warehouse->id)->lockForUpdate()->first();
                if (! $balance) {
                    throw ValidationException::withMessages(['stock' => __('Saldo bahan gudang tidak ditemukan.')]);
                }
                $onHand = FixedDecimal::quantity((string) $balance->stock);
                $reservedAll = FixedDecimal::quantity((string) $balance->reserved_stock);
                $ownReserved = FixedDecimal::quantity((string) ($request->materials()->where('ingredient_id', $ingredientId)->value('reserved_quantity') ?? '0'));
                if ($required > $onHand - max(0, $reservedAll - $ownReserved)) {
                    throw ValidationException::withMessages(['stock' => __('Stok bahan tidak cukup untuk memulai produksi. Periksa ulang rencana atau ajukan restock.')]);
                }
                DB::table('ingredient_warehouses')->where('id', $balance->id)->update([
                    'stock' => FixedDecimal::quantityText($onHand - $required),
                    'reserved_stock' => FixedDecimal::quantityText($reservedAll - $ownReserved), 'updated_at' => now(),
                ]);
                $request->materials()->where('ingredient_id', $ingredientId)->update(['reserved_quantity' => '0.000']);
                $ingredient = Ingredient::query()->whereKey($ingredientId)->lockForUpdate()->firstOrFail();
                $global = FixedDecimal::quantity((string) $ingredient->stock);
                if ($required > $global) {
                    throw ValidationException::withMessages(['stock' => __('Saldo bahan total tidak mencukupi.')]);
                }
                $ingredient->update(['stock' => FixedDecimal::quantityText($global - $required)]);
                DB::table('ingredient_inventory_ledgers')->insert([
                    'ingredient_id' => $ingredientId, 'warehouse_id' => $warehouse->id, 'user_id' => $actor->id,
                    'type' => 'production_consumption', 'quantity_delta' => FixedDecimal::quantityText(-$required),
                    'quantity_before' => FixedDecimal::quantityText($onHand), 'quantity_after' => FixedDecimal::quantityText($onHand - $required),
                    'reference_type' => ProductionOrder::class, 'reference_id' => $order->id,
                    'idempotency_key' => 'PROD-START-'.$order->id.'-'.$ingredientId, 'created_at' => now(),
                ]);
            }
            foreach ($order->items as $item) {
                $item->materials()->update(['actual_quantity' => DB::raw('planned_quantity'), 'remaining_quantity' => '0.000']);
            }
            $request->update(['status' => 'in_production']);
            app(AuditLogger::class)->record('production_order.started', $order, null, ['request_id' => $request->id, 'number' => $order->number]);

            return $order->load(['items.product', 'items.materials.ingredient']);
        });
    }

    /** Consume actual quantities, release this request's reservations, and add actual output to the central warehouse. */
    public function complete(User $actor, int $orderId, array $actualMaterials, array $outputs): ProductionOrder
    {
        $this->assertActor($actor);
        abort_unless($actor->can('production-orders-complete'), 403);

        return DB::transaction(function () use ($actor, $orderId, $actualMaterials, $outputs): ProductionOrder {
            $order = ProductionOrder::query()->with(['items.materials.ingredient', 'request.materials'])->lockForUpdate()->findOrFail($orderId);
            app(OutletAccess::class)->require($actor, $order->outlet_id);
            $warehouse = Warehouse::query()->whereKey($order->warehouse_id)->where('is_active', true)->firstOrFail();
            abort_unless($warehouse->type === 'central' && $warehouse->outlet_id === null, 403, __('Produksi hanya dapat diselesaikan di Gudang Bersama.'));
            if ($order->status === 'completed') {
                return $order;
            }
            if ($order->status !== 'in_production') {
                throw ValidationException::withMessages(['order' => __('Production order tidak sedang berjalan.')]);
            }
            $materialInputs = collect($actualMaterials)->keyBy(fn ($line) => (int) ($line['production_order_material_id'] ?? 0));
            $materialRows = $order->items->flatMap->materials;
            if ($materialInputs->count() !== count($actualMaterials) || $materialInputs->keys()->diff($materialRows->pluck('id'))->isNotEmpty() || $materialRows->count() !== $materialInputs->count()) {
                throw ValidationException::withMessages(['materials' => __('Masukkan konsumsi aktual untuk setiap bahan produksi.')]);
            }
            $outputInputs = collect($outputs)->keyBy(fn ($line) => (int) ($line['production_order_item_id'] ?? 0));
            if ($outputInputs->count() !== count($outputs) || $outputInputs->keys()->diff($order->items->pluck('id'))->isNotEmpty() || $order->items->count() !== $outputInputs->count()) {
                throw ValidationException::withMessages(['outputs' => __('Masukkan hasil produksi untuk setiap menu.')]);
            }

            $actualByIngredient = [];
            $plannedByIngredient = [];
            $costByOrderItem = [];
            foreach ($materialRows as $material) {
                $input = $materialInputs->get($material->id);
                $actual = $this->quantity((string) ($input['actual_quantity'] ?? ''));
                $ingredientId = $material->ingredient_id;
                $actualByIngredient[$ingredientId] = ($actualByIngredient[$ingredientId] ?? 0) + $actual;
                $plannedByIngredient[$ingredientId] = ($plannedByIngredient[$ingredientId] ?? 0) + FixedDecimal::quantity((string) $material->planned_quantity);
                $priceMicro = FixedDecimal::toScaled((string) $material->unit_cost_snapshot, 8);
                $lineCents = self::costCents($actual, $priceMicro);
                $costByOrderItem[$material->production_order_item_id] = ($costByOrderItem[$material->production_order_item_id] ?? 0) + $lineCents;
                $material->update([
                    'actual_quantity' => FixedDecimal::quantityText($actual),
                    'remaining_quantity' => FixedDecimal::quantityText(max(0, FixedDecimal::quantity((string) $material->planned_quantity) - $actual)),
                    'actual_unit_cost' => (string) $material->unit_cost_snapshot, 'actual_cost' => FixedDecimal::moneyText($lineCents),
                ]);
            }

            foreach ($actualByIngredient as $ingredientId => $consumed) {
                $balance = DB::table('ingredient_warehouses')->where('ingredient_id', $ingredientId)->where('warehouse_id', $order->warehouse_id)->lockForUpdate()->first();
                if (! $balance) {
                    throw ValidationException::withMessages(['stock' => __('Saldo bahan gudang tidak ditemukan.')]);
                }
                $onHand = FixedDecimal::quantity((string) $balance->stock);
                $reservedAll = FixedDecimal::quantity((string) $balance->reserved_stock);
                $requestMaterial = $order->request->materials->firstWhere('ingredient_id', $ingredientId);
                $reservedOwn = $requestMaterial ? FixedDecimal::quantity((string) $requestMaterial->reserved_quantity) : 0;
                $legacy = $order->started_at === null;
                $reservedOthers = $legacy ? max(0, $reservedAll - $reservedOwn) : $reservedAll;
                $delta = $consumed - ($legacy ? 0 : $plannedByIngredient[$ingredientId]);
                if ($delta > $onHand - $reservedOthers) {
                    throw ValidationException::withMessages(['stock' => __('Stok bahan tidak cukup setelah memperhitungkan reservasi permintaan lain.')]);
                }
                $after = $onHand - $delta;
                $reservedAfter = $reservedOthers;
                DB::table('ingredient_warehouses')->where('id', $balance->id)->update([
                    'stock' => FixedDecimal::quantityText($after), 'reserved_stock' => FixedDecimal::quantityText($reservedAfter), 'updated_at' => now(),
                ]);
                $ingredient = Ingredient::query()->whereKey($ingredientId)->lockForUpdate()->firstOrFail();
                $globalBefore = FixedDecimal::quantity((string) $ingredient->stock);
                if ($delta > $globalBefore) {
                    throw ValidationException::withMessages(['stock' => __('Saldo bahan total tidak cukup untuk mencatat konsumsi.')]);
                }
                $ingredient->update(['stock' => FixedDecimal::quantityText($globalBefore - $delta)]);
                if ($delta !== 0) {
                    DB::table('ingredient_inventory_ledgers')->insert([
                        'ingredient_id' => $ingredientId, 'warehouse_id' => $order->warehouse_id, 'user_id' => $actor->id,
                        'type' => $delta > 0 ? 'production_consumption' : 'production_adjustment',
                        'quantity_delta' => FixedDecimal::quantityText(-$delta),
                        'quantity_before' => FixedDecimal::quantityText($onHand), 'quantity_after' => FixedDecimal::quantityText($after),
                        'reference_type' => ProductionOrder::class, 'reference_id' => $order->id,
                        'idempotency_key' => 'PROD-FINISH-'.$order->id.'-'.$ingredientId, 'created_at' => now(),
                    ]);
                }
            }

            foreach ($order->items as $item) {
                $outputInput = $outputInputs->get($item->id);
                $output = $this->quantity((string) ($outputInput['actual_output'] ?? ''));
                $planned = FixedDecimal::quantity((string) $item->planned_quantity);
                $varianceNote = trim((string) ($outputInput['variance_note'] ?? ''));
                if ($output !== $planned && $varianceNote === '') {
                    throw ValidationException::withMessages(['outputs' => __('Catatan selisih wajib diisi jika hasil aktual berbeda dari target.')]);
                }
                $costCents = $costByOrderItem[$item->id] ?? 0;
                if ($costCents > intdiv(PHP_INT_MAX, 1000)) {
                    throw ValidationException::withMessages(['outputs' => __('Biaya produksi melebihi batas aman.')]);
                }
                $unitCostCents = $output > 0 ? intdiv(($costCents * 1000) + intdiv($output, 2), $output) : 0;
                $item->update([
                    'produced_quantity' => FixedDecimal::quantityText($output), 'unit_cost' => FixedDecimal::moneyText($unitCostCents),
                    'variance_quantity' => FixedDecimal::quantityText($output - $planned),
                    'yield_percent' => number_format($planned > 0 ? ($output / $planned) * 100 : 0, 2, '.', ''),
                    'variance_note' => $varianceNote ?: null,
                ]);
                if ($output === 0) {
                    continue;
                }
                $product = $item->product()->lockForUpdate()->firstOrFail();
                $batchId = null;
                if ($product->track_batches) {
                    $batch = ProductBatch::query()->create([
                        'product_id' => $product->id, 'warehouse_id' => $order->warehouse_id,
                        'batch_number' => 'PROD-'.$order->id.'-'.$item->id, 'quantity_received' => 0,
                        'quantity_remaining' => 0, 'status' => 'active',
                    ]);
                    $batchId = $batch->id;
                }
                app(StockMutationService::class)->mutate(
                    $product->id, $order->warehouse_id, FixedDecimal::quantityText($output), 'production_output',
                    'PROD-OUT-'.$order->id.'-'.$item->id, 'Hasil production order '.$order->number,
                    $batchId, ProductionOrder::class, $order->id,
                );
                $product->refresh()->update(['buy_price' => FixedDecimal::moneyText($unitCostCents)]);
                $product->productUnits()->where('unit_id', $product->unit_id)->update(['buy_price' => FixedDecimal::moneyText($unitCostCents)]);
            }

            $order->update(['status' => 'completed', 'completed_by' => $actor->id, 'completed_at' => now()]);
            $order->request()->update(['status' => 'completed']);
            app(AuditLogger::class)->record('production_order.completed', $order, ['status' => 'in_production'], [
                'status' => 'completed', 'outputs' => $order->items->fresh()->map(fn ($item) => ['product_id' => $item->product_id, 'quantity' => $item->produced_quantity, 'unit_cost' => $item->unit_cost])->all(),
            ]);

            return $order->fresh(['items.product', 'items.materials.ingredient']);
        });
    }

    private function quantity(string $value): int
    {
        try {
            return FixedDecimal::quantity($value);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['quantity' => __('Jumlah harus berupa angka dengan maksimal tiga desimal.')]);
        }
    }

    private static function costCents(int $quantityMilli, int $unitCostMicro): int
    {
        if ($quantityMilli < 0 || $unitCostMicro < 0 || ($unitCostMicro > 0 && $quantityMilli > intdiv(PHP_INT_MAX - 500000000, $unitCostMicro))) {
            throw ValidationException::withMessages(['amount' => __('Biaya bahan melebihi batas aman perhitungan.')]);
        }

        return intdiv(($quantityMilli * $unitCostMicro) + 500000000, 1000000000);
    }

    private function assertActor(User $actor): void
    {
        abort_unless((int) auth()->id() === $actor->id, 403);
    }
}
