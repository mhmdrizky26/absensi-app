<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Attendance numbers for recaps and exports. Terlambat and Dispensasi count
 * as present; the attendance rate is (H + T + D) divided by all recorded marks.
 */
class AttendanceReport
{
    public const STATUSES = ['H', 'T', 'D', 'S', 'I', 'A'];

    public function __construct(private AttendanceWindow $window) {}

    /**
     * Students of the classroom, including those who have since left, so
     * their past marks still show.
     *
     * @return Collection<int, Student>
     */
    public function students(Classroom $classroom): Collection
    {
        return $classroom->students()->orderBy('name')->get();
    }

    /**
     * A month grid: school days as columns, each student's mark per day.
     *
     * @return array{days: list<string>, students: list<array{id: int, name: string, nis: string, isActive: bool, marks: array<string, array{id: int, status: string}>, counts: array<string, int>, rate: ?int}>, dailyRates: array<string, ?int>}
     */
    public function monthGrid(Classroom $classroom, CarbonInterface $month): array
    {
        $from = CarbonImmutable::parse($month->format('Y-m-01'));
        $to = $from->endOfMonth();
        $days = array_map(fn (CarbonImmutable $day): string => $day->toDateString(), $this->window->schoolDaysBetween($from, $to));
        $students = $this->students($classroom);

        $marks = Attendance::query()
            ->where('classroom_id', $classroom->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['id', 'student_id', 'date', 'status'])
            ->groupBy('student_id');

        $rows = $students->map(function (Student $student) use ($marks): array {
            $studentMarks = [];
            $counts = array_fill_keys(self::STATUSES, 0);

            foreach ($marks->get($student->id, collect()) as $attendance) {
                $studentMarks[$attendance->date->toDateString()] = ['id' => $attendance->id, 'status' => $attendance->status->value];
                $counts[$attendance->status->value]++;
            }

            return [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
                'isActive' => $student->status->value === 'aktif',
                'marks' => $studentMarks,
                'counts' => $counts,
                'rate' => $this->rate($counts),
            ];
        })->values()->all();

        $dailyRates = [];

        foreach ($days as $day) {
            $counts = array_fill_keys(self::STATUSES, 0);

            foreach ($rows as $row) {
                if (isset($row['marks'][$day])) {
                    $counts[$row['marks'][$day]['status']]++;
                }
            }

            $dailyRates[$day] = $this->rate($counts);
        }

        return ['days' => $days, 'students' => $rows, 'dailyRates' => $dailyRates];
    }

    /**
     * Mark counts per student of the classroom between two dates.
     *
     * @return list<array{id: int, name: string, nis: string, nisn: ?string, isActive: bool, counts: array<string, int>, rate: ?int}>
     */
    public function studentTotals(Classroom $classroom, CarbonInterface $from, CarbonInterface $to): array
    {
        $totals = $this->countsBy('student_id', $from, $to, fn ($query) => $query->where('classroom_id', $classroom->id));

        return $this->students($classroom)->map(function (Student $student) use ($totals): array {
            $counts = $totals[$student->id] ?? array_fill_keys(self::STATUSES, 0);

            return [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
                'nisn' => $student->nisn,
                'isActive' => $student->status->value === 'aktif',
                'counts' => $counts,
                'rate' => $this->rate($counts),
            ];
        })->values()->all();
    }

    /**
     * Mark counts per classroom between two dates.
     *
     * @param  Collection<int, Classroom>  $classrooms
     * @return list<array{id: int, name: string, homeroomTeacher: ?string, students: int, counts: array<string, int>, rate: ?int}>
     */
    public function classroomTotals(Collection $classrooms, CarbonInterface $from, CarbonInterface $to): array
    {
        $totals = $this->countsBy('classroom_id', $from, $to, fn ($query) => $query->whereIn('classroom_id', $classrooms->pluck('id')));

        return $classrooms->map(function (Classroom $classroom) use ($totals): array {
            $counts = $totals[$classroom->id] ?? array_fill_keys(self::STATUSES, 0);

            return [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'homeroomTeacher' => $classroom->homeroomTeacher?->name,
                'students' => (int) $classroom->students_count,
                'counts' => $counts,
                'rate' => $this->rate($counts),
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, int>  $counts
     */
    public function rate(array $counts): ?int
    {
        $total = array_sum($counts);

        return $total === 0 ? null : (int) round(($counts['H'] + $counts['T'] + $counts['D']) / $total * 100);
    }

    /**
     * @param  callable(Builder<Attendance>): mixed  $scope
     * @return array<int, array<string, int>>
     */
    private function countsBy(string $column, CarbonInterface $from, CarbonInterface $to, callable $scope): array
    {
        $query = Attendance::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("{$column} as group_id, status, count(*) as total")
            ->groupBy($column, 'status');
        $scope($query);

        $totals = [];

        foreach ($query->toBase()->get() as $row) {
            $totals[$row->group_id] ??= array_fill_keys(self::STATUSES, 0);
            $totals[$row->group_id][$row->status] = (int) $row->total;
        }

        return $totals;
    }

    /**
     * Sum the counts of several rows.
     *
     * @param  list<array{counts: array<string, int>}>  $rows
     * @return array<string, int>
     */
    public function sum(array $rows): array
    {
        $sum = array_fill_keys(self::STATUSES, 0);

        foreach ($rows as $row) {
            foreach (self::STATUSES as $status) {
                $sum[$status] += $row['counts'][$status];
            }
        }

        return $sum;
    }

    /**
     * Human label for a mark letter.
     */
    public static function label(string $status): string
    {
        return AttendanceStatus::from($status)->label();
    }
}
