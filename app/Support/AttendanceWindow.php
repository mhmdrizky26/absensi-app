<?php

namespace App\Support;

use App\Models\Holiday;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * When the first-period class scan is allowed. Hours and school days come
 * from the admin's settings, falling back to config/attendance.php.
 */
class AttendanceWindow
{
    public const OPENS_AT = 'class_scan_opens_at';

    public const CLOSES_AT = 'class_scan_closes_at';

    public const SCHOOL_DAYS = 'school_days';

    public function isEnforced(): bool
    {
        return (bool) config('attendance.enforce_window');
    }

    public function opensAtTime(): string
    {
        return Setting::value(self::OPENS_AT, config('attendance.class_scan_opens_at'));
    }

    public function closesAtTime(): string
    {
        return Setting::value(self::CLOSES_AT, config('attendance.class_scan_closes_at'));
    }

    /**
     * ISO weekday numbers: 1 = Monday … 7 = Sunday.
     *
     * @return list<int>
     */
    public function schoolDays(): array
    {
        $stored = Setting::value(self::SCHOOL_DAYS);

        return $stored === null
            ? config('attendance.school_days')
            : array_map('intval', array_filter(explode(',', $stored)));
    }

    public function holidayOn(CarbonInterface $date): ?Holiday
    {
        return Holiday::query()->whereDate('date', $date->toDateString())->first();
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

    public function opensAt(CarbonInterface $date): CarbonImmutable
    {
        return $this->timeOn($date, $this->opensAtTime());
    }

    public function closesAt(CarbonInterface $date): CarbonImmutable
    {
        return $this->timeOn($date, $this->closesAtTime());
    }

    /**
     * Why a class cannot be opened at the given moment, or null when it can.
     */
    public function closedReason(CarbonInterface $moment): ?string
    {
        if (! $this->isEnforced()) {
            return null;
        }

        if ($holiday = $this->holidayOn($moment)) {
            return "Hari ini libur ({$holiday->description}), jadi absensi kelas tidak dibuka.";
        }

        if (! $this->isSchoolDay($moment)) {
            return 'Hari ini bukan hari sekolah, jadi absensi kelas tidak dibuka.';
        }

        if ($moment->lessThan($this->opensAt($moment))) {
            return 'Absensi kelas baru dibuka pukul '.$this->opensAt($moment)->format('H:i').'.';
        }

        if ($moment->greaterThanOrEqualTo($this->closesAt($moment))) {
            return 'Absensi kelas sudah ditutup pukul '.$this->closesAt($moment)->format('H:i').'. Siswa yang datang terlambat melapor ke guru piket.';
        }

        return null;
    }

    private function timeOn(CarbonInterface $date, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return CarbonImmutable::parse($date->toDateString(), $date->getTimezone())->setTime($hour, $minute);
    }
}
