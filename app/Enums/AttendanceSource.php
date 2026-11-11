<?php

namespace App\Enums;

/**
 * How a mark was recorded. ClassScan and ClassManual come from the former
 * per-class scanning and only appear on older marks.
 */
enum AttendanceSource: string
{
    case GateScan = 'scan_gerbang';
    case ClassScan = 'scan_kelas';
    case ClassManual = 'manual_kelas';
    case DutyScan = 'scan_piket';
    case Staff = 'staf';
    case Dispensation = 'dispensasi';
    case Automatic = 'otomatis';

    public function label(): string
    {
        return match ($this) {
            self::GateScan => 'Scan di gerbang',
            self::ClassScan => 'Scan di kelas',
            self::ClassManual => 'Ditandai guru di kelas',
            self::DutyScan => 'Dicatat guru piket',
            self::Staff => 'Diisi staf',
            self::Dispensation => 'Dispensasi disetujui',
            self::Automatic => 'Otomatis',
        };
    }
}
