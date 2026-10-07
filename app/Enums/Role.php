<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case GuruPiket = 'guru_piket';
    case WaliKelas = 'wali_kelas';
    case GuruBk = 'guru_bk';

    /**
     * Human-readable name shown in the interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::GuruPiket => 'Guru Piket',
            self::WaliKelas => 'Wali Kelas',
            self::GuruBk => 'Guru BK',
        };
    }
}
