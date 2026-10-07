<?php

namespace App\Exports;

use App\Models\ProductWarehouse;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class WarehouseStockExport extends WarehouseExcelValueBinder implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    protected array $textColumns = ['A', 'B', 'C', 'D'];

    public function __construct(private readonly Warehouse $warehouse) {}

    public function collection(): Collection
    {
        return ProductWarehouse::query()->with(['product.unit'])
            ->where('warehouse_id', $this->warehouse->id)
            ->whereHas('product', fn ($query) => $query->where('is_active', true)->where('track_stock', true)->where('is_semi_finished', true))
            ->orderBy('product_id')->get()
            ->map(fn (ProductWarehouse $stock) => [
                $stock->product->sku,
                $stock->product->title,
                $stock->product->unit?->symbol,
                $this->warehouse->code,
                (float) $stock->stock,
                (float) $stock->reserved_stock,
                (float) $stock->stock - (float) $stock->reserved_stock,
                (float) $stock->reorder_point,
            ]);
    }

    public function headings(): array
    {
        return ['SKU', 'Produk', 'Satuan', 'Kode Gudang', 'Stok Fisik', 'Stok Direservasi', 'Stok Tersedia', 'Titik Pemesanan'];
    }

    public function title(): string
    {
        return 'Stok Gudang';
    }
}
