<?php

namespace App\Services\Production;

use App\Models\RecipeVersion;
use App\Support\FixedDecimal;
use Illuminate\Validation\ValidationException;

class RecipeCostService
{
    /** Returns a two-decimal estimate from current master costs; historical production snapshots stay separate. */
    public function estimate(RecipeVersion $recipe): string
    {
        $recipe->loadMissing('items.ingredient', 'productItems.product.productUnits', 'productItems.unit');
        $costMicro = 0;
        foreach ($recipe->items as $item) {
            $ingredient = $item->ingredient;
            if (! $ingredient || ! $ingredient->is_active) {
                throw ValidationException::withMessages(['recipe' => __('Resep memiliki bahan nonaktif atau terhapus; perbaiki versi resep sebelum menghitung HPP.')]);
            }
            $quantityMilli = self::effectiveQuantityMilli(
                FixedDecimal::quantity((string) $item->quantity),
                (string) ($item->waste_percent ?? '0'),
            );
            $unitCostMicro = FixedDecimal::toScaled((string) $ingredient->buy_price, 8);
            if ($quantityMilli > intdiv(PHP_INT_MAX - 500, max(1, $unitCostMicro))) {
                throw new \InvalidArgumentException('Kuantitas resep terlalu besar untuk estimasi HPP yang aman.');
            }
            $costMicro += intdiv(($quantityMilli * $unitCostMicro) + 500, 1000);
        }
        foreach ($recipe->productItems as $item) {
            $product = $item->product;
            if (! $product || ! $product->is_active || ! $product->is_semi_finished) {
                throw ValidationException::withMessages(['recipe' => __('Resep memiliki produk setengah jadi yang tidak aktif.')]);
            }
            $unit = $product->productUnits->firstWhere('unit_id', $item->unit_id);
            $factor = $item->unit_id === $product->unit_id ? 1 : (float) ($unit?->conversion_factor ?? 0);
            if ($factor <= 0) {
                throw ValidationException::withMessages(['recipe' => __('Satuan produk setengah jadi pada resep tidak valid.')]);
            }
            $quantityMilli = (int) round(self::effectiveQuantityMilli(FixedDecimal::quantity((string) $item->quantity), (string) $item->waste_percent) * $factor);
            $unitCost = $unit?->buy_price !== null && (float) $unit->buy_price > 0
                ? $unit->buy_price
                : $product->buy_price;
            $unitCostMicro = FixedDecimal::toScaled((string) $unitCost, 8);
            if ($quantityMilli > intdiv(PHP_INT_MAX - 500, max(1, $unitCostMicro))) {
                throw new \InvalidArgumentException('Kuantitas produk terlalu besar untuk estimasi HPP yang aman.');
            }
            $costMicro += intdiv(($quantityMilli * $unitCostMicro) + 500, 1000);
        }

        $yieldMilli = FixedDecimal::quantity((string) $recipe->yield_quantity);
        if ($yieldMilli < 1) {
            throw ValidationException::withMessages(['yieldQuantity' => __('Hasil standar resep harus lebih dari nol.')]);
        }

        $centsDivisor = $yieldMilli * 1000;

        return FixedDecimal::moneyText(intdiv($costMicro + intdiv($centsDivisor, 2), $centsDivisor));
    }

    /** Scale a recipe quantity to a target output; round up to the smallest stock unit. */
    public static function requiredQuantityMilli(int $recipeQuantityMilli, int $targetMilli, int $yieldMilli): int
    {
        if ($recipeQuantityMilli < 0 || $targetMilli < 0 || $yieldMilli < 1
            || ($targetMilli > 0 && $recipeQuantityMilli > intdiv(PHP_INT_MAX - $yieldMilli + 1, $targetMilli))) {
            throw new \InvalidArgumentException('Kebutuhan resep melewati batas aman perhitungan.');
        }

        return intdiv(($recipeQuantityMilli * $targetMilli) + $yieldMilli - 1, $yieldMilli);
    }

    public static function effectiveQuantityMilli(int $quantityMilli, string $wastePercent): int
    {
        $wasteScaled = FixedDecimal::toScaled($wastePercent, 4);
        if ($wasteScaled === 0) {
            return $quantityMilli;
        }
        if ($quantityMilli > intdiv(PHP_INT_MAX - 500000, $wasteScaled)) {
            throw new \InvalidArgumentException('Susut resep melewati batas aman perhitungan.');
        }

        return $quantityMilli + intdiv(($quantityMilli * $wasteScaled) + 500000, 1000000);
    }
}
