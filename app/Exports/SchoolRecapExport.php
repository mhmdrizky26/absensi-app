<?php

namespace App\Exports;

class SchoolRecapExport extends RecapSheet
{
    /**
     * @param  list<array<string, mixed>>  $classroomRows
     * @param  array<string, int>  $totals
     */
    public function __construct(
        private string $periodLabel,
        private array $classroomRows,
        private array $totals,
        private ?int $rate,
        private string $schoolName,
    ) {}

    public function title(): string
    {
        return 'Rekap sekolah';
    }

    protected function titleLines(): array
    {
        return ["Rekap Absensi {$this->schoolName}", $this->periodLabel];
    }

    protected function headings(): array
    {
        return ['Kelas', 'Wali kelas', 'Siswa', 'Hadir', 'Terlambat', 'Dispensasi', 'Sakit', 'Izin', 'Alpa', 'Kehadiran'];
    }

    protected function rows(): array
    {
        $rows = array_map(fn (array $row): array => [
            $row['name'],
            $row['homeroomTeacher'] ?? '-',
            $row['students'],
            ...array_values($row['counts']),
            $this->percent($row['rate']),
        ], $this->classroomRows);

        $rows[] = ['Total', '', array_sum(array_column($this->classroomRows, 'students')), ...array_values($this->totals), $this->percent($this->rate)];

        return $rows;
    }
}
