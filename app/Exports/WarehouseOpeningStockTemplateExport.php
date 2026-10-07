<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class WarehouseOpeningStockTemplateExport extends WarehouseExcelValueBinder implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    protected array $textColumns = ['A', 'B', 'C'];

    public function collection()
    {
        return Product::query()->with('unit')->where('is_active', true)->where('track_stock', true)->where('is_semi_finished', true)->orderBy('sku')->get()
            ->map(fn (Product $product) => [
                $product->sku, $product->title, $product->unit?->symbol, null, null, null, null,
            ]);
    }

    public function headings(): array
    {
        return ['SKU', 'Produk', 'Satuan', 'Stok Awal', 'Alasan', 'Nomor Batch', 'Tanggal Kedaluwarsa'];
    }

    public function title(): string
    {
        return 'Template Stok Awal';
    }
}
