<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'aktif';
    case Transferred = 'pindah';
    case Graduated = 'lulus';
    case DroppedOut = 'keluar';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Transferred => 'Pindah',
            self::Graduated => 'Lulus',
            self::DroppedOut => 'Keluar',
        };
    }
}
