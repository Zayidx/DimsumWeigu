<?php

namespace App\Services\Cashier;

use App\Models\Ingredient;
use App\Models\Transaction;
use App\Support\FixedDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IngredientSaleConsumptionService
{
    public function consume(Ingredient $ingredient, int $warehouseId, int $quantityMilli, Transaction $transaction, string $key): void
    {
        if ($quantityMilli <= 0) {
            return;
        }
        DB::transaction(function () use ($ingredient, $warehouseId, $quantityMilli, $transaction, $key): void {
            $ingredient = Ingredient::query()->whereKey($ingredient->id)->lockForUpdate()->firstOrFail();
            $balance = DB::table('ingredient_warehouses')->where('ingredient_id', $ingredient->id)->where('warehouse_id', $warehouseId)->lockForUpdate()->first();
            if (! $balance) {
                throw ValidationException::withMessages(['stock' => __('Stok bahan :name tidak tersedia di outlet.', ['name' => $ingredient->name])]);
            }
            $before = FixedDecimal::quantity((string) $balance->stock);
            $reserved = FixedDecimal::quantity((string) $balance->reserved_stock);
            $after = $before - $quantityMilli;
            if ($after < $reserved) {
                throw ValidationException::withMessages(['stock' => __('Stok bahan :name di outlet tidak mencukupi.', ['name' => $ingredient->name])]);
            }
            DB::table('ingredient_warehouses')->where('id', $balance->id)->update(['stock' => FixedDecimal::quantityText($after), 'updated_at' => now()]);
            $globalBefore = FixedDecimal::quantity((string) $ingredient->stock);
            if ($globalBefore < $quantityMilli) {
                throw ValidationException::withMessages(['stock' => __('Saldo total bahan :name tidak mencukupi.', ['name' => $ingredient->name])]);
            }
            $ingredient->update(['stock' => FixedDecimal::quantityText($globalBefore - $quantityMilli)]);
            DB::table('ingredient_inventory_ledgers')->insert([
                'ingredient_id' => $ingredient->id, 'warehouse_id' => $warehouseId, 'user_id' => auth()->id(),
                'type' => 'sale_consumption', 'quantity_delta' => FixedDecimal::quantityText(-$quantityMilli),
                'quantity_before' => FixedDecimal::quantityText($before), 'quantity_after' => FixedDecimal::quantityText($after),
                'reference_type' => Transaction::class, 'reference_id' => $transaction->id,
                'idempotency_key' => $key, 'created_at' => now(),
            ]);
        }, attempts: 3);
    }
}
