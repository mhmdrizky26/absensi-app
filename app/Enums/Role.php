<?php

namespace App\Enums;

/**
 * Account roles. Wali kelas and guru piket are not roles: a guru is wali kelas
 * by leading a class (Classroom::homeroom_teacher_id) and guru piket on the
 * days of the duty schedule, so one teacher can be both.
 */
enum Role: string
{
    case Admin = 'admin';
    case Guru = 'guru';
    case GuruBk = 'guru_bk';

    /**
     * Human-readable name shown in the interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Guru => 'Guru',
            self::GuruBk => 'Guru BK',
        };
    }
}
