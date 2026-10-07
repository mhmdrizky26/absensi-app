<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Attendance over time for the dashboard trend charts: per month, per weekday
 * and per school day, for any set of classrooms.
 */
class AttendanceTrend
{
    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    private const WEEKDAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    public function __construct(private AttendanceWindow $window, private AttendanceReport $report, private RecapScope $scope) {}

    /**
     * Everything the dashboard trend panel shows: the whole school (admin,
     * guru BK, optionally one class) or the wali kelas's own class.
     *
     * @return array<string, mixed>
     */
    public function overview(User $user, ?int $classroomId, bool $wholeYear): array
    {
        $academicYear = AcademicYear::active();
        $classrooms = $this->scope->classroomsFor($user);
        $isHomeroomTeacher = $user->hasRole(Role::WaliKelas);
        $selectedId = $isHomeroomTeacher ? null : $classroomId;
        $target = $selectedId ? new EloquentCollection([$this->scope->classroomFor($user, $selectedId)]) : $classrooms;

        $props = [
            'scopeLabel' => $isHomeroomTeacher || $selectedId ? 'Kelas '.($target->first()?->name ?? '—') : 'Seluruh sekolah',
            'classrooms' => $isHomeroomTeacher ? [] : $classrooms->map(fn (Classroom $classroom): array => $classroom->only('id', 'name'))->values(),
            'filters' => ['classroom' => $selectedId, 'periode' => $wholeYear ? 'tahun' : 'semester'],
            'homeroom' => $isHomeroomTeacher ? $classrooms->first()?->name : null,
            'isHomeroomTeacher' => $isHomeroomTeacher,
            'period' => null,
            'summary' => null,
            'monthly' => [],
            'weekdays' => [],
            'daily' => [],
            'classroomRanking' => [],
        ];

        if (! $academicYear || $target->isEmpty()) {
            return $props;
        }

        $ids = $target->modelKeys();
        [$semesterFrom, $to, $semester] = $academicYear->semesterSoFar(today());
        $from = $wholeYear ? $academicYear->odd_semester_starts_on->toImmutable() : $semesterFrom;
        $monthly = $this->monthly($ids, $academicYear->odd_semester_starts_on, $to);
        $weekdays = $this->weekdays($ids, $from, $to);
        $counts = $this->report->sum($weekdays);
        $recordedMonths = array_values(array_filter($monthly, fn (array $month): bool => $month['rate'] !== null));
        $worstWeekday = collect($weekdays)->filter(fn (array $day): bool => $day['absentRate'] !== null)->sortByDesc('absentRate')->first();

        $ranking = [];

        if ($target->count() > 1) {
            $target->loadCount('students')->load('homeroomTeacher:id,name');
            $ranking = collect($this->report->classroomTotals($target, $from, $to))
                ->sortBy(fn (array $row): int => $row['rate'] ?? 101)
                ->values()
                ->all();
        }

        return [
            ...$props,
            'period' => [
                'label' => $wholeYear ? "Tahun ajaran {$academicYear->name}" : "Semester {$semester} {$academicYear->name}",
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'summary' => [
                'counts' => $counts,
                'rate' => $this->report->rate($counts),
                'thisMonth' => end($recordedMonths) ?: null,
                'lastMonth' => $recordedMonths[count($recordedMonths) - 2] ?? null,
                'worstWeekday' => $worstWeekday,
            ],
            'monthly' => $monthly,
            'weekdays' => $weekdays,
            'daily' => $this->daily($ids, $academicYear->odd_semester_starts_on, $to),
            'classroomRanking' => $ranking,
        ];
    }

    /**
     * @param  list<int>  $classroomIds
     * @return list<array{key: string, label: string, counts: array<string, int>, rate: ?int}>
     */
    public function monthly(array $classroomIds, CarbonInterface $from, CarbonInterface $to): array
    {
        $grouped = $this->countsGroupedBy("DATE_FORMAT(date, '%Y-%m')", $classroomIds, $from, $to);
        $months = [];

        for ($month = CarbonImmutable::parse($from->format('Y-m-01')); $month->lessThanOrEqualTo($to); $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $counts = $grouped[$key] ?? $this->emptyCounts();
            $months[] = ['key' => $key, 'label' => self::MONTHS[$month->month - 1].' '.$month->format('y'), 'counts' => $counts, 'rate' => $this->report->rate($counts)];
        }

        return $months;
    }

    /**
     * Only the configured school days appear.
     *
     * @param  list<int>  $classroomIds
     * @return list<array{key: string, label: string, counts: array<string, int>, rate: ?int, absentRate: ?int}>
     */
    public function weekdays(array $classroomIds, CarbonInterface $from, CarbonInterface $to): array
    {
        $grouped = $this->countsGroupedBy('WEEKDAY(date) + 1', $classroomIds, $from, $to);
        $schoolDays = $this->window->schoolDays();
        sort($schoolDays);

        return array_map(function (int $weekday) use ($grouped): array {
            $counts = $grouped[$weekday] ?? $this->emptyCounts();
            $total = array_sum($counts);

            return [
                'key' => (string) $weekday,
                'label' => self::WEEKDAYS[$weekday],
                'counts' => $counts,
                'rate' => $this->report->rate($counts),
                'absentRate' => $total === 0 ? null : (int) round($counts['A'] / $total * 100),
            ];
        }, $schoolDays);
    }

    /**
     * The last school days up to $to, one point per day (null when nothing was recorded).
     *
     * @param  list<int>  $classroomIds
     * @return list<array{key: string, label: string, counts: array<string, int>, rate: ?int}>
     */
    public function daily(array $classroomIds, CarbonInterface $from, CarbonInterface $to, int $limit = 30): array
    {
        $days = array_slice($this->window->schoolDaysBetween($from, $to), -$limit);

        if ($days === []) {
            return [];
        }

        $grouped = $this->countsGroupedBy('DATE(date)', $classroomIds, $days[0], end($days));

        return array_map(function (CarbonImmutable $day) use ($grouped): array {
            $counts = $grouped[$day->toDateString()] ?? $this->emptyCounts();

            return ['key' => $day->toDateString(), 'label' => $day->translatedFormat('j M'), 'counts' => $counts, 'rate' => $this->report->rate($counts)];
        }, $days);
    }

    /**
     * @param  list<int>  $classroomIds
     * @return array<string, array<string, int>>
     */
    private function countsGroupedBy(string $expression, array $classroomIds, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = Attendance::query()
            ->toBase()
            ->whereIn('classroom_id', $classroomIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("{$expression} as bucket, status, count(*) as total")
            ->groupBy('bucket', 'status')
            ->get();

        $grouped = [];

        foreach ($rows as $row) {
            $bucket = (string) $row->bucket;
            $grouped[$bucket] ??= $this->emptyCounts();
            $grouped[$bucket][$row->status] = (int) $row->total;
        }

        return $grouped;
    }

    /**
     * @return array<string, int>
     */
    private function emptyCounts(): array
    {
        return array_fill_keys(AttendanceReport::STATUSES, 0);
    }
}
