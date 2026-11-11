<?php

namespace App\Exports;

/**
 * Empty spreadsheet for importing teacher accounts. Only Nama is required;
 * an empty username or password is made up during the import.
 */
class UserTemplateExport extends ImportTemplate
{
    public function title(): string
    {
        return 'Guru';
    }

    protected function columns(): array
    {
        return [
            'Nama' => 'Wajib. Nama lengkap dengan gelar, misalnya Budi Santoso, S.Pd.',
            'Peran' => 'Boleh kosong (dianggap wali kelas). wali kelas, guru piket, atau guru bk. Guru mapel tidak perlu akun.',
            'Wali Kelas' => 'Boleh kosong. Untuk wali kelas: kelas yang dipegang, misalnya VII-A.',
            'Username' => 'Boleh kosong: dibuat otomatis dari nama, misalnya budi.santoso.',
            'Password' => 'Boleh kosong: dibuat otomatis. Kalau diisi, minimal 8 karakter.',
        ];
    }

    protected function textColumns(): array
    {
        return ['D', 'E'];
    }

    protected function dropdowns(): array
    {
        return [
            'B' => ['wali kelas', 'guru piket', 'guru bk'],
        ];
    }
}
