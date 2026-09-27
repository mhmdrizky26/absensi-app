<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Empty spreadsheet with the headings the student import expects. NIS and
 * NISN are formatted as text so Excel keeps leading zeros.
 */
class StudentTemplateExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings
{
    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return ['NIS', 'NISN', 'Nama', 'JK', 'Kelas'];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
        ];
    }
}
