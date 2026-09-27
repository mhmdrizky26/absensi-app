<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * A recap worksheet: a few title lines, a blank line, a bold heading row
 * and the data rows below it. Zero counts are written as 0, not as blanks.
 */
abstract class RecapSheet implements FromArray, ShouldAutoSize, WithStrictNullComparison, WithStyles, WithTitle
{
    /**
     * @return list<string>
     */
    abstract protected function titleLines(): array;

    /**
     * @return list<string>
     */
    abstract protected function headings(): array;

    /**
     * @return list<list<string|int|null>>
     */
    abstract protected function rows(): array;

    public function array(): array
    {
        return [
            ...array_map(fn (string $line): array => [$line], $this->titleLines()),
            [],
            $this->headings(),
            ...$this->rows(),
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $headingRow = count($this->titleLines()) + 2;

        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            $headingRow => ['font' => ['bold' => true]],
        ];
    }

    /**
     * Percentage text for a rate that may be missing.
     */
    protected function percent(?int $rate): string
    {
        return $rate === null ? '-' : "{$rate}%";
    }
}
