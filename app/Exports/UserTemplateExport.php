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
            'Peran' => 'Boleh kosong (dianggap guru). guru atau guru bk. Semua guru, termasuk guru mapel, perlu akun untuk melihat absen kelas.',
            'Wali Kelas' => 'Boleh kosong. Untuk guru yang menjadi wali kelas: kelas yang dipegang, misalnya VII-A.',
            'Username' => 'Boleh kosong: dibuat otomatis dari nama, misalnya budi.santoso.',
            'Password' => 'Boleh kosong: diisi 12345. Kalau diisi sendiri, minimal 8 karakter.',
            'Hari Piket' => 'Boleh kosong. Untuk guru piket: hari piketnya tiap minggu, misalnya Senin, Rabu.',
        ];
    }

    protected function textColumns(): array
    {
        return ['D', 'E'];
    }

    protected function dropdowns(): array
    {
        return [
            'B' => ['guru', 'guru bk'],
        ];
    }
}
