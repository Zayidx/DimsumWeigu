<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\ProductWarehouse;
use App\Models\Transaction;
use App\Models\Warehouse;
use App\Support\OutletAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, OutletAccess $access): View
    {
        $outlet = $access->current($request);
        $today = now()->startOfDay();
        $sales = Transaction::query()->where('outlet_id', $outlet->id)->where('occurred_at', '>=', $today)->whereIn('status', ['completed', 'paid']);
        $requestedPeriod = $request->query('period', '7');
        $trendDays = is_string($requestedPeriod) && in_array($requestedPeriod, ['7', '30'], true) ? (int) $requestedPeriod : 7;
        $trendStart = today()->subDays($trendDays - 1);
        $trendRows = Transaction::query()
            ->where('outlet_id', $outlet->id)
            ->whereBetween('occurred_at', [$trendStart->copy()->startOfDay(), today()->endOfDay()])
            ->whereIn('status', ['completed', 'paid'])
            ->selectRaw('DATE(occurred_at) as sales_date, SUM(grand_total) as total')
            ->groupByRaw('DATE(occurred_at)')
            ->pluck('total', 'sales_date');
        $dailyTrend = collect(range(0, $trendDays - 1))->map(function (int $offset) use ($trendStart, $trendRows): array {
            $date = $trendStart->copy()->addDays($offset);

            return ['label' => $date->translatedFormat('d M'), 'date' => $date->translatedFormat('d F Y'), 'total' => (float) ($trendRows[$date->toDateString()] ?? 0)];
        });
        $salesTrend = $trendDays === 30
            ? $dailyTrend->chunk(6)->map(function (Collection $days): array {
                $first = $days->first();
                $last = $days->last();

                return ['label' => $first['label'].'–'.$last['label'], 'date' => $first['date'].' – '.$last['date'], 'total' => $days->sum('total')];
            })->values()
            : $dailyTrend;
        $previousTrendTotal = Transaction::query()
            ->where('outlet_id', $outlet->id)
            ->whereBetween('occurred_at', [$trendStart->copy()->subDays($trendDays)->startOfDay(), $trendStart->copy()->subDay()->endOfDay()])
            ->whereIn('status', ['completed', 'paid'])
            ->sum('grand_total');
        $trendTotal = $dailyTrend->sum('total');
        $warehouses = Warehouse::query()->where('outlet_id', $outlet->id)->where('is_active', true);
        $warehouseIds = (clone $warehouses)->pluck('id');
        $lowStockQuery = ProductWarehouse::query()->with(['product:id,title,sku', 'warehouse:id,name'])
            ->whereIn('warehouse_id', $warehouseIds)->whereColumn('stock', '<=', 'reorder_point')
            ->whereHas('product', fn (Builder $products) => $products->where('is_active', true))
            ->orderByRaw('(reorder_point - stock) DESC');
        $lowStockCount = (clone $lowStockQuery)->count();
        $lowStock = $lowStockQuery->limit(8)->get();
        $shift = CashierShift::query()->with('warehouse:id,name')->where('user_id', $request->user()->id)->where('outlet_id', $outlet->id)->where('status', 'open')->latest('opened_at')->first();

        return view('dashboard.index', [
            'outlet' => $outlet,
            'todaySales' => (clone $sales)->sum('grand_total'),
            'salesTrend' => $salesTrend,
            'trendDays' => $trendDays,
            'trendStart' => $trendStart,
            'trendTotal' => $trendTotal,
            'previousTrendTotal' => (float) $previousTrendTotal,
            'trendChangePercent' => $previousTrendTotal > 0 ? (($trendTotal - $previousTrendTotal) / $previousTrendTotal) * 100 : null,
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
