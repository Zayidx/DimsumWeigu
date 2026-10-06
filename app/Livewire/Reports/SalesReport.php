<?php

namespace App\Livewire\Reports;

use App\Models\CashierShift;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\TransactionTender;
use App\Models\Warehouse;
use App\Support\CsvCell;
use App\Support\OutletAccess;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReport extends Component
{
    use WithPagination;

    public string $from;

    public string $to;

    public string $warehouseId = '';

    public string $outletId = '';

    public string $cashierId = '';

    public string $method = '';

    public function mount(OutletAccess $access): void
    {
        $input = request()->validate([
            'from' => ['sometimes', 'date', 'after_or_equal:'.today()->subYear()->toDateString(), 'before_or_equal:today'],
            'to' => ['sometimes', 'date', 'before_or_equal:today'],
            'warehouse' => ['sometimes', 'integer'],
            'outlet' => ['sometimes', 'integer'],
        ]);
        $this->from = $input['from'] ?? today()->toDateString();
        $this->to = $input['to'] ?? today()->toDateString();
        if (isset($input['outlet'])) {
            $outlet = $access->outletsFor(auth()->user())->whereKey($input['outlet'])->firstOrFail();
            $this->outletId = (string) $outlet->id;
        }
        if (isset($input['warehouse'])) {
            $outletIds = $access->outletsFor(auth()->user())->pluck('id');
            $warehouse = Warehouse::query()->whereIn('outlet_id', $outletIds)->when($this->outletId !== '', fn (Builder $query) => $query->where('outlet_id', $this->outletId))->where('is_active', true)->findOrFail($input['warehouse']);
            $this->warehouseId = (string) $warehouse->id;
        }
    }

    public function applyFilters(): void
    {
        $this->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'], 'outletId' => ['nullable', 'integer'], 'warehouseId' => ['nullable', 'integer'], 'cashierId' => ['nullable', 'integer'], 'method' => ['nullable', 'string', 'max:40']]);
        $this->resetPage();
    }

    public function updatedOutletId(): void
    {
        $this->warehouseId = '';
        $this->cashierId = '';
        $this->resetPage();
    }

    public function export(OutletAccess $access): StreamedResponse
    {
        abort_unless(auth()->user()->can('reports-export'), 403);
        $query = $this->query($access);
        $rows = (clone $query)->with(['user:id,name', 'warehouse:id,name', 'customer:id,name', 'tenders'])->orderBy('occurred_at')->get();

        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Nomor', 'Waktu', 'Pelanggan', 'Kasir', 'Gudang', 'Metode', 'Status pembayaran', 'Subtotal', 'Diskon', 'Pajak', 'Total', 'Dibayar']);
            foreach ($rows as $transaction) {
                fputcsv($stream, array_map(CsvCell::safe(...), [$transaction->number, $transaction->occurred_at?->format('Y-m-d H:i:s'), $transaction->customer?->name, $transaction->user?->name, $transaction->warehouse?->name, $transaction->tenders->pluck('method')->unique()->join('+'), $transaction->payment_status, $transaction->subtotal, $transaction->discount_total, $transaction->tax_total, $transaction->grand_total, $transaction->paid_total]));
            }
            fclose($stream);
        }, 'sales-'.($this->outletId ?: 'all').'-'.$this->from.'-'.$this->to.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(OutletAccess $access)
    {
        abort_unless(auth()->user()->can('reports-access'), 403);
        $outlets = $access->outletsFor(auth()->user())->get(['id', 'name']);
        $query = $this->query($access);
        $rows = (clone $query)->with(['user:id,name', 'warehouse:id,name', 'customer:id,name', 'tenders'])->latest('occurred_at')->paginate(25);
        $daily = (clone $query)->selectRaw('DATE(occurred_at) as day, SUM(grand_total) as total')->groupByRaw('DATE(occurred_at)')->orderBy('day')->pluck('total', 'day');
        $outletTotals = (clone $query)->selectRaw('outlet_id, SUM(grand_total) as total')->groupBy('outlet_id')->pluck('total', 'outlet_id');
        $methods = TransactionTender::query()->whereIn('transaction_id', (clone $query)->select('transactions.id'))
            ->selectRaw('method, SUM(amount) as total')->groupBy('method')->pluck('total', 'method');
        $paidTenders = TransactionTender::query()->whereIn('transaction_id', (clone $query)->select('transactions.id'))->where('status', 'paid');
        $cashSales = (clone $paidTenders)->where('method', 'cash')->sum('amount');
        $qrisSales = (clone $paidTenders)->where('method', 'qris')->sum('amount');
        $qrisByAccount = TransactionTender::query()->join('bank_accounts', 'bank_accounts.id', '=', 'transaction_tenders.bank_account_id')
            ->whereIn('transaction_id', (clone $query)->select('transactions.id'))->where('status', 'paid')->where('method', 'qris')
            ->selectRaw('bank_accounts.bank_name, bank_accounts.account_name, bank_accounts.account_number, SUM(transaction_tenders.amount) as total')
            ->groupBy('bank_accounts.id', 'bank_accounts.bank_name', 'bank_accounts.account_name', 'bank_accounts.account_number')->get();
        $products = TransactionDetail::query()->whereIn('transaction_id', (clone $query)->select('transactions.id'))
            ->selectRaw('product_name, SUM(line_total) as total')->groupBy('product_name')->orderByDesc('total')->limit(8)->pluck('total', 'product_name');
        $chartData = [
            'daily' => ['labels' => $daily->keys()->map(fn ($day) => now()->parse($day)->format('d M'))->all(), 'values' => $daily->values()->map(fn ($value) => (float) $value)->all()],
            'outlets' => ['labels' => $outlets->whereIn('id', $this->outletId !== '' ? [(int) $this->outletId] : $outlets->pluck('id'))->pluck('name')->all(), 'values' => $outlets->whereIn('id', $this->outletId !== '' ? [(int) $this->outletId] : $outlets->pluck('id'))->map(fn ($outlet) => (float) ($outletTotals[$outlet->id] ?? 0))->values()->all()],
            'methods' => ['labels' => $methods->keys()->all(), 'values' => $methods->values()->map(fn ($value) => (float) $value)->all()],
            'products' => ['labels' => $products->keys()->all(), 'values' => $products->values()->map(fn ($value) => (float) $value)->all()],
        ];
        $visibleOutletIds = $this->outletId !== '' ? [(int) $this->outletId] : $outlets->pluck('id')->all();

        return view('livewire.reports.sales-report', [
            'outlets' => $outlets,
            'chartData' => $chartData,
            'rows' => $rows,
            'totalSales' => (clone $query)->sum('grand_total'),
            'totalTransactions' => (clone $query)->count(),
            'paidSales' => (clone $query)->where('payment_status', 'paid')->sum('grand_total'),
            'cashSales' => $cashSales,
            'qrisSales' => $qrisSales,
            'qrisByAccount' => $qrisByAccount,
            'warehouses' => Warehouse::query()->whereIn('outlet_id', $visibleOutletIds)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'cashiers' => CashierShift::query()->whereIn('outlet_id', $visibleOutletIds)->with('user:id,name')->get()->pluck('user')->filter()->unique('id')->sortBy('name'),
        ])->layout('layouts.dashboard', ['title' => __('Laporan penjualan')]);
    }

    private function query(OutletAccess $access): Builder
    {
        $this->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'], 'outletId' => ['nullable', 'integer'], 'warehouseId' => ['nullable', 'integer'], 'cashierId' => ['nullable', 'integer'], 'method' => ['nullable', 'string', 'max:40']]);
        abort_if(now()->parse($this->from)->diffInDays(now()->parse($this->to)) > 366, 422, __('Rentang laporan maksimal satu tahun.'));
        $outletIds = $access->outletsFor(auth()->user())->pluck('id');
        if ($this->outletId !== '') {
            abort_unless($outletIds->contains((int) $this->outletId), 404);
            $outletIds = collect([(int) $this->outletId]);
        }
        $query = Transaction::query()->whereIn('outlet_id', $outletIds)->whereBetween('occurred_at', [$this->from.' 00:00:00', $this->to.' 23:59:59'])->whereIn('status', ['completed', 'paid']);
        if ($this->warehouseId !== '') {
            abort_unless(Warehouse::query()->whereIn('outlet_id', $outletIds)->where('is_active', true)->whereKey($this->warehouseId)->exists(), 404);
            $query->where('warehouse_id', $this->warehouseId);
        }
        if ($this->cashierId !== '') {
            $query->where('user_id', $this->cashierId);
        }
        if ($this->method !== '') {
            $query->whereHas('tenders', fn (Builder $tenders) => $tenders->where('method', $this->method));
        }

        return $query;
    }
}
