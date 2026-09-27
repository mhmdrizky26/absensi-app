<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'H';
    case Late = 'T';
    case Dispensation = 'D';
    case Sick = 'S';
    case Excused = 'I';
    case Absent = 'A';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::Late => 'Terlambat',
            self::Dispensation => 'Dispensasi',
            self::Sick => 'Sakit',
            self::Excused => 'Izin',
            self::Absent => 'Alpa',
        };
    }

    /**
     * Whether the day counts as attended: present, late, or away on an
     * approved school dispensation. Such marks are not overwritten by
     * scans, Sakit/Izin letters or automatic Alpa.
     */
    public function isAtSchool(): bool
    {
        return in_array($this, [self::Present, self::Late, self::Dispensation], true);
    }
}
