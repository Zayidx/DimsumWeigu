<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\ProductWarehouse;
use App\Models\Transaction;
use App\Models\Warehouse;
use App\Support\OutletAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, OutletAccess $access): View
    {
        $outlet = $access->current($request);
        $today = now()->startOfDay();
        $sales = Transaction::query()->where('outlet_id', $outlet->id)->where('occurred_at', '>=', $today)->whereIn('status', ['completed', 'paid']);
        $warehouses = Warehouse::query()->where('outlet_id', $outlet->id)->where('is_active', true);
        $warehouseIds = (clone $warehouses)->pluck('id');
        $lowStockQuery = ProductWarehouse::query()->with(['product:id,title,sku', 'warehouse:id,name'])
            ->whereIn('warehouse_id', $warehouseIds)->whereColumn('stock', '<=', 'reorder_point')
            ->whereHas('product', fn (Builder $products) => $products->where('is_active', true))
            ->orderBy('stock');
        $lowStockCount = (clone $lowStockQuery)->count();
        $lowStock = $lowStockQuery->limit(8)->get();
        $shift = CashierShift::query()->with('warehouse:id,name')->where('user_id', $request->user()->id)->where('outlet_id', $outlet->id)->where('status', 'open')->latest('opened_at')->first();

        return view('dashboard.index', [
            'outlet' => $outlet,
            'todaySales' => (clone $sales)->sum('grand_total'),
            'todayTransactions' => (clone $sales)->count(),
            'openShifts' => CashierShift::query()->where('outlet_id', $outlet->id)->where('status', 'open')->count(),
            'lowStock' => $lowStock,
            'lowStockCount' => $lowStockCount,
            'shift' => $shift,
            'recentTransactions' => $request->user()->can('transactions-access') ? Transaction::query()->with('customer:id,name')->where('outlet_id', $outlet->id)->latest('occurred_at')->limit(8)->get() : collect(),
            'outlets' => $access->outletsFor($request->user())->get(['id', 'name']),
        ]);
    }
}
