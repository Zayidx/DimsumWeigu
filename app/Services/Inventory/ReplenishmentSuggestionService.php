<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\RecipeVersion;
use App\Models\Warehouse;
use App\Services\Production\RecipeCostService;
use App\Support\FixedDecimal;
use Illuminate\Support\Facades\DB;

class ReplenishmentSuggestionService
{
    /** @return array{central: ?Warehouse, rows: array<int, array<string, mixed>>} */
    public function suggestions(array $outletIds, int $targetDays = 3, int $lookbackDays = 14, ?int $outletFilter = null): array
    {
        $central = Warehouse::query()->whereNull('outlet_id')->where('type', 'central')->where('is_active', true)->orderBy('id')->first();
        if (! $central || $outletIds === []) {
            return ['central' => $central, 'rows' => []];
        }

        $outletIds = array_values(array_filter($outletIds, fn (int $id): bool => ! $outletFilter || $id === $outletFilter));
        $outletWarehouses = Warehouse::query()->with('outlet:id,name')->whereIn('outlet_id', $outletIds)->where('is_active', true)->where('is_sales_enabled', true)->orderBy('id')->get()->keyBy('outlet_id');
        if ($outletWarehouses->isEmpty()) {
            return ['central' => $central, 'rows' => []];
        }

        $from = today()->subDays($lookbackDays - 1)->startOfDay();
        $sales = DB::table('transaction_details')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->whereIn('transactions.outlet_id', $outletWarehouses->keys())
            ->whereBetween('transactions.occurred_at', [$from, now()])
            ->where('transactions.source', 'pos')
            ->whereIn('transactions.status', ['completed', 'paid'])
            ->whereNotNull('transaction_details.product_id')
            ->selectRaw('transactions.outlet_id, transaction_details.product_id, transaction_details.unit_name, SUM(transaction_details.quantity) as sold_quantity')
            ->groupBy('transactions.outlet_id', 'transaction_details.product_id', 'transaction_details.unit_name')
            ->get();

        $menuProducts = Product::query()->with(['unit:id,symbol', 'productUnits.unit:id,symbol'])
            ->whereIn('id', $sales->pluck('product_id')->unique())
            ->where('is_active', true)->get()->keyBy('id');
        $activeRecipes = RecipeVersion::query()
            ->with(['productItems.product.unit:id,symbol', 'productItems.product.productUnits'])
            ->whereIn('product_id', $menuProducts->keys())
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderByDesc('version')->get()
            ->groupBy(fn (RecipeVersion $recipe): string => $recipe->product_id.'|'.$recipe->unit_id);
        $components = Product::query()->with('unit:id,symbol')->where('is_active', true)->where('is_semi_finished', true)->get()->keyBy('id');
        $centralStocks = ProductWarehouse::query()->where('warehouse_id', $central->id)->get()->keyBy('product_id');

        $demand = [];
        foreach ($sales as $sale) {
            $menu = $menuProducts->get($sale->product_id);
            if (! $menu) {
                continue;
            }

            $saleUnitId = (int) $menu->unit_id;
            if ($menu->unit?->symbol !== $sale->unit_name) {
                $saleUnit = $menu->productUnits->first(fn ($unit): bool => $unit->unit?->symbol === $sale->unit_name);
                if (! $saleUnit) {
                    continue;
                }
                $saleUnitId = (int) $saleUnit->unit_id;
            }

            $recipe = $activeRecipes->get($menu->id.'|'.$saleUnitId)?->first();
            if (! $recipe || $recipe->productItems->isEmpty()) {
                continue;
            }

            $soldMilli = FixedDecimal::quantity((string) $sale->sold_quantity);
            $yieldMilli = FixedDecimal::quantity((string) $recipe->yield_quantity);
            if ($soldMilli <= 0 || $yieldMilli <= 0) {
                continue;
            }

            foreach ($recipe->productItems as $item) {
                $component = $item->product;
                if (! $component?->is_active || ! $component->is_semi_finished || ! $component->track_stock) {
                    continue;
                }
                $unit = $component->productUnits->firstWhere('unit_id', $item->unit_id);
                $factor = (int) $item->unit_id === (int) $component->unit_id ? 1 : (float) ($unit?->conversion_factor ?? 0);
                if ($factor <= 0) {
                    continue;
                }

                $componentMilli = RecipeCostService::effectiveQuantityMilli(
                    FixedDecimal::quantity((string) $item->quantity),
                    (string) $item->waste_percent,
                );
                $consumedMilli = (int) round(($componentMilli * $soldMilli / $yieldMilli) * $factor);
                $key = $sale->outlet_id.'|'.$component->id;
                $demand[$key] = ($demand[$key] ?? 0) + $consumedMilli;
            }
        }

        $rows = [];
        foreach ($outletWarehouses as $outletId => $outletWarehouse) {
            $currentStocks = ProductWarehouse::query()->where('warehouse_id', $outletWarehouse->id)->get()->keyBy('product_id');
            $incoming = DB::table('stock_transfer_items')
                ->join('stock_transfers', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
                ->where('stock_transfers.destination_warehouse_id', $outletWarehouse->id)
                ->where('stock_transfers.status', 'sent')
                ->selectRaw('stock_transfer_items.product_id, SUM(CASE WHEN stock_transfer_items.quantity_sent > stock_transfer_items.quantity_received THEN stock_transfer_items.quantity_sent - stock_transfer_items.quantity_received ELSE 0 END) as quantity')
                ->groupBy('stock_transfer_items.product_id')
                ->pluck('quantity', 'product_id');
            $requested = DB::table('production_request_items')
                ->join('production_requests', 'production_requests.id', '=', 'production_request_items.production_request_id')
                ->where('production_requests.outlet_id', $outletId)
                ->whereIn('production_requests.status', ['requested', 'approved', 'purchasing', 'received'])
                ->selectRaw('production_request_items.product_id, SUM(production_request_items.quantity) as quantity')
                ->groupBy('production_request_items.product_id')
                ->pluck('quantity', 'product_id');
            $productionInProgress = DB::table('production_order_items')
                ->join('production_orders', 'production_orders.id', '=', 'production_order_items.production_order_id')
                ->where('production_orders.warehouse_id', $central->id)
                ->where('production_orders.outlet_id', $outletId)
                ->where('production_orders.status', 'in_production')
                ->selectRaw('production_order_items.product_id, SUM(CASE WHEN production_order_items.planned_quantity > production_order_items.produced_quantity THEN production_order_items.planned_quantity - production_order_items.produced_quantity ELSE 0 END) as quantity')
                ->groupBy('production_order_items.product_id')
                ->pluck('quantity', 'product_id');

            $productIds = collect(array_keys($currentStocks->filter(fn (ProductWarehouse $stock): bool => (float) $stock->stock <= (float) $stock->reorder_point)->all()))
                ->merge($components->filter(fn (Product $product): bool => (float) $product->reorder_point > 0)->keys())
                ->merge($incoming->keys())->merge($requested->keys())->merge($productionInProgress->keys())
                ->merge(collect(array_keys($demand))->filter(fn (string $key): bool => (int) strtok($key, '|') === (int) $outletId)->map(fn (string $key): int => (int) substr($key, strpos($key, '|') + 1)))
                ->unique()->values();

            foreach ($productIds as $productId) {
                $product = $components->get((int) $productId);
                if (! $product) {
                    continue;
                }

                $salesConsumptionMilli = $demand[$outletId.'|'.$product->id] ?? 0;

                $current = $currentStocks->get($productId);
                $centralStock = $centralStocks->get($productId);
                $currentMilli = max(0, FixedDecimal::quantity((string) ($current?->stock ?? 0)) - FixedDecimal::quantity((string) ($current?->reserved_stock ?? 0)));
                $incomingMilli = FixedDecimal::quantity((string) ($incoming[$productId] ?? 0));
                $requestedMilli = FixedDecimal::quantity((string) ($requested[$productId] ?? 0));
                $productionInProgressMilli = FixedDecimal::quantity((string) ($productionInProgress[$productId] ?? 0));
                $centralAvailableMilli = max(0, FixedDecimal::quantity((string) ($centralStock?->stock ?? 0)) - FixedDecimal::quantity((string) ($centralStock?->reserved_stock ?? 0)));
                $forecastMilli = (int) ceil($salesConsumptionMilli * $targetDays / $lookbackDays);
                $reorderMilli = FixedDecimal::quantity((string) ($current?->reorder_point ?? $product->reorder_point ?? 0));
                $targetMilli = max($forecastMilli, $reorderMilli);
                $neededMilli = max(0, $targetMilli - $currentMilli - $incomingMilli - $requestedMilli);
                if ($neededMilli === 0 && $salesConsumptionMilli === 0) {
                    continue;
                }

                $rows[] = [
                    'outlet_id' => (int) $outletId,
                    'outlet_name' => $outletWarehouse->outlet?->name ?? $outletWarehouse->name,
                    'product_id' => $product->id,
                    'product_name' => $product->title,
                    'unit_symbol' => $product->unit?->symbol,
                    'sales_quantity' => $salesConsumptionMilli,
                    'average_daily' => (int) ceil($salesConsumptionMilli / $lookbackDays),
                    'target_quantity' => $targetMilli,
                    'outlet_available' => $currentMilli,
                    'incoming_quantity' => $incomingMilli,
                    'requested_quantity' => $requestedMilli,
                    'production_in_progress' => $productionInProgressMilli,
                    'central_available' => $centralAvailableMilli,
                    'suggested_transfer' => min($neededMilli, $centralAvailableMilli),
                    'additional_production' => max(0, $neededMilli - $centralAvailableMilli - $productionInProgressMilli),
                ];
            }
        }

        usort($rows, fn (array $a, array $b): int => ($b['additional_production'] <=> $a['additional_production']) ?: ($b['suggested_transfer'] <=> $a['suggested_transfer']));

        return ['central' => $central, 'rows' => $rows];
    }
}
