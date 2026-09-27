<?php

namespace App\Enums;

enum AttendanceSource: string
{
    case ClassScan = 'scan_kelas';
    case ClassManual = 'manual_kelas';
    case DutyScan = 'scan_piket';
    case Staff = 'staf';
    case Dispensation = 'dispensasi';
    case Automatic = 'otomatis';

    public function label(): string
    {
        return match ($this) {
            self::ClassScan => 'Scan di kelas',
            self::ClassManual => 'Ditandai guru di kelas',
            self::DutyScan => 'Scan guru piket',
            self::Staff => 'Diisi staf',
            self::Dispensation => 'Dispensasi disetujui',
            self::Automatic => 'Otomatis',
        };
    }
}
