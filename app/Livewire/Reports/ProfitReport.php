<?php

namespace App\Livewire\Reports;

use App\Models\Profit;
use App\Models\Warehouse;
use App\Support\CsvCell;
use App\Support\OutletAccess;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfitReport extends Component
{
    use WithPagination;

    public string $from;

    public string $to;

    public string $warehouseId = '';

    public string $outletId = '';

    public function updatedOutletId(): void
    {
        $this->warehouseId = '';
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->from = today()->toDateString();
        $this->to = today()->toDateString();
    }

    public function applyFilters(): void
    {
        $this->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'], 'warehouseId' => ['nullable', 'integer']]);
        $this->resetPage();
    }

    public function export(OutletAccess $access): StreamedResponse
    {
        abort_unless(auth()->user()->can('reports-export'), 403);
        [$query] = $this->query($access);
        $rows = (clone $query)->with(['transaction:id,number,warehouse_id', 'transaction.warehouse:id,name,outlet_id', 'detail:id,transaction_id,product_name,product_sku,quantity'])->orderBy('occurred_at')->get();
        $outletNames = $access->outletsFor(auth()->user())->pluck('name', 'id');

        return response()->streamDownload(function () use ($rows, $outletNames): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Waktu', 'Transaksi', 'Outlet', 'Gudang', 'Produk', 'SKU', 'Qty', 'Penjualan', 'HPP', 'Laba']);
            foreach ($rows as $row) {
                fputcsv($stream, array_map(CsvCell::safe(...), [$row->occurred_at?->format('Y-m-d H:i:s'), $row->transaction?->number, $outletNames[$row->outlet_id] ?? '', $row->transaction?->warehouse?->name, $row->detail?->product_name, $row->detail?->product_sku, $row->detail?->quantity, $row->revenue, $row->cost, $row->profit]));
            }
            fclose($stream);
        }, 'profit-'.($this->outletId ?: 'all').'-'.$this->from.'-'.$this->to.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(OutletAccess $access)
    {
        abort_unless(auth()->user()->can('reports-access'), 403);
        [$query, $outletIds] = $this->query($access);

        return view('livewire.reports.profit-report', [
            'outlets' => $access->outletsFor(auth()->user())->get(['id', 'name']),
            'rows' => (clone $query)->with(['transaction:id,number,warehouse_id', 'transaction.warehouse:id,name,outlet_id', 'detail:id,transaction_id,product_name,product_sku,quantity'])->latest('occurred_at')->paginate(25),
            'revenue' => (clone $query)->sum('revenue'), 'cost' => (clone $query)->sum('cost'), 'profitTotal' => (clone $query)->sum('profit'),
            'warehouses' => Warehouse::query()->whereIn('outlet_id', $outletIds)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.dashboard', ['title' => __('Laporan laba')]);
    }

    private function query(OutletAccess $access): array
    {
        $this->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'], 'warehouseId' => ['nullable', 'integer']]);
        abort_if(now()->parse($this->from)->diffInDays(now()->parse($this->to)) > 366, 422, __('Rentang laporan maksimal satu tahun.'));
        $outletIds = $access->outletsFor(auth()->user())->pluck('id');
        if ($this->outletId !== '') {
            abort_unless($outletIds->contains((int) $this->outletId), 404);
            $outletIds = collect([(int) $this->outletId]);
        }
        $query = Profit::query()->whereIn('outlet_id', $outletIds)->whereBetween('occurred_at', [$this->from.' 00:00:00', $this->to.' 23:59:59']);
        if ($this->warehouseId !== '') {
            abort_unless(Warehouse::query()->whereIn('outlet_id', $outletIds)->whereKey($this->warehouseId)->exists(), 404);
            $query->whereHas('transaction', fn (Builder $transactions) => $transactions->where('warehouse_id', $this->warehouseId));
        }

        return [$query, $outletIds];
    }
}
