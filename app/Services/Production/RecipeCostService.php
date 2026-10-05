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
        $recipe->loadMissing('items.ingredient');
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

        return FixedDecimal::moneyText((int) round($costMicro / 1000000));
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
