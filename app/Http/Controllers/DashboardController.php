<?php

namespace App\Http\Controllers;

use App\Models\CashHandover;
use App\Models\CashierShift;
use App\Models\OperationalNotification;
use App\Models\Outlet;
use App\Models\Payable;
use App\Models\ProductionOrder;
use App\Models\ProductionRequest;
use App\Models\ProductWarehouse;
use App\Models\PurchaseOrder;
use App\Models\Receivable;
use App\Models\StockTransfer;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\Warehouse;
use App\Models\WarehouseCashLedger;
use App\Services\Cashier\CashierShiftService;
use App\Support\DisplayNumber;
use App\Support\FixedDecimal;
use App\Support\OutletAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, OutletAccess $access): View
    {
        $outlet = $access->current($request);
        $user = $request->user();
        $outlets = $access->outletsFor($user)->get(['id', 'name']);
        $requestedOutlet = $request->query('outlet');
        $selectedOutlet = is_scalar($requestedOutlet) && ctype_digit((string) $requestedOutlet)
            ? $outlets->firstWhere('id', (int) $requestedOutlet) : null;
        abort_if($requestedOutlet !== null && ! $selectedOutlet, 404);
        $outletIds = $selectedOutlet ? [$selectedOutlet->id] : $outlets->pluck('id')->all();
        if ($user->hasRole('cashier') && ! $user->hasRole('super-admin')) {
            abort_if($selectedOutlet && $selectedOutlet->id !== $outlet->id, 404);
            $outletIds = [$outlet->id];
        }
        $availableWarehouses = Warehouse::query()->whereIn('outlet_id', $outletIds)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $requestedWarehouse = $request->query('warehouse');
        $selectedWarehouse = is_scalar($requestedWarehouse) && ctype_digit((string) $requestedWarehouse)
            ? $availableWarehouses->firstWhere('id', (int) $requestedWarehouse) : null;
        abort_if($requestedWarehouse !== null && ! $selectedWarehouse, 404);
        $warehouseId = $selectedWarehouse?->id;
        if (! $user->hasRole('super-admin')) {
            $role = $user->hasRole('manager') ? 'manager' : ($user->hasRole('finance') ? 'finance' : ($user->hasRole('warehouse') ? 'warehouse' : ($user->hasRole('cashier') ? 'cashier' : 'custom')));

            return $this->roleDashboard($request, $outlet, $role, $availableWarehouses, $selectedWarehouse, $outlets, $selectedOutlet, $outletIds);
        }
        $today = now()->startOfDay();
        $sales = Transaction::query()->whereIn('outlet_id', $outletIds)->where('occurred_at', '>=', $today)->whereIn('status', ['completed', 'paid']);
        if ($warehouseId) {
            $sales->where('warehouse_id', $warehouseId);
        }
        $requestedPeriod = $request->query('period', '7');
        $trendDays = is_string($requestedPeriod) && in_array($requestedPeriod, ['7', '30'], true) ? (int) $requestedPeriod : 7;
        $trendStart = today()->subDays($trendDays - 1);
        $trendRows = Transaction::query()
            ->whereIn('outlet_id', $outletIds)
            ->whereBetween('occurred_at', [$trendStart->copy()->startOfDay(), today()->endOfDay()])
            ->whereIn('status', ['completed', 'paid'])
            ->when($warehouseId, fn (Builder $query) => $query->where('warehouse_id', $warehouseId))
            ->selectRaw('DATE(occurred_at) as sales_date, SUM(grand_total) as total')
            ->groupByRaw('DATE(occurred_at)')
            ->pluck('total', 'sales_date');
        $dailyTrend = collect(range(0, $trendDays - 1))->map(function (int $offset) use ($trendStart, $trendRows): array {
            $date = $trendStart->copy()->addDays($offset);

            return ['label' => $date->translatedFormat('d M'), 'date' => $date->translatedFormat('d F Y'), 'from' => $date->toDateString(), 'to' => $date->toDateString(), 'total' => (float) ($trendRows[$date->toDateString()] ?? 0)];
        });
        $salesTrend = $trendDays === 30
            ? $dailyTrend->chunk(6)->map(function (Collection $days): array {
                $first = $days->first();
                $last = $days->last();

                return ['label' => $first['label'].'–'.$last['label'], 'date' => $first['date'].' – '.$last['date'], 'from' => $first['from'], 'to' => $last['to'], 'total' => $days->sum('total')];
            })->values()
            : $dailyTrend;
        $previousTrendTotal = Transaction::query()
            ->whereIn('outlet_id', $outletIds)
            ->whereBetween('occurred_at', [$trendStart->copy()->subDays($trendDays)->startOfDay(), $trendStart->copy()->subDay()->endOfDay()])
            ->whereIn('status', ['completed', 'paid'])
            ->when($warehouseId, fn (Builder $query) => $query->where('warehouse_id', $warehouseId))
            ->sum('grand_total');
        $trendTotal = $dailyTrend->sum('total');
        $warehouseIds = $warehouseId ? collect([$warehouseId]) : $availableWarehouses->pluck('id');
        $lowStockQuery = ProductWarehouse::query()->with(['product:id,title,sku', 'warehouse:id,name'])
            ->whereIn('warehouse_id', $warehouseIds)->whereColumn('stock', '<=', 'reorder_point')
            ->whereHas('product', fn (Builder $products) => $products->where('is_active', true))
            ->orderByRaw('(reorder_point - stock) DESC');
        $lowStockCount = (clone $lowStockQuery)->count();
        $lowStock = $lowStockQuery->limit(8)->get();
        $shift = CashierShift::query()->with('warehouse:id,name')->where('user_id', $request->user()->id)->where('outlet_id', $outlet->id)->where('status', 'open')->latest('opened_at')->first();

        $tasks = collect();
        $addTask = function (string $permission, string $label, Builder $query, string $route) use ($tasks, $user): void {
            if (! $user->can($permission)) {
                return;
            }
            $count = (clone $query)->count();
            if ($count === 0) {
                return;
            }
            $oldest = (clone $query)->min('created_at');
            $tasks->push(['label' => $label, 'count' => $count, 'oldest' => $oldest, 'url' => route($route)]);
        };
        $addTask('production-requests-access', 'Permintaan produksi aktif', ProductionRequest::query()->whereIn('outlet_id', $outletIds)->whereIn('status', ['draft', 'purchasing', 'approved', 'received'])->when($warehouseId, fn (Builder $q) => $q->where('warehouse_id', $warehouseId)), 'production-requests.index');
        $addTask('goods-receivings-access', 'Pesanan menunggu penerimaan', PurchaseOrder::query()->whereIn('status', ['ordered', 'partial'])->whereIn('warehouse_id', $warehouseIds), 'goods-receivings.index');
        $addTask('payables-access', 'Utang jatuh tempo', Payable::query()->whereIn('status', ['unpaid', 'partial'])->whereDate('due_at', '<=', today())->where(function (Builder $q) use ($warehouseIds): void {
            $q->whereHas('receiving', fn (Builder $r) => $r->whereIn('warehouse_id', $warehouseIds))
                ->orWhereHas('ingredientReceiving', fn (Builder $r) => $r->whereIn('warehouse_id', $warehouseIds));
        }), 'payables.index');
        $addTask('receivables-access', 'Piutang jatuh tempo', Receivable::query()->whereIn('status', ['unpaid', 'partial'])->whereDate('due_at', '<=', today())->whereHas('transaction', fn (Builder $q) => $q->whereIn('outlet_id', $outletIds)->when($warehouseId, fn (Builder $q) => $q->where('warehouse_id', $warehouseId))), 'receivables.index');
        $addTask('cashier-shifts-access', 'Shift masih terbuka', CashierShift::query()->whereIn('outlet_id', $outletIds)->where('status', 'open')->when($warehouseId, fn (Builder $q) => $q->where('warehouse_id', $warehouseId)), 'shifts.index');

        $quickActions = collect();
        if ($user->can('cashier-shifts-open') && ! $shift) {
            $quickActions->push(['label' => 'Buka shift', 'url' => route('shifts.create'), 'icon' => 'bi-play-circle']);
        }
        if ($user->can('purchase-orders-access') && $user->can('purchase-orders-create')) {
            $quickActions->push(['label' => 'Buat pesanan', 'url' => route('purchase-orders.index'), 'icon' => 'bi-bag-plus']);
        }
        if ($user->can('goods-receivings-access') && $user->can('goods-receivings-create')) {
            $quickActions->push(['label' => 'Terima barang', 'url' => route('goods-receivings.index'), 'icon' => 'bi-box-arrow-in-down']);
        }
        if ($user->can('production-requests-access') && $user->can('production-request')) {
            $quickActions->push(['label' => 'Permintaan produksi', 'url' => route('production-requests.index'), 'icon' => 'bi-clipboard-plus']);
        }
        if ($shift && $user->can('cashier-shifts-access')) {
            $quickActions->prepend(['label' => 'Lihat shift', 'url' => route('shifts.index'), 'icon' => 'bi-clock-history']);
        }

        $notifications = collect();
        if ($user->can('notifications-access')) {
            $notifications = OperationalNotification::query()->whereNull('read_at')->where(function (Builder $q) use ($outletIds, $user): void {
                $q->where(fn (Builder $personal) => $personal->where('user_id', $user->id)->where(fn (Builder $scope) => $scope->whereNull('outlet_id')->orWhereIn('outlet_id', $outletIds)))
                    ->orWhere(fn (Builder $shared) => $shared->whereNull('user_id')->whereIn('outlet_id', $outletIds));
            })->orderByRaw("CASE WHEN type LIKE '%overdue' THEN 0 ELSE 1 END")->latest()->limit(4)->get();
        }

        $periodSales = Transaction::query()->whereIn('outlet_id', $outletIds)->whereBetween('occurred_at', [$trendStart->copy()->startOfDay(), today()->endOfDay()])->whereIn('status', ['completed', 'paid'])->when($warehouseId, fn (Builder $q) => $q->where('warehouse_id', $warehouseId));
        $topProducts = collect();
        $busyHours = collect();
        if ($user->can('reports-access')) {
            $topProducts = TransactionDetail::query()->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
                ->whereIn('transactions.outlet_id', $outletIds)->whereBetween('transactions.occurred_at', [$trendStart->copy()->startOfDay(), today()->endOfDay()])
                ->whereIn('transactions.status', ['completed', 'paid'])->when($warehouseId, fn (Builder $q) => $q->where('transactions.warehouse_id', $warehouseId))
                ->selectRaw('transaction_details.product_name, transaction_details.unit_name, SUM(transaction_details.quantity) as quantity')
                ->groupBy('transaction_details.product_name', 'transaction_details.unit_name')->orderByDesc('quantity')->limit(5)->get();
            $hourExpression = DB::getDriverName() === 'sqlite' ? "strftime('%H', occurred_at)" : 'HOUR(occurred_at)';
            $busyHours = (clone $periodSales)->selectRaw($hourExpression.' as sale_hour, COUNT(*) as transactions')
                ->groupByRaw($hourExpression)->orderByDesc('transactions')->limit(3)->get();
        }

        return view('dashboard.index', [
            'outlet' => $outlet,
            'availableWarehouses' => $availableWarehouses,
            'selectedWarehouse' => $selectedWarehouse,
            'tasks' => $tasks,
            'quickActions' => $quickActions->take(3),
            'notifications' => $notifications,
            'topProducts' => $topProducts,
            'busyHours' => $busyHours,
            'todaySales' => (clone $sales)->sum('grand_total'),
            'salesTrend' => $salesTrend,
            'trendDays' => $trendDays,
            'trendStart' => $trendStart,
            'trendTotal' => $trendTotal,
            'previousTrendTotal' => (float) $previousTrendTotal,
            'trendChangePercent' => $previousTrendTotal > 0 ? (($trendTotal - $previousTrendTotal) / $previousTrendTotal) * 100 : null,
            'todayTransactions' => (clone $sales)->count(),
            'openShifts' => CashierShift::query()->whereIn('outlet_id', $outletIds)->where('status', 'open')->when($warehouseId, fn (Builder $q) => $q->where('warehouse_id', $warehouseId))->count(),
            'lowStock' => $lowStock,
            'lowStockCount' => $lowStockCount,
            'shift' => $shift,
            'recentTransactions' => $user->can('transactions-access') ? Transaction::query()->with('customer:id,name')->whereIn('outlet_id', $outletIds)->when($warehouseId, fn (Builder $q) => $q->where('warehouse_id', $warehouseId))->latest('occurred_at')->limit(8)->get() : collect(),
            'outlets' => $outlets,
            'selectedOutlet' => $selectedOutlet,
            'scopeLabel' => $selectedOutlet?->name ?? 'semua outlet yang dapat diakses',
        ]);
    }

    private function roleDashboard(Request $request, Outlet $outlet, string $role, Collection $warehouses, ?Warehouse $selectedWarehouse, Collection $outlets, ?Outlet $selectedOutlet, array $outletIds): View
    {
        $user = $request->user();
        $warehouseId = $selectedWarehouse?->id;
        $warehouseIds = $warehouseId ? [$warehouseId] : $warehouses->pluck('id')->all();
        $metrics = collect();
        $workItems = collect();
        $managerAlerts = collect();
        $actions = collect();
        $recentTransactions = collect();
        $titles = ['cashier' => 'Ruang kerja kasir', 'warehouse' => 'Ruang kerja gudang', 'finance' => 'Ruang kerja keuangan', 'manager' => 'Ruang kerja manajer', 'custom' => 'Ruang kerja saya'];
        $descriptions = [
            'cashier' => 'Shift dan transaksi Anda pada outlet aktif.',
            'warehouse' => 'Stok, penerimaan, dan produksi pada lokasi terpilih.',
            'finance' => 'Tagihan dan penagihan pada lokasi terpilih.',
            'manager' => 'Penjualan dan hal operasional yang perlu ditinjau pada lokasi terpilih.',
            'custom' => 'Akses cepat ke fitur yang tersedia untuk akun Anda.',
        ];
        $addAction = function (string $permission, string $label, string $route, string $icon) use ($user, $actions): void {
            if ($user->can($permission) && Route::has($route)) {
                $actions->push(['label' => $label, 'url' => route($route), 'icon' => $icon]);
            }
        };
        $addWorkItem = function (string $permission, string $label, int $count, string $route) use ($user, $workItems): void {
            if ($count > 0 && $user->can($permission)) {
                $workItems->push(['label' => $label, 'count' => $count, 'url' => route($route)]);
            }
        };

        if ($role === 'cashier') {
            $shift = CashierShift::query()->with('warehouse:id,name')->whereIn('outlet_id', $outletIds)->where('open_user_id', $user->id)->where('status', 'open')->latest('opened_at')->first();
            $requestedPettyCashWarehouseId = $shift?->warehouse_id
                ?? $selectedWarehouse?->id
                ?? (int) session('active_warehouse_id');
            $pettyCashWarehouseId = Warehouse::query()->where('outlet_id', $outlet->id)->where('is_active', true)->where('is_sales_enabled', true)
                ->whereKey($requestedPettyCashWarehouseId ?: 0)->value('id');
            $pettyCashWarehouseId ??= Warehouse::query()->where('outlet_id', $outlet->id)->where('is_active', true)->where('is_sales_enabled', true)->orderBy('id')->value('id');
            $lastPettyCashEntry = $pettyCashWarehouseId
                ? WarehouseCashLedger::query()->where('warehouse_id', $pettyCashWarehouseId)->latest('id')->first()
                : null;
            $pettyCashPosition = $lastPettyCashEntry
                ? FixedDecimal::money((string) $lastPettyCashEntry->balance_after)
                : CashierShiftService::INITIAL_PETTY_CASH_CENTS;
            $pettyCashAvailable = max(0, $pettyCashPosition);
            $pettyCashDebt = max(0, -$pettyCashPosition);
            $sales = Transaction::query()->where('outlet_id', $outlet->id)->where('user_id', $user->id)->whereDate('occurred_at', today())->whereIn('status', ['completed', 'paid']);
            $metrics->push(['label' => 'Shift saya', 'value' => $shift ? 'Terbuka' : 'Belum dibuka', 'detail' => $shift?->warehouse?->name ?? 'Buka shift sebelum melayani penjualan.']);
            $metrics->push(['label' => 'Transaksi saya hari ini', 'value' => DisplayNumber::format((clone $sales)->count()), 'detail' => 'Transaksi selesai milik Anda.']);
            $metrics->push(['label' => 'Penjualan saya hari ini', 'value' => 'Rp'.DisplayNumber::format((clone $sales)->sum('grand_total'), 0, ',', '.'), 'detail' => 'Nilai transaksi selesai, termasuk yang belum lunas.']);
            $metrics->push(['label' => 'Petty cash tersedia', 'value' => 'Rp'.DisplayNumber::format($pettyCashAvailable / 100, 0, ',', '.'), 'detail' => $pettyCashDebt > 0
                ? 'Saldo sedang minus Rp'.DisplayNumber::format($pettyCashDebt / 100, 0, ',', '.').'; penjualan tunai berikutnya membayar utang ini terlebih dahulu.'
                : 'Saldo kas tunai berjalan di gudang aktif.']);
            if ($pettyCashDebt > 0) {
                $metrics->push(['label' => 'Utang petty cash', 'value' => 'Rp'.DisplayNumber::format($pettyCashDebt / 100, 0, ',', '.'), 'detail' => 'Akan tertutup otomatis dari penerimaan penjualan tunai berikutnya.']);
            }
            if ($user->can('transactions-access')) {
                $recentTransactions = Transaction::query()->where('outlet_id', $outlet->id)->where('user_id', $user->id)->latest('occurred_at')->limit(5)->get();
            }
            if ($shift) {
                $addAction('cashier-shifts-access', 'Lihat shift saya', 'shifts.index', 'bi-clock-history');
            } else {
                $addAction('cashier-shifts-open', 'Buka shift', 'shifts.create', 'bi-play-circle');
            }
            $addAction('pos-access', 'Buka kasir', 'pos.index', 'bi-cart3');
            $addAction('transactions-access', 'Daftar transaksi', 'transactions.index', 'bi-receipt');
            $addAction('cash-management-access', 'Serah terima kas', 'cashier-cash-management.index', 'bi-bank');
        } elseif ($role === 'manager') {
            $sales = Transaction::query()->whereIn('outlet_id', $outletIds)->whereIn('warehouse_id', $warehouseIds)
                ->whereDate('occurred_at', today())->whereIn('status', ['completed', 'paid']);
            $openShifts = CashierShift::query()->whereIn('outlet_id', $outletIds)->whereIn('warehouse_id', $warehouseIds)->where('status', 'open');
            $lowStock = ProductWarehouse::query()->whereIn('warehouse_id', $warehouseIds)->whereColumn('stock', '<=', 'reorder_point')
                ->whereHas('product', fn (Builder $q) => $q->where('is_active', true));
            $handovers = CashHandover::query()->whereIn('source_warehouse_id', $warehouseIds)->whereIn('status', ['pending', 'approved']);
            $metrics->push(['label' => 'Penjualan hari ini', 'value' => 'Rp'.DisplayNumber::format((clone $sales)->sum('grand_total'), 0, ',', '.'), 'detail' => 'Transaksi selesai pada lokasi terpilih.']);
            $metrics->push(['label' => 'Shift terbuka', 'value' => DisplayNumber::format((clone $openShifts)->count()), 'detail' => 'Kasir yang sedang bertugas.']);
            $metrics->push(['label' => 'Stok perlu dicek', 'value' => DisplayNumber::format((clone $lowStock)->count()), 'detail' => 'Saldo mencapai batas pesan ulang.']);
            $metrics->push(['label' => 'Serah terima kas aktif', 'value' => DisplayNumber::format((clone $handovers)->count()), 'detail' => 'Menunggu persetujuan atau penerimaan.']);
            $addWorkItem('inventory-access', 'Periksa stok rendah', (clone $lowStock)->count(), 'inventory.index');
            $addWorkItem('cash-management-access', 'Tinjau serah terima kas', (clone $handovers)->count(), 'cash-management.index');

            if ($user->can('inventory-access')) {
                $lowStock->with(['product:id,title,unit_id', 'product.unit:id,symbol', 'warehouse:id,name,outlet_id', 'warehouse.outlet:id,name'])
                    ->orderByRaw('(reorder_point - stock) DESC')->limit(5)->get()
                    ->each(fn (ProductWarehouse $row) => $managerAlerts->push([
                        'label' => 'Stok rendah · '.($row->product?->title ?? 'Produk'),
                        'detail' => ($row->warehouse?->outlet?->name ?? $row->warehouse?->name).' · tersedia '.DisplayNumber::format(max(0, (float) $row->stock - (float) $row->reserved_stock), 3, ',', '.').' '.($row->product?->unit?->symbol ?? '').' dari batas pesan ulang '.DisplayNumber::format((float) $row->reorder_point, 3, ',', '.'),
                        'url' => route('reports.inventory', ['outlet' => $row->warehouse?->outlet_id]),
                        'date' => null,
                        'priority' => 0,
                    ]));
            }

            if ($user->can('production-requests-access')) {
                ProductionRequest::query()->with(['warehouse:id,name,outlet_id', 'warehouse.outlet:id,name'])
                    ->whereIn('outlet_id', $outletIds)
                    ->whereIn('status', ['requested', 'purchasing', 'approved', 'received'])
                    ->oldest()->limit(4)->get()
                    ->each(fn (ProductionRequest $request) => $managerAlerts->push([
                        'label' => 'Permintaan produksi · '.$request->number,
                        'detail' => ($request->warehouse?->outlet?->name ?? $request->warehouse?->name).' · status '.str($request->status)->headline(),
                        'url' => route('production-requests.index'),
                        'date' => $request->created_at,
                        'priority' => 1,
                    ]));
            }

            if ($user->can('transfers-access')) {
                StockTransfer::query()->with(['destinationWarehouse:id,name,outlet_id', 'destinationWarehouse.outlet:id,name'])
                    ->whereIn('destination_warehouse_id', $warehouseIds)->where('status', 'sent')
                    ->oldest('sent_at')->limit(4)->get()
                    ->each(fn (StockTransfer $transfer) => $managerAlerts->push([
                        'label' => 'Transfer menunggu diterima · '.$transfer->number,
                        'detail' => ($transfer->destinationWarehouse?->outlet?->name ?? $transfer->destinationWarehouse?->name).' · dikirim '.($transfer->sent_at?->diffForHumans() ?? 'tanpa tanggal'),
                        'url' => route('transfers.index'),
                        'date' => $transfer->sent_at,
                        'priority' => 1,
                    ]));
            }

            if ($user->can('cashier-shifts-access')) {
                CashierShift::query()->with(['user:id,name', 'warehouse:id,name,outlet_id', 'warehouse.outlet:id,name'])
                    ->whereIn('outlet_id', $outletIds)->whereIn('warehouse_id', $warehouseIds)
                    ->where('status', 'closed')->where('cash_difference', '<>', 0)
                    ->where('closed_at', '>=', now()->subDays(7))->latest('closed_at')->limit(4)->get()
                    ->each(fn (CashierShift $shift) => $managerAlerts->push([
                        'label' => 'Selisih tutup shift · '.($shift->user?->name ?? 'Kasir'),
                        'detail' => ($shift->warehouse?->outlet?->name ?? $shift->warehouse?->name).' · '.((float) $shift->cash_difference < 0 ? 'kurang ' : 'lebih ').'Rp'.DisplayNumber::format(abs((float) $shift->cash_difference), 0, ',', '.'),
                        'url' => route('shifts.index'),
                        'date' => $shift->closed_at,
                        'priority' => 1,
                    ]));
            }

            if ($user->can('cash-management-access')) {
                (clone $handovers)->with(['source:id,name,outlet_id', 'destination:id,name'])->oldest()->limit(3)->get()
                    ->each(fn (CashHandover $handover) => $managerAlerts->push([
                        'label' => 'Serah terima kas · '.str($handover->status)->headline(),
                        'detail' => ($handover->source?->name ?? 'Lokasi asal').' → '.($handover->destination?->name ?? 'Lokasi tujuan').' · Rp'.DisplayNumber::format($handover->amount, 0, ',', '.'),
                        'url' => route('cash-management.index'),
                        'date' => $handover->created_at,
                        'priority' => 1,
                    ]));
            }

            $managerAlerts = $managerAlerts->sortBy(fn (array $alert) => ($alert['priority'] * 3000000000) + ($alert['date']?->timestamp ?? 0))->take(12)->values();
            $addAction('transactions-access', 'Pantau transaksi', 'transactions.index', 'bi-receipt');
            $addAction('cashier-shifts-access', 'Pantau shift', 'shifts.index', 'bi-clock-history');
            $addAction('inventory-access', 'Periksa stok', 'inventory.index', 'bi-box-seam');
            $addAction('cash-management-access', 'Serah terima kas', 'cash-management.index', 'bi-bank');
            $addAction('replenishment-access', 'Saran stok & produksi', 'replenishment-suggestions.index', 'bi-boxes');
        } elseif ($role === 'warehouse') {
            $lowStock = ProductWarehouse::query()->whereIn('warehouse_id', $warehouseIds)->whereColumn('stock', '<=', 'reorder_point')->whereHas('product', fn (Builder $q) => $q->where('is_active', true));
            $orders = PurchaseOrder::query()->whereIn('warehouse_id', $warehouseIds)->whereIn('status', ['ordered', 'partial']);
            $requests = ProductionRequest::query()->whereIn('outlet_id', $outletIds)->whereIn('warehouse_id', $warehouseIds)->whereIn('status', ['draft', 'purchasing', 'approved', 'received']);
            $production = ProductionOrder::query()->whereIn('outlet_id', $outletIds)->whereIn('warehouse_id', $warehouseIds)->where('status', 'in_production');
            $incomingCash = CashHandover::query()->whereIn('destination_warehouse_id', $warehouseIds)->where('status', 'approved');
            if ($user->can('inventory-access')) {
                $metrics->push(['label' => 'Stok perlu dicek', 'value' => DisplayNumber::format((clone $lowStock)->count()), 'detail' => 'Saldo mencapai batas pesan ulang.']);
                $addWorkItem('inventory-access', 'Periksa stok rendah', (clone $lowStock)->count(), 'inventory.index');
            }
            if ($user->can('goods-receivings-access')) {
                $metrics->push(['label' => 'Menunggu penerimaan', 'value' => DisplayNumber::format((clone $orders)->count()), 'detail' => 'Pesanan yang masih perlu diterima.']);
                $addWorkItem('goods-receivings-access', 'Terima barang', (clone $orders)->count(), 'goods-receivings.index');
            }
            if ($user->can('production-requests-access')) {
                $metrics->push(['label' => 'Permintaan produksi aktif', 'value' => DisplayNumber::format((clone $requests)->count()), 'detail' => 'Permintaan pada lokasi terpilih.']);
                $addWorkItem('production-requests-access', 'Tinjau permintaan produksi', (clone $requests)->count(), 'production-requests.index');
            }
            if ($user->can('production-orders-access')) {
                $metrics->push(['label' => 'Sedang diproduksi', 'value' => DisplayNumber::format((clone $production)->count()), 'detail' => 'Pesanan produksi belum selesai.']);
                $addWorkItem('production-orders-access', 'Lanjutkan produksi', (clone $production)->count(), 'production-orders.index');
            }
            if ($user->can('cash-management-access')) {
                $metrics->push(['label' => 'Kas menunggu diterima', 'value' => DisplayNumber::format((clone $incomingCash)->count()), 'detail' => 'Serah terima yang sudah dikirim ke lokasi terpilih.']);
                $addWorkItem('cash-management-access', 'Konfirmasi penerimaan kas', (clone $incomingCash)->count(), 'cash-management.index');
            }
            $addAction('inventory-access', 'Buka inventori', 'inventory.index', 'bi-box-seam');
            $addAction('goods-receivings-access', 'Penerimaan barang', 'goods-receivings.index', 'bi-box-arrow-in-down');
            $addAction('production-orders-access', 'Produksi', 'production-orders.index', 'bi-gear');
            $addAction('cash-management-access', 'Penerimaan kas', 'cash-management.index', 'bi-bank');
            $addAction('replenishment-access', 'Saran stok & produksi', 'replenishment-suggestions.index', 'bi-boxes');
        } elseif ($role === 'finance') {
            $payables = Payable::query()->whereIn('status', ['unpaid', 'partial'])->where(function (Builder $q) use ($warehouseIds): void {
                $q->whereHas('receiving', fn (Builder $r) => $r->whereIn('warehouse_id', $warehouseIds))
                    ->orWhereHas('ingredientReceiving', fn (Builder $r) => $r->whereIn('warehouse_id', $warehouseIds));
            });
            $receivables = Receivable::query()->whereIn('status', ['unpaid', 'partial'])->whereHas('transaction', fn (Builder $q) => $q->whereIn('outlet_id', $outletIds)->whereIn('warehouse_id', $warehouseIds));
            if ($user->can('payables-access')) {
                $balance = (clone $payables)->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as balance')->value('balance');
                $overdue = (clone $payables)->whereDate('due_at', '<=', today())->count();
                $metrics->push(['label' => 'Sisa utang', 'value' => 'Rp'.DisplayNumber::format((float) $balance, 0, ',', '.'), 'detail' => 'Belum dibayar pada lokasi terpilih.']);
                $metrics->push(['label' => 'Utang jatuh tempo', 'value' => DisplayNumber::format($overdue), 'detail' => 'Jatuh tempo hari ini atau sebelumnya.']);
                $addWorkItem('payables-access', 'Tinjau utang jatuh tempo', $overdue, 'payables.index');
            }
            if ($user->can('receivables-access')) {
                $balance = (clone $receivables)->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as balance')->value('balance');
                $overdue = (clone $receivables)->whereDate('due_at', '<=', today())->count();
                $metrics->push(['label' => 'Sisa piutang', 'value' => 'Rp'.DisplayNumber::format((float) $balance, 0, ',', '.'), 'detail' => 'Belum diterima pada lokasi terpilih.']);
                $metrics->push(['label' => 'Piutang jatuh tempo', 'value' => DisplayNumber::format($overdue), 'detail' => 'Jatuh tempo hari ini atau sebelumnya.']);
                $addWorkItem('receivables-access', 'Tinjau piutang jatuh tempo', $overdue, 'receivables.index');
            }
            $addAction('payables-access', 'Buka utang', 'payables.index', 'bi-wallet2');
            $addAction('receivables-access', 'Buka piutang', 'receivables.index', 'bi-cash-coin');
            $addAction('reports-access', 'Laporan penjualan', 'reports.sales', 'bi-bar-chart');
            $addAction('qris-reconciliations-access', 'Rekonsiliasi QRIS', 'qris-reconciliations.index', 'bi-credit-card');
        } else {
            foreach ([
                ['users-access', 'Pengguna', 'users.index', 'bi-people'],
                ['products-access', 'Produk', 'products.index', 'bi-box'],
                ['categories-access', 'Kategori', 'categories.index', 'bi-tags'],
                ['units-access', 'Satuan', 'units.index', 'bi-rulers'],
                ['customers-access', 'Pelanggan', 'customers.index', 'bi-person-lines-fill'],
                ['suppliers-access', 'Pemasok', 'suppliers.index', 'bi-truck'],
                ['pos-access', 'Kasir', 'pos.index', 'bi-cart3'],
                ['transactions-access', 'Transaksi', 'transactions.index', 'bi-receipt'],
                ['cashier-shifts-access', 'Shift kasir', 'shifts.index', 'bi-clock-history'],
                ['inventory-access', 'Inventori', 'inventory.index', 'bi-box-seam'],
                ['ingredients-access', 'Bahan', 'ingredients.index', 'bi-basket'],
                ['recipes-access', 'Resep', 'recipes.index', 'bi-journal-text'],
                ['production-requests-access', 'Permintaan produksi', 'production-requests.index', 'bi-clipboard-check'],
                ['production-orders-access', 'Produksi', 'production-orders.index', 'bi-gear'],
                ['transfers-access', 'Transfer stok', 'transfers.index', 'bi-arrow-left-right'],
                ['stock-opnames-access', 'Stok opname', 'stock-opnames.index', 'bi-clipboard-data'],
                ['purchase-orders-access', 'Pembelian', 'purchase-orders.index', 'bi-bag-check'],
                ['goods-receivings-access', 'Penerimaan barang', 'goods-receivings.index', 'bi-box-arrow-in-down'],
                ['supplier-returns-access', 'Retur pemasok', 'supplier-returns.index', 'bi-arrow-return-left'],
                ['payables-access', 'Utang', 'payables.index', 'bi-wallet2'],
                ['receivables-access', 'Piutang', 'receivables.index', 'bi-cash-coin'],
                ['expenses-access', 'Pengeluaran', 'expenses.index', 'bi-cash-stack'],
                ['waste-access', 'Waste', 'waste.index', 'bi-trash3'],
                ['cash-management-access', 'Manajemen kas', 'cash-management.index', 'bi-bank'],
                ['reports-access', 'Laporan', 'reports.sales', 'bi-bar-chart'],
                ['audit-logs-access', 'Audit log', 'audit-logs.index', 'bi-journal-text'],
                ['notifications-access', 'Notifikasi', 'notifications.index', 'bi-bell'],
                ['store-settings-access', 'Profil toko', 'settings.store', 'bi-sliders'],
                ['payment-settings-access', 'Pengaturan pembayaran', 'settings.payments', 'bi-credit-card'],
            ] as [$permission, $label, $route, $icon]) {
                $addAction($permission, $label, $route, $icon);
            }
        }

        return view('dashboard.role', [
            'outlet' => $outlet,
            'role' => $role,
            'roleTitle' => $titles[$role],
            'roleDescription' => $descriptions[$role],
            'availableWarehouses' => $warehouses,
            'selectedWarehouse' => $selectedWarehouse,
            'outlets' => $outlets,
            'selectedOutlet' => $selectedOutlet,
            'scopeLabel' => $selectedOutlet?->name ?? 'semua outlet yang dapat diakses',
            'metrics' => $metrics,
            'workItems' => $workItems,
            'managerAlerts' => $managerAlerts,
            'actions' => $actions->take(4),
            'recentTransactions' => $recentTransactions,
        ]);
    }
}
