<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * An empty spreadsheet to fill in and import. The heading row is bold and
 * frozen, every heading carries a note explaining the column, number-like
 * columns are text all the way down (so Excel keeps a leading 0 as it is
 * typed) and columns with fixed answers get a drop-down list.
 */
abstract class ImportTemplate implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    /**
     * Rows below the heading that get the text format and drop-down lists.
     */
    private const PREPARED_ROWS = 2000;

    /**
     * @return array<string, string> heading => note shown when hovering it
     */
    abstract protected function columns(): array;

    /**
     * Column letters that must stay text, e.g. NIS and phone numbers.
     *
     * @return list<string>
     */
    protected function textColumns(): array
    {
        return [];
    }

    /**
     * Column letter => allowed answers, offered as a drop-down list.
     *
     * @return array<string, list<string>>
     */
    protected function dropdowns(): array
    {
        return [];
    }

    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return array_keys($this->columns());
    }

    /**
     * @return array<class-string, callable(AfterSheet): void>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => $this->prepare($event->sheet->getDelegate()),
        ];
    }

    private function prepare(Worksheet $sheet): void
    {
        $lastRow = self::PREPARED_ROWS + 1;
        $lastColumn = chr(ord('A') + count($this->columns()) - 1);

        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EEFB');
        $sheet->freezePane('A2');

        foreach (array_values($this->columns()) as $index => $note) {
            $sheet->getComment(chr(ord('A') + $index).'1')->getText()->createTextRun($note);
        }

        foreach ($this->textColumns() as $column) {
            $sheet->getStyle("{$column}2:{$column}{$lastRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        foreach ($this->dropdowns() as $column => $options) {
            $validation = $sheet->getCell("{$column}2")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST)
                ->setAllowBlank(true)
                ->setShowDropDown(true)
                ->setShowErrorMessage(true)
                ->setErrorTitle('Isian tidak dikenal')
                ->setError('Pilih salah satu dari daftar: '.implode(', ', $options).'.')
                ->setFormula1('"'.implode(',', $options).'"');
            $validation->setSqref("{$column}2:{$column}{$lastRow}");
        }
    }
}
