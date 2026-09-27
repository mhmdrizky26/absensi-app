<?php

namespace App\Exports;

use App\Models\Classroom;
use App\Support\AttendanceReport;
use Carbon\CarbonImmutable;

class MonthlyRecapExport extends RecapSheet
{
    /**
     * @param  array{days: list<string>, students: list<array<string, mixed>>, dailyRates: array<string, ?int>}  $grid
     */
    public function __construct(
        private Classroom $classroom,
        private CarbonImmutable $month,
        private array $grid,
        private string $schoolName,
    ) {}

    public function title(): string
    {
        return $this->classroom->name.' '.$this->month->format('Y-m');
    }

    protected function titleLines(): array
    {
        return [
            "Rekap Absensi Kelas {$this->classroom->name}",
            "{$this->schoolName} · {$this->month->translatedFormat('F Y')}",
            'Wali kelas: '.($this->classroom->homeroomTeacher?->name ?? '-'),
            'H = Hadir, T = Terlambat, D = Dispensasi, S = Sakit, I = Izin, A = Alpa',
        ];
    }

    protected function headings(): array
    {
        return [
            'No', 'NIS', 'Nama',
            ...array_map(fn (string $day): string => (string) CarbonImmutable::parse($day)->day, $this->grid['days']),
            ...AttendanceReport::STATUSES, 'Kehadiran',
        ];
    }

    protected function rows(): array
    {
        $rows = [];

        foreach ($this->grid['students'] as $index => $student) {
            $rows[] = [
                $index + 1,
                $student['nis'],
                $student['name'],
                ...array_map(fn (string $day): string => $student['marks'][$day]['status'] ?? '', $this->grid['days']),
                ...array_values($student['counts']),
                $this->percent($student['rate']),
            ];
        }

        return $rows;
    }
}
