<?php

namespace App\Exports;

use App\Models\Classroom;

class SemesterRecapExport extends RecapSheet
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(
        private Classroom $classroom,
        private string $periodLabel,
        private array $studentRows,
        private string $schoolName,
    ) {}

    public function title(): string
    {
        return 'Rekap '.$this->classroom->name;
    }

    protected function titleLines(): array
    {
        return [
            "Rekap Kehadiran untuk Rapor · Kelas {$this->classroom->name}",
            "{$this->schoolName} · {$this->periodLabel}",
            'Wali kelas: '.($this->classroom->homeroomTeacher?->name ?? '-'),
        ];
    }

    protected function headings(): array
    {
        return ['No', 'NIS', 'NISN', 'Nama', 'Sakit', 'Izin', 'Alpa', 'Hadir', 'Terlambat', 'Dispensasi', 'Kehadiran'];
    }

    protected function rows(): array
    {
        return array_map(fn (array $row, int $index): array => [
            $index + 1,
            $row['nis'],
            $row['nisn'],
            $row['name'],
            $row['counts']['S'],
            $row['counts']['I'],
            $row['counts']['A'],
            $row['counts']['H'],
            $row['counts']['T'],
            $row['counts']['D'],
            $this->percent($row['rate']),
        ], $this->studentRows, array_keys($this->studentRows));
    }
}
