<?php

namespace App\Livewire\Inventory;

use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class WarehouseMonitoringIndex extends Component
{
    public string $search = '';

    public function render()
    {
        abort_unless(auth()->user()?->can('inventory-access'), 403);

        $warehouse = Warehouse::query()->where('type', 'central')->whereNull('outlet_id')->where('is_active', true)->firstOrFail();
        abort_unless(auth()->user()->hasRole('super-admin') || auth()->user()->can('warehouses-access'), 404);

        $term = trim($this->search);
        $rawMaterials = DB::table('ingredients')
            ->join('units', 'units.id', '=', 'ingredients.unit_id')
            ->leftJoin('ingredient_warehouses', function ($join) use ($warehouse) {
                $join->on('ingredient_warehouses.ingredient_id', '=', 'ingredients.id')
                    ->where('ingredient_warehouses.warehouse_id', '=', $warehouse->id);
            })
            ->whereNull('ingredients.deleted_at')->where('ingredients.is_active', true)
            ->when($term !== '', fn ($query) => $query->where(fn ($filter) => $filter->where('ingredients.name', 'like', '%'.$term.'%')->orWhere('ingredients.code', 'like', '%'.$term.'%')))
            ->orderBy('ingredients.name')
            ->get(['ingredients.code', 'ingredients.name', 'units.symbol as unit', 'ingredients.reorder_point', 'ingredient_warehouses.stock', 'ingredient_warehouses.reserved_stock'])
            ->map(function ($item) {
                $item->available = (float) ($item->stock ?? 0) - (float) ($item->reserved_stock ?? 0);

                return $item;
            });

        $semiFinished = DB::table('products')
            ->join('units', 'units.id', '=', 'products.unit_id')
            ->leftJoin('product_warehouses', function ($join) use ($warehouse) {
                $join->on('product_warehouses.product_id', '=', 'products.id')
                    ->where('product_warehouses.warehouse_id', '=', $warehouse->id);
            })
            ->whereNull('products.deleted_at')->where('products.is_active', true)
            ->where('products.is_semi_finished', true)->where('products.track_stock', true)
            ->when($term !== '', fn ($query) => $query->where(fn ($filter) => $filter->where('products.title', 'like', '%'.$term.'%')->orWhere('products.sku', 'like', '%'.$term.'%')))
            ->orderBy('products.title')
            ->get(['products.sku', 'products.title', 'units.symbol as unit', 'product_warehouses.stock', 'product_warehouses.reserved_stock', 'product_warehouses.reorder_point'])
            ->map(function ($item) {
                $item->available = (float) ($item->stock ?? 0) - (float) ($item->reserved_stock ?? 0);

                return $item;
            });

        return view('livewire.inventory.warehouse-monitoring-index', compact('warehouse', 'rawMaterials', 'semiFinished'))
            ->layout('layouts.dashboard', ['title' => __('Monitoring inventori gudang')]);
    }
}
