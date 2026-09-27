<?php

namespace App\Enums;

enum DispensationStatus: string
{
    case Pending = 'menunggu';
    case Approved = 'disetujui';
    case Rejected = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu persetujuan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }
}
