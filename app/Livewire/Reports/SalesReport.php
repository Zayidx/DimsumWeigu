<?php

namespace App\Livewire\Reports;

use App\Models\CashierShift;
use App\Models\Transaction;
use App\Models\Warehouse;
use App\Support\OutletAccess;
use App\Support\CsvCell;
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
    public string $cashierId = '';
    public string $method = '';

    public function mount(): void
    {
        $this->from = today()->toDateString();
        $this->to = today()->toDateString();
    }

    public function applyFilters(): void
    {
        $this->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'], 'warehouseId' => ['nullable', 'integer'], 'cashierId' => ['nullable', 'integer'], 'method' => ['nullable', 'string', 'max:40']]);
        $this->resetPage();
    }

    public function export(OutletAccess $access): StreamedResponse
    {
        abort_unless(auth()->user()->can('reports-export'), 403);
        [$query, $outlet] = $this->query($access);
        $rows = (clone $query)->with(['user:id,name', 'warehouse:id,name', 'customer:id,name', 'tenders'])->orderBy('occurred_at')->get();
        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Nomor', 'Waktu', 'Pelanggan', 'Kasir', 'Gudang', 'Metode', 'Status pembayaran', 'Subtotal', 'Diskon', 'Pajak', 'Total', 'Dibayar']);
            foreach ($rows as $transaction) {
                fputcsv($stream, array_map(CsvCell::safe(...), [$transaction->number, $transaction->occurred_at?->format('Y-m-d H:i:s'), $transaction->customer?->name, $transaction->user?->name, $transaction->warehouse?->name, $transaction->tenders->pluck('method')->unique()->join('+'), $transaction->payment_status, $transaction->subtotal, $transaction->discount_total, $transaction->tax_total, $transaction->grand_total, $transaction->paid_total]));
            }
            fclose($stream);
        }, 'sales-'.$outlet->code.'-'.$this->from.'-'.$this->to.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(OutletAccess $access)
    {
        abort_unless(auth()->user()->can('reports-access'), 403);
        [$query, $outlet] = $this->query($access);
        $rows = (clone $query)->with(['user:id,name', 'warehouse:id,name', 'customer:id,name', 'tenders'])->latest('occurred_at')->paginate(25);
        return view('livewire.reports.sales-report', [
            'outlet' => $outlet,
            'rows' => $rows,
            'totalSales' => (clone $query)->sum('grand_total'),
            'totalTransactions' => (clone $query)->count(),
            'paidSales' => (clone $query)->where('payment_status', 'paid')->sum('grand_total'),
            'warehouses' => Warehouse::query()->where('outlet_id', $outlet->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'cashiers' => CashierShift::query()->where('outlet_id', $outlet->id)->with('user:id,name')->get()->pluck('user')->filter()->unique('id')->sortBy('name'),
        ])->layout('layouts.dashboard', ['title' => __('Laporan penjualan')]);
    }

    private function query(OutletAccess $access): array
    {
        $this->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'], 'warehouseId' => ['nullable', 'integer'], 'cashierId' => ['nullable', 'integer'], 'method' => ['nullable', 'string', 'max:40']]);
        abort_if(now()->parse($this->from)->diffInDays(now()->parse($this->to)) > 366, 422, __('Rentang laporan maksimal satu tahun.'));
        $outlet = $access->current(request());
        $query = Transaction::query()->where('outlet_id', $outlet->id)->whereBetween('occurred_at', [$this->from.' 00:00:00', $this->to.' 23:59:59'])->whereIn('status', ['completed', 'paid']);
        if ($this->warehouseId !== '') $query->where('warehouse_id', $this->warehouseId);
        if ($this->cashierId !== '') $query->where('user_id', $this->cashierId);
        if ($this->method !== '') $query->whereHas('tenders', fn (Builder $tenders) => $tenders->where('method', $this->method));
        return [$query, $outlet];
    }
}
