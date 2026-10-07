<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

abstract class WarehouseExcelValueBinder extends DefaultValueBinder implements WithCustomValueBinder
{
    /** @var array<int, string> */
    protected array $textColumns = [];

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if ($cell->getRow() > 1 && in_array($cell->getColumn(), $this->textColumns, true) && $value !== null) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
