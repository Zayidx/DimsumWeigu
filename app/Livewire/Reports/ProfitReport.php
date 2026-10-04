<?php

namespace App\Livewire\Reports;

use App\Models\Profit;
use App\Models\Warehouse;
use App\Support\OutletAccess;
use App\Support\CsvCell;
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

    public function mount(): void { $this->from = today()->toDateString(); $this->to = today()->toDateString(); }

    public function applyFilters(): void
    {
        $this->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'], 'warehouseId' => ['nullable', 'integer']]);
        $this->resetPage();
    }

    public function export(OutletAccess $access): StreamedResponse
    {
        abort_unless(auth()->user()->can('reports-export'), 403);
        [$query, $outlet] = $this->query($access);
        $rows = (clone $query)->with(['transaction:id,number,warehouse_id', 'transaction.warehouse:id,name', 'detail:id,transaction_id,product_name,product_sku,quantity'])->orderBy('occurred_at')->get();
        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Waktu', 'Transaksi', 'Gudang', 'Produk', 'SKU', 'Qty', 'Penjualan', 'HPP', 'Laba']);
            foreach ($rows as $row) fputcsv($stream, array_map(CsvCell::safe(...), [$row->occurred_at?->format('Y-m-d H:i:s'), $row->transaction?->number, $row->transaction?->warehouse?->name, $row->detail?->product_name, $row->detail?->product_sku, $row->detail?->quantity, $row->revenue, $row->cost, $row->profit]));
            fclose($stream);
        }, 'profit-'.$outlet->code.'-'.$this->from.'-'.$this->to.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(OutletAccess $access)
    {
        abort_unless(auth()->user()->can('reports-access'), 403);
        [$query, $outlet] = $this->query($access);
        return view('livewire.reports.profit-report', [
            'outlet' => $outlet,
            'rows' => (clone $query)->with(['transaction:id,number,warehouse_id', 'transaction.warehouse:id,name', 'detail:id,transaction_id,product_name,product_sku,quantity'])->latest('occurred_at')->paginate(25),
            'revenue' => (clone $query)->sum('revenue'), 'cost' => (clone $query)->sum('cost'), 'profitTotal' => (clone $query)->sum('profit'),
            'warehouses' => Warehouse::query()->where('outlet_id', $outlet->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.dashboard', ['title' => __('Laporan laba')]);
    }

    private function query(OutletAccess $access): array
    {
        $this->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'], 'warehouseId' => ['nullable', 'integer']]);
        abort_if(now()->parse($this->from)->diffInDays(now()->parse($this->to)) > 366, 422, __('Rentang laporan maksimal satu tahun.'));
        $outlet = $access->current(request());
        $query = Profit::query()->where('outlet_id', $outlet->id)->whereBetween('occurred_at', [$this->from.' 00:00:00', $this->to.' 23:59:59']);
        if ($this->warehouseId !== '') $query->whereHas('transaction', fn (Builder $transactions) => $transactions->where('warehouse_id', $this->warehouseId));
        return [$query, $outlet];
    }
}
