<?php

namespace App\Exports;

use App\Models\AcademicYear;

/**
 * A full-year attendance summary of the grade IX students, to keep as an
 * archive before they are deleted when the next academic year starts.
 */
class GraduatesArchiveExport extends RecapSheet
{
    /**
     * @param  list<array{classroom: string, row: array<string, mixed>}>  $graduates
     */
    public function __construct(
        private AcademicYear $academicYear,
        private array $graduates,
        private string $schoolName,
    ) {}

    public function title(): string
    {
        return 'Arsip kelas IX';
    }

    protected function titleLines(): array
    {
        return [
            "Arsip Kehadiran Kelas IX · Tahun Ajaran {$this->academicYear->name}",
            $this->schoolName,
            'Diunduh sebelum data siswa kelas IX dihapus pada kenaikan kelas.',
        ];
    }

    protected function headings(): array
    {
        return ['No', 'Kelas', 'NIS', 'NISN', 'Nama', 'Hadir', 'Terlambat', 'Dispensasi', 'Sakit', 'Izin', 'Alpa', 'Kehadiran'];
    }

    protected function rows(): array
    {
        return array_map(fn (array $graduate, int $index): array => [
            $index + 1,
            $graduate['classroom'],
            $graduate['row']['nis'],
            $graduate['row']['nisn'],
            $graduate['row']['name'],
            ...array_values($graduate['row']['counts']),
            $this->percent($graduate['row']['rate']),
        ], $this->graduates, array_keys($this->graduates));
    }
}
