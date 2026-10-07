<?php

namespace App\Support;

use App\Enums\StudentStatus;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Early warning: finds students whose attendance pattern needs a closer look
 * by wali kelas or guru BK. Every flag comes from a fixed, explainable rule so
 * teachers can see exactly why a student is listed.
 */
class AttendanceRisk
{
    public const LEVEL_HIGH = 'tinggi';

    public const LEVEL_WATCH = 'perhatian';

    public const STREAK_DAYS = 3;

    public const ABSENT_WATCH = 5;

    public const ABSENT_HIGH = 10;

    public const RATE_WATCH = 85;

    public const RATE_HIGH = 75;

    public const RATE_MIN_MARKS = 10;

    public const WEEKDAY_MIN_ABSENT = 3;

    public const RECENT_DAYS = 10;

    public const DECLINE_POINTS = 15;

    public const LATE_WATCH = 5;

    public const LATE_WINDOW_DAYS = 30;

    public const EXCUSED_WATCH = 6;

    private const WEEKDAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    public function __construct(private AttendanceWindow $window, private AttendanceReport $report) {}

    /**
     * The rules in plain language, shown on the page so the listing is never a black box.
     *
     * @return list<array{level: string, text: string}>
     */
    public static function rules(): array
    {
        return [
            ['level' => self::LEVEL_HIGH, 'text' => 'Alpa '.self::STREAK_DAYS.' hari sekolah berturut-turut atau lebih dalam '.self::LATE_WINDOW_DAYS.' hari terakhir (lebih lama dari itu: perlu perhatian).'],
            ['level' => self::LEVEL_HIGH, 'text' => 'Alpa '.self::ABSENT_HIGH.' kali atau lebih dalam semester ini.'],
            ['level' => self::LEVEL_HIGH, 'text' => 'Kehadiran di bawah '.self::RATE_HIGH.'% (minimal '.self::RATE_MIN_MARKS.' hari tercatat).'],
            ['level' => self::LEVEL_WATCH, 'text' => 'Alpa '.self::ABSENT_WATCH.' kali atau lebih dalam semester ini.'],
            ['level' => self::LEVEL_WATCH, 'text' => 'Kehadiran di bawah '.self::RATE_WATCH.'%.'],
            ['level' => self::LEVEL_WATCH, 'text' => 'Alpa sering jatuh di hari yang sama (minimal '.self::WEEKDAY_MIN_ABSENT.' kali dan separuh dari seluruh alpanya).'],
            ['level' => self::LEVEL_WATCH, 'text' => 'Kehadiran '.self::RECENT_DAYS.' hari sekolah terakhir turun '.self::DECLINE_POINTS.' poin atau lebih dibanding sebelumnya.'],
            ['level' => self::LEVEL_WATCH, 'text' => 'Terlambat '.self::LATE_WATCH.' kali atau lebih dalam '.self::LATE_WINDOW_DAYS.' hari terakhir.'],
            ['level' => self::LEVEL_WATCH, 'text' => 'Sakit dan izin '.self::EXCUSED_WATCH.' kali atau lebih dalam semester ini.'],
        ];
    }

    /**
     * Flagged active students of the classrooms, most serious first.
     *
     * @param  Collection<int, Classroom>  $classrooms
     * @return list<array{id: int, name: string, nis: string, classroomId: int, classroom: string, level: string, reasons: list<array{level: string, text: string}>, counts: array<string, int>, rate: ?int, recent: list<array{date: string, status: ?string}>}>
     */
    public function flagged(Collection $classrooms, CarbonInterface $from, CarbonInterface $to): array
    {
        $days = array_map(fn (CarbonImmutable $day): string => $day->toDateString(), $this->window->schoolDaysBetween($from, $to));
        $classroomNames = $classrooms->pluck('name', 'id');

        $students = Student::query()
            ->join('classroom_student', 'classroom_student.student_id', '=', 'students.id')
            ->whereIn('classroom_student.classroom_id', $classrooms->modelKeys())
            ->where('students.status', StudentStatus::Active)
            ->orderBy('students.name')
            ->get(['students.id', 'students.name', 'students.nis', 'classroom_student.classroom_id']);

        $marks = [];

        foreach (Attendance::query()
            ->whereIn('student_id', $students->modelKeys())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->toBase()
            ->get(['student_id', 'date', 'status']) as $mark) {
            $marks[$mark->student_id][substr((string) $mark->date, 0, 10)] = $mark->status;
        }

        $rows = [];

        foreach ($students as $student) {
            $studentMarks = $marks[$student->id] ?? [];
            $reasons = $this->reasonsFor($studentMarks, $days, $to);

            if ($reasons === []) {
                continue;
            }

            $counts = array_fill_keys(AttendanceReport::STATUSES, 0);

            foreach ($studentMarks as $status) {
                $counts[$status]++;
            }

            $rows[] = [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
                'classroomId' => (int) $student->classroom_id,
                'classroom' => $classroomNames[$student->classroom_id] ?? '',
                'level' => in_array(self::LEVEL_HIGH, array_column($reasons, 'level'), true) ? self::LEVEL_HIGH : self::LEVEL_WATCH,
                'reasons' => $reasons,
                'counts' => $counts,
                'rate' => $this->report->rate($counts),
                'recent' => array_map(fn (string $day): array => ['date' => $day, 'status' => $studentMarks[$day] ?? null], array_slice($days, -self::RECENT_DAYS)),
            ];
        }

        usort($rows, fn (array $a, array $b): int => [$a['level'] !== self::LEVEL_HIGH, -count($a['reasons']), $a['rate'] ?? 100, $a['name']]
            <=> [$b['level'] !== self::LEVEL_HIGH, -count($b['reasons']), $b['rate'] ?? 100, $b['name']]);

        return $rows;
    }

