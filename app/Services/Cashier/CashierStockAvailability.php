<?php

namespace App\Services\Cashier;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\RecipeVersion;
use App\Services\Production\RecipeCostService;
use App\Support\FixedDecimal;
use Illuminate\Support\Facades\DB;

class CashierStockAvailability
{
    /** Return the sellable quantity in thousandths; null means the menu is not stock-tracked. */
    public function forSale(Product $product, int $unitId, int $warehouseId): array
    {
        $recipe = RecipeVersion::query()->where('product_id', $product->id)->where('unit_id', $unitId)->where('is_active', true)
            ->with(['items.ingredient', 'productItems.product.productUnits'])->first();
        $hasAssemblyRecipe = RecipeVersion::query()->where('product_id', $product->id)->where('is_active', true)->whereHas('productItems')->exists();

        if ($hasAssemblyRecipe && ! $recipe?->productItems->isNotEmpty()) {
            return ['quantity_milli' => 0, 'kind' => 'recipe', 'limiting_name' => 'Resep untuk satuan ini'];
        }

        if ($recipe?->productItems->isNotEmpty()) {
            return $this->recipeAvailability($recipe, $warehouseId);
        }

        if (! $product->track_stock) {
            return ['quantity_milli' => null, 'kind' => 'untracked', 'limiting_name' => null];
        }

        $balance = DB::table('product_warehouses')->where('product_id', $product->id)->where('warehouse_id', $warehouseId)->first(['stock', 'reserved_stock']);
        $available = $balance ? max(0, FixedDecimal::quantity((string) $balance->stock) - FixedDecimal::quantity((string) $balance->reserved_stock)) : 0;
        if ($product->track_batches) {
            $batchStock = ProductBatch::query()->where('product_id', $product->id)->where('warehouse_id', $warehouseId)->where('status', 'active')
                ->where(fn ($dates) => $dates->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
                ->get(['quantity_remaining'])->sum(fn (ProductBatch $batch) => FixedDecimal::quantity((string) $batch->quantity_remaining));
            $available = min($available, $batchStock);
        }

        $unit = ProductUnit::query()->where('product_id', $product->id)->where('unit_id', $unitId)->where('is_base', $unitId === $product->unit_id)->first();
        $factor = (float) ($unit?->conversion_factor ?? 1);
        $quantityMilli = $factor > 0 ? (int) floor(($available / $factor) * 1000) : 0;

        return ['quantity_milli' => max(0, $quantityMilli), 'kind' => 'stock', 'limiting_name' => $product->title];
    }

    private function recipeAvailability(RecipeVersion $recipe, int $warehouseId): array
    {
        $yieldMilli = FixedDecimal::quantity((string) $recipe->yield_quantity);
        if ($yieldMilli <= 0) {
            return ['quantity_milli' => 0, 'kind' => 'recipe', 'limiting_name' => 'Hasil resep belum valid'];
        }

        $availableSalesMilli = PHP_INT_MAX;
        $limitingName = null;

        foreach ($recipe->items as $item) {
            $ingredient = $item->ingredient;
            if (! $ingredient || ! $ingredient->is_active) {
                return ['quantity_milli' => 0, 'kind' => 'recipe', 'limiting_name' => $ingredient?->name ?? 'Bahan resep'];
            }
            $perSale = (int) intdiv((FixedDecimal::quantity((string) $item->quantity) * 1000) + intdiv($yieldMilli, 2), $yieldMilli);
            $perSale = RecipeCostService::effectiveQuantityMilli($perSale, (string) ($item->waste_percent ?? '0'));
            if ($perSale <= 0) {
                continue;
            }
            $balance = DB::table('ingredient_warehouses')->where('ingredient_id', $ingredient->id)->where('warehouse_id', $warehouseId)->first(['stock', 'reserved_stock']);
            $warehouseAvailable = $balance ? max(0, FixedDecimal::quantity((string) $balance->stock) - FixedDecimal::quantity((string) $balance->reserved_stock)) : 0;
            $available = min($warehouseAvailable, FixedDecimal::quantity((string) $ingredient->stock));
            $sales = (int) intdiv($available * 1000, $perSale);
            if ($sales < $availableSalesMilli) {
                $availableSalesMilli = $sales;
                $limitingName = $ingredient->name;
            }
        }

        foreach ($recipe->productItems as $item) {
            $component = $item->product;
            if (! $component || ! $component->is_active || ! $component->is_semi_finished) {
                return ['quantity_milli' => 0, 'kind' => 'recipe', 'limiting_name' => $component?->title ?? 'Komponen resep'];
            }
            $unit = $component->productUnits->firstWhere('unit_id', $item->unit_id);
            $factor = $item->unit_id === $component->unit_id ? 1 : (float) ($unit?->conversion_factor ?? 0);
            if ($factor <= 0) {
                return ['quantity_milli' => 0, 'kind' => 'recipe', 'limiting_name' => $component->title];
            }
            $componentQty = RecipeCostService::effectiveQuantityMilli(FixedDecimal::quantity((string) $item->quantity), (string) $item->waste_percent);
            $required = (int) round(($componentQty * 1000 / $yieldMilli) * $factor);
            if ($required <= 0) {
                continue;
            }
            $balance = DB::table('product_warehouses')->where('product_id', $component->id)->where('warehouse_id', $warehouseId)->first(['stock', 'reserved_stock']);
            $available = $balance ? max(0, FixedDecimal::quantity((string) $balance->stock) - FixedDecimal::quantity((string) $balance->reserved_stock)) : 0;
            if ($component->track_batches) {
                $batchStock = ProductBatch::query()->where('product_id', $component->id)->where('warehouse_id', $warehouseId)->where('status', 'active')
                    ->where(fn ($dates) => $dates->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
                    ->get(['quantity_remaining'])->sum(fn (ProductBatch $batch) => FixedDecimal::quantity((string) $batch->quantity_remaining));
                $available = min($available, $batchStock);
            }
            $sales = (int) intdiv($available * 1000, $required);
            if ($sales < $availableSalesMilli) {
                $availableSalesMilli = $sales;
                $limitingName = $component->title;
            }
        }

        if ($availableSalesMilli === PHP_INT_MAX) {
            $availableSalesMilli = 0;
        }

        return ['quantity_milli' => $availableSalesMilli, 'kind' => 'recipe', 'limiting_name' => $limitingName];
    }
}
