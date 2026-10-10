<?php

namespace App\Livewire\Reports;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\TransactionTender;
use App\Support\OutletAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class CashierSalesReport extends Component
{
    use WithPagination;

    public string $from;

    public string $to;

    public string $sortOrder = 'desc';

    public function mount(): void
    {
        $input = request()->validate([
            'from' => ['sometimes', 'date', 'after_or_equal:'.today()->subYear()->toDateString(), 'before_or_equal:today'],
            'to' => ['sometimes', 'date', 'before_or_equal:today'],
            'sort' => ['sometimes', 'in:asc,desc'],
        ]);

        $this->from = $input['from'] ?? today()->toDateString();
        $this->to = $input['to'] ?? today()->toDateString();
        $this->sortOrder = $input['sort'] ?? 'desc';
    }

    public function applyFilters(): void
    {
        $this->validate([
            'from' => ['required', 'date', 'after_or_equal:'.today()->subYear()->toDateString(), 'before_or_equal:today'],
            'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:today'],
            'sortOrder' => ['required', 'in:asc,desc'],
        ]);

        abort_if(now()->parse($this->from)->diffInDays(now()->parse($this->to)) > 366, 422, __('Rentang laporan maksimal satu tahun.'));

        $this->resetPage();
    }

    public function render(OutletAccess $access)
    {
        abort_unless(auth()->user()->can('cashier-sales-report-access'), 403);

        $query = $this->query($access);
        $rows = (clone $query)
            ->with(['tenders' => fn ($tenders) => $tenders->where('status', 'paid')])
            ->orderBy('occurred_at', $this->sortOrder)
            ->orderBy('id', $this->sortOrder)
            ->paginate(25);

        $dailySales = (clone $query)
            ->selectRaw('DATE(occurred_at) as day, COUNT(*) as transactions, SUM(grand_total) as total')
            ->groupByRaw('DATE(occurred_at)')
            ->orderBy('day', $this->sortOrder)
            ->get();

        $hourExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', occurred_at) AS INTEGER)"
            : 'HOUR(occurred_at)';
        $hourlyTotals = (clone $query)
            ->selectRaw("{$hourExpression} as hour, COUNT(*) as transactions, SUM(grand_total) as total")
            ->groupByRaw($hourExpression)
            ->get()
            ->keyBy('hour');
        $hourlySales = collect(range(0, 23))->map(fn (int $hour) => [
            'hour' => $hour,
            'label' => sprintf('%02d.00', $hour),
            'transactions' => (int) ($hourlyTotals[$hour]->transactions ?? 0),
            'total' => (float) ($hourlyTotals[$hour]->total ?? 0),
        ]);
        $activeHours = $hourlySales->where('transactions', '>', 0);
        $busiestHour = $activeHours->sortBy([['transactions', 'desc'], ['hour', 'asc']])->first();
        $quietestHour = $activeHours->sortBy([['transactions', 'asc'], ['hour', 'asc']])->first();

        $productSales = TransactionDetail::query()
            ->whereIn('transaction_id', (clone $query)->select('transactions.id'))
            ->selectRaw('product_name, SUM(quantity) as quantity, SUM(line_total) as total, COUNT(DISTINCT transaction_id) as transactions')
            ->groupBy('product_name')
            ->orderByDesc('quantity')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $cashAndQris = TransactionTender::query()
            ->whereIn('transaction_id', (clone $query)->select('transactions.id'))
            ->where('status', 'paid')
            ->whereIn('method', ['cash', 'qris'])
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method');

        $chartData = [
            'daily' => [
                'labels' => $dailySales->sortBy('day')->map(fn ($day) => now()->parse($day->day)->format('d M'))->values()->all(),
                'values' => $dailySales->sortBy('day')->map(fn ($day) => (float) $day->total)->values()->all(),
            ],
            'outlets' => ['labels' => [], 'values' => []],
            'methods' => [
                'labels' => ['cash', 'qris'],
                'values' => [(float) ($cashAndQris['cash'] ?? 0), (float) ($cashAndQris['qris'] ?? 0)],
            ],
            'products' => [
                'labels' => $productSales->pluck('product_name')->all(),
                'values' => $productSales->map(fn ($product) => (float) $product->total)->all(),
                'quantity' => $productSales->map(fn ($product) => (float) $product->quantity)->all(),
            ],
            'hours' => [
                'labels' => $hourlySales->pluck('label')->all(),
                'values' => $hourlySales->pluck('transactions')->all(),
            ],
        ];

        $totalSales = (clone $query)->sum('grand_total');
        $totalTransactions = (clone $query)->count();

        return view('livewire.reports.cashier-sales-report', [
            'rows' => $rows,
            'dailySales' => $dailySales,
            'productSales' => $productSales,
            'hourlySales' => $hourlySales,
            'busiestHour' => $busiestHour,
            'quietestHour' => $quietestHour,
            'chartData' => $chartData,
            'totalSales' => $totalSales,
            'totalTransactions' => $totalTransactions,
            'averageTicket' => $totalTransactions > 0 ? $totalSales / $totalTransactions : 0,
            'cashSales' => $cashAndQris['cash'] ?? 0,
            'qrisSales' => $cashAndQris['qris'] ?? 0,
        ])->layout('layouts.dashboard', ['title' => __('Laporan penjualan saya')]);
    }

    private function query(OutletAccess $access): Builder
    {
        $this->validate([
            'from' => ['required', 'date', 'after_or_equal:'.today()->subYear()->toDateString(), 'before_or_equal:today'],
            'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:today'],
            'sortOrder' => ['required', 'in:asc,desc'],
        ]);

        abort_if(now()->parse($this->from)->diffInDays(now()->parse($this->to)) > 366, 422, __('Rentang laporan maksimal satu tahun.'));

        return Transaction::query()
            ->whereIn('outlet_id', $access->outletsFor(auth()->user())->select('id'))
            ->where('user_id', auth()->id())
            ->where('source', 'pos')
            ->whereIn('status', ['completed', 'paid'])
            ->whereBetween('occurred_at', [$this->from.' 00:00:00', $this->to.' 23:59:59']);
    }
}