    /**
     * @param  array<string, string>  $marks  date => status letter
     * @param  list<string>  $days  school days in order
     * @return list<array{level: string, text: string}>
     */
    private function reasonsFor(array $marks, array $days, CarbonInterface $to): array
    {
        $reasons = [];
        $counts = array_count_values($marks) + array_fill_keys(AttendanceReport::STATUSES, 0);

        [$streak, $streakEnd] = $this->longestAbsentStreak($marks, $days);

        $recentSince = CarbonImmutable::parse($to->toDateString())->subDays(self::LATE_WINDOW_DAYS)->toDateString();

        if ($streak >= self::STREAK_DAYS) {
            $reasons[] = [
                'level' => $streakEnd > $recentSince ? self::LEVEL_HIGH : self::LEVEL_WATCH,
                'text' => "Alpa {$streak} hari berturut-turut (s.d. ".CarbonImmutable::parse($streakEnd)->translatedFormat('j M').').',
            ];
        }

        if ($counts['A'] >= self::ABSENT_WATCH) {
            $reasons[] = ['level' => $counts['A'] >= self::ABSENT_HIGH ? self::LEVEL_HIGH : self::LEVEL_WATCH, 'text' => "Alpa {$counts['A']} kali semester ini."];
        }

        $rate = $this->report->rate($counts);

        if ($rate !== null && count($marks) >= self::RATE_MIN_MARKS && $rate < self::RATE_WATCH) {
            $reasons[] = ['level' => $rate < self::RATE_HIGH ? self::LEVEL_HIGH : self::LEVEL_WATCH, 'text' => "Kehadiran {$rate}%."];
        }

        if ($weekday = $this->absentWeekdayPattern($marks, $counts['A'])) {
            $reasons[] = ['level' => self::LEVEL_WATCH, 'text' => $weekday];
        }

        if ($decline = $this->recentDecline($marks, $days)) {
            $reasons[] = ['level' => self::LEVEL_WATCH, 'text' => $decline];
        }

        $recentLate = count(array_filter($marks, fn (string $status, string $date): bool => $status === 'T' && $date > $recentSince, ARRAY_FILTER_USE_BOTH));

        if ($recentLate >= self::LATE_WATCH) {
            $reasons[] = ['level' => self::LEVEL_WATCH, 'text' => "Terlambat {$recentLate} kali dalam ".self::LATE_WINDOW_DAYS.' hari terakhir.'];
        }

        if ($counts['S'] + $counts['I'] >= self::EXCUSED_WATCH) {
            $reasons[] = ['level' => self::LEVEL_WATCH, 'text' => 'Sakit '.$counts['S'].' kali dan izin '.$counts['I'].' kali semester ini.'];
        }

        return $reasons;
    }

    /**
     * Longest run of Alpa over consecutive school days. A day without any
     * mark (the class was not scanned) neither extends nor breaks the run.
     *
     * @param  array<string, string>  $marks
     * @param  list<string>  $days
     * @return array{0: int, 1: ?string}
     */
    private function longestAbsentStreak(array $marks, array $days): array
    {
        $current = 0;
        $longest = 0;
        $longestEnd = null;

        foreach ($days as $day) {
            if (! isset($marks[$day])) {
                continue;
            }

            $current = $marks[$day] === 'A' ? $current + 1 : 0;

            if ($current > 0 && $current >= $longest) {
                $longest = $current;
                $longestEnd = $day;
            }
        }

        return [$longest, $longestEnd];
    }

    /**
     * @param  array<string, string>  $marks
     */
    private function absentWeekdayPattern(array $marks, int $absentTotal): ?string
    {
        if ($absentTotal < self::WEEKDAY_MIN_ABSENT) {
            return null;
        }

        $perWeekday = [];

        foreach ($marks as $date => $status) {
            if ($status === 'A') {
                $weekday = CarbonImmutable::parse($date)->isoWeekday();
                $perWeekday[$weekday] = ($perWeekday[$weekday] ?? 0) + 1;
            }
        }

        arsort($perWeekday);
        $weekday = array_key_first($perWeekday);
        $count = $perWeekday[$weekday];

        if ($count < self::WEEKDAY_MIN_ABSENT || $count * 2 < $absentTotal) {
            return null;
        }

        return 'Sering alpa hari '.self::WEEKDAYS[$weekday]." ({$count} dari {$absentTotal} alpa).";
    }

    /**
     * @param  array<string, string>  $marks
     * @param  list<string>  $days
     */
    private function recentDecline(array $marks, array $days): ?string
    {
        $recentDays = array_slice($days, -self::RECENT_DAYS);
        $recent = array_intersect_key($marks, array_flip($recentDays));
        $earlier = array_diff_key($marks, $recent);

        if (count($recent) < 5 || count($earlier) < 5) {
            return null;
        }

        $recentRate = $this->report->rate(array_count_values($recent) + array_fill_keys(AttendanceReport::STATUSES, 0));
        $earlierRate = $this->report->rate(array_count_values($earlier) + array_fill_keys(AttendanceReport::STATUSES, 0));

        if ($earlierRate - $recentRate < self::DECLINE_POINTS) {
            return null;
        }

        return "Kehadiran turun dari {$earlierRate}% menjadi {$recentRate}% dalam ".self::RECENT_DAYS.' hari sekolah terakhir.';
    }
}
