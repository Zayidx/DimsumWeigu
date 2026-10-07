<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class MasterDataExcelExport extends WarehouseExcelValueBinder implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    protected array $textColumns = [];

    public function __construct(private readonly array $columns, private readonly array $rows, private readonly string $sheetTitle = 'Master Data')
    {
        $this->textColumns = match (mb_strtolower($sheetTitle)) {
            'products' => ['A', 'B', 'C', 'D', 'E', 'R'],
            'ingredients' => ['A', 'B', 'C', 'D', 'G'],
            'customers' => ['A', 'B', 'C', 'D', 'E', 'G', 'J'],
            'suppliers' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'I'],
            default => [],
        };
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->columns;
    }

    public function title(): string
    {
        return mb_substr($this->sheetTitle, 0, 31);
    }
}
