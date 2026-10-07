<?php

namespace App\Livewire\Reports;

use App\Exports\MasterDataExcelExport;
use App\Models\ProductWarehouse;
use App\Models\Warehouse;
use App\Support\OutletAccess;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryReport extends Component
{
    use WithPagination;

    public string $search = '';

    public string $warehouseId = '';

    public string $outletId = '';

    public bool $belowReorder = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedWarehouseId(): void
    {
        $this->resetPage();
    }

    public function updatedOutletId(): void
    {
        $this->warehouseId = '';
        $this->resetPage();
    }

    public function updatedBelowReorder(): void
    {
        $this->resetPage();
    }

    public function export(OutletAccess $access): StreamedResponse
    {
        abort_unless(auth()->user()->can('reports-export'), 403);
        [$query] = $this->query($access);
        $rows = (clone $query)->with(['product:id,title,sku,unit_id,reorder_point,buy_price', 'product.unit:id,name,symbol', 'warehouse:id,name,outlet_id'])->orderBy('product_id')->get();
        $outletNames = $access->outletsFor(auth()->user())->pluck('name', 'id');

        $data = $rows->map(fn ($row) => [$row->product?->title, $row->product?->sku, $outletNames[$row->warehouse?->outlet_id] ?? '', $row->warehouse?->name, $row->product?->unit?->symbol, $row->stock, $row->reserved_stock, $row->reorder_point, (float) $row->stock * (float) ($row->product?->buy_price ?? 0)])->all();

        return Excel::download(new MasterDataExcelExport(['Produk', 'SKU', 'Outlet', 'Gudang', 'Satuan', 'Stok', 'Reserved', 'Reorder point', 'Nilai beli'], $data, 'Inventori'), 'inventory-'.($this->outletId ?: 'all').'-'.today()->toDateString().'.xlsx');
    }

    public function render(OutletAccess $access)
    {
        abort_unless(auth()->user()->can('reports-access'), 403);
        [$query,$outletIds] = $this->query($access);
        $rows = (clone $query)->with(['product:id,title,sku,unit_id,buy_price', 'product.unit:id,name,symbol', 'warehouse:id,name,outlet_id'])->orderBy('product_id')->paginate(30);

        return view('livewire.reports.inventory-report', [
            'rows' => $rows, 'outlets' => $access->outletsFor(auth()->user())->get(['id', 'name']), 'warehouses' => $this->warehouseQuery($outletIds)->orderBy('name')->get(['id', 'name']),
            'totalProducts' => (clone $query)->distinct('product_id')->count('product_id'),
            'lowCount' => (clone $query)->whereColumn('stock', '<=', 'reorder_point')->count(),
            'stockValue' => (clone $query)->join('products', 'products.id', '=', 'product_warehouses.product_id')->selectRaw('SUM(product_warehouses.stock * products.buy_price) as value')->value('value') ?? 0,
        ])->layout('layouts.dashboard', ['title' => __('Laporan stok')]);
    }

    private function query(OutletAccess $access): array
    {
        $outletIds = $access->outletsFor(auth()->user())->pluck('id');
        if ($this->outletId !== '') {
            abort_unless($outletIds->contains((int) $this->outletId), 404);
            $outletIds = collect([(int) $this->outletId]);
        }
        $warehouseIds = $this->warehouseQuery($outletIds)->pluck('id');
        if ($this->warehouseId !== '' && ! $warehouseIds->contains((int) $this->warehouseId)) {
            abort(404);
        }
        $query = ProductWarehouse::query()->whereIn('warehouse_id', $warehouseIds)
            ->whereHas('product', fn ($products) => $products->where('is_active', true))
            ->when($this->warehouseId !== '' && $warehouseIds->contains((int) $this->warehouseId), fn ($q) => $q->where('warehouse_id', (int) $this->warehouseId))
            ->when($this->search !== '', fn ($q) => $q->whereHas('product', fn ($p) => $p->where('title', 'like', '%'.$this->search.'%')->orWhere('sku', 'like', '%'.$this->search.'%')))
            ->when($this->belowReorder, fn ($q) => $q->whereColumn('stock', '<=', 'reorder_point'));

        return [$query, $outletIds];
    }

    private function warehouseQuery($outletIds)
    {
        return Warehouse::query()->where('is_active', true)->where(function ($query) use ($outletIds): void {
            $query->whereIn('outlet_id', $outletIds);
            if (auth()->user()->hasRole('super-admin') || auth()->user()->can('warehouses-access')) {
                $query->orWhereNull('outlet_id');
            }
        });
    }
}
