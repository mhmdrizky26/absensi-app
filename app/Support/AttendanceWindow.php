<?php

namespace App\Support;

use App\Models\AttendanceDay;
use App\Models\Holiday;
use App\Models\OnlineDay;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The school's weekly schedule: which weekdays are school days and, per day,
 * when the gate opens and when it closes (jam masuk). Set by the admin,
 * falling back to config/attendance.php.
 */
class AttendanceWindow
{
    public const SCHEDULE = 'gate_schedule';

    /**
     * Settings from before the per-day schedule, still read until the admin
     * saves a schedule.
     */
    private const LEGACY_OPENS_AT = 'class_scan_opens_at';

    private const LEGACY_CLOSES_AT = 'class_scan_closes_at';

    private const LEGACY_SCHOOL_DAYS = 'school_days';

    public const WEEKDAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    public function isEnforced(): bool
    {
        return (bool) config('attendance.enforce_window');
    }

    /**
     * Gate hours of each school day, keyed by ISO weekday (1 = Monday … 7 = Sunday).
     * Weekdays that are not school days are left out.
     *
     * @return array<int, array{opens_at: string, closes_at: string}>
     */
    public function schedule(): array
    {
        $stored = Setting::value(self::SCHEDULE);

        if ($stored !== null) {
            $schedule = [];

            foreach (json_decode($stored, true) ?: [] as $weekday => $hours) {
                $schedule[(int) $weekday] = ['opens_at' => $hours['opens_at'], 'closes_at' => $hours['closes_at']];
            }

            ksort($schedule);

            return $schedule;
        }

        $legacyDays = Setting::value(self::LEGACY_SCHOOL_DAYS);
        $days = $legacyDays === null ? config('attendance.school_days') : array_map('intval', array_filter(explode(',', $legacyDays)));
        $hours = [
            'opens_at' => Setting::value(self::LEGACY_OPENS_AT, config('attendance.opens_at')),
            'closes_at' => Setting::value(self::LEGACY_CLOSES_AT, config('attendance.closes_at')),
        ];

        return collect($days)->sort()->mapWithKeys(fn (int $day): array => [$day => $hours])->all();
    }

    /**
     * Every weekday, school day or not, for the admin's schedule form.
     *
     * @return list<array{day: int, label: string, active: bool, opens_at: string, closes_at: string}>
     */
    public function weekForm(): array
    {
        $schedule = $this->schedule();

        return collect(self::WEEKDAYS)->map(fn (string $label, int $day): array => [
            'day' => $day,
            'label' => $label,
            'active' => isset($schedule[$day]),
            'opens_at' => $schedule[$day]['opens_at'] ?? config('attendance.opens_at'),
            'closes_at' => $schedule[$day]['closes_at'] ?? config('attendance.closes_at'),
        ])->values()->all();
    }

    /**
     * @param  array<int, array{opens_at: string, closes_at: string}>  $schedule
     */
    public function saveSchedule(array $schedule): void
    {
        ksort($schedule);

        Setting::put([self::SCHEDULE => json_encode((object) $schedule)]);
    }

    /**
     * ISO weekday numbers: 1 = Monday … 7 = Sunday.
     *
     * @return list<int>
     */
    public function schoolDays(): array
    {
        return array_keys($this->schedule());
    }

    public function holidayOn(CarbonInterface $date): ?Holiday
    {
        return Holiday::query()->whereDate('date', $date->toDateString())->first();
    }

    /**
     * A sekolah daring day: still a school day, but the gate stays shut and
     * students are recorded Hadir automatically.
     */
    public function onlineDayOn(CarbonInterface $date): ?OnlineDay
    {
        return OnlineDay::forDate($date);
    }

    /**
     * A school day is a configured weekday that is not a holiday.
     */
    public function isSchoolDay(CarbonInterface $date): bool
    {
        return in_array($date->isoWeekday(), $this->schoolDays(), true) && ! $this->holidayOn($date);
    }

    /**
     * School days from $from to $to inclusive, with one query for holidays.
     *
     * @return list<CarbonImmutable>
     */
    public function schoolDaysBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        $holidays = Holiday::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')
            ->map(fn (mixed $date): string => substr((string) $date, 0, 10))
            ->flip();
        $schoolDays = $this->schoolDays();
        $days = [];

        for ($day = CarbonImmutable::parse($from->toDateString()); $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            if (in_array($day->isoWeekday(), $schoolDays, true) && ! $holidays->has($day->toDateString())) {
                $days[] = $day;
            }
        }

        return $days;
    }

    /**
     * When the gate opens on the given day.
     */
    public function opensAt(CarbonInterface $date): CarbonImmutable
    {
        return $this->timeOn($date, $this->hoursOn($date)['opens_at']);
    }

    /**
     * When the gate closes on the given day (jam masuk). Students who arrive
     * later are recorded by the guru piket.
     */
    public function closesAt(CarbonInterface $date): CarbonImmutable
    {
        return $this->timeOn($date, $this->hoursOn($date)['closes_at']);
    }

    /**
     * Minutes after jam masuk during which a gate tablet may still send scans
     * it queued before the gate closed (it was offline, or the scan was made
     * seconds before closing). Logging in is refused already.
     */
    public const QUEUE_GRACE_MINUTES = 30;

    /**
     * Why a gate tablet cannot be used at the given moment, or null when it
     * can. Before the gate opens a tablet may log in and wait; after jam
     * masuk, on days without school and once the day is finished it is
     * logged out and cannot log in. In demo mode only a finished day or a
     * sekolah daring day closes it.
     */
    public function gateClosedReason(CarbonInterface $moment): ?string
    {
        if ($onlineDay = $this->onlineDayOn($moment)) {
            return "Hari ini sekolah daring ({$onlineDay->description}), semua siswa otomatis Hadir, jadi tab gerbang tidak dibuka.";
        }

        if (AttendanceDay::forDate($moment)) {
            return 'Absen hari ini sudah diselesaikan guru piket, jadi tab gerbang ditutup sampai besok.';
        }

        if (! $this->isEnforced()) {
            return null;
        }

        if ($holiday = $this->holidayOn($moment)) {
            return "Hari ini libur ({$holiday->description}), jadi tab gerbang tidak dibuka.";
        }

        if (! $this->isSchoolDay($moment)) {
            return 'Hari ini bukan hari sekolah, jadi tab gerbang tidak dibuka.';
        }

        if ($moment->greaterThanOrEqualTo($this->closesAt($moment))) {
            return 'Scan gerbang sudah ditutup pukul '.$this->closesAt($moment)->format('H:i').'. Siswa yang datang terlambat atau lupa kartu melapor ke guru piket.';
        }

        return null;
    }

    /**
     * Whether a tablet may still send scans it queued before jam masuk.
     */
    public function acceptsQueuedScans(CarbonInterface $moment): bool
    {
        return $this->isEnforced()
            && ! AttendanceDay::forDate($moment)
            && ! $this->onlineDayOn($moment)
            && $this->isSchoolDay($moment)
            && $moment->lessThan($this->closesAt($moment)->addMinutes(self::QUEUE_GRACE_MINUTES));
    }

    /**
     * @return array{opens_at: string, closes_at: string}
     */
    private function hoursOn(CarbonInterface $date): array
    {
        return $this->schedule()[$date->isoWeekday()] ?? ['opens_at' => config('attendance.opens_at'), 'closes_at' => config('attendance.closes_at')];
    }

    private function timeOn(CarbonInterface $date, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return CarbonImmutable::parse($date->toDateString(), $date->getTimezone())->setTime($hour, $minute);
    }
}
