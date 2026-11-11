<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * One Excel file with several recap sheets, shown as tabs at the bottom:
 * for example a summary followed by one sheet per class, or a semester
 * total followed by one sheet per month.
 */
class RecapWorkbook implements Export, WithMultipleSheets
{
    /**
     * @param  list<RecapSheet>  $sheets
     */
    public function __construct(private array $sheets) {}

    /**
     * @return list<RecapSheet>
     */
    public function sheets(): array
    {
        return $this->sheets;
    }
}
