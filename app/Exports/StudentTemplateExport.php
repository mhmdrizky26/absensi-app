<?php

namespace App\Exports;

/**
 * Empty spreadsheet with the headings the student import expects. NIS, NISN
 * and the parent's WhatsApp number stay text, so a leading 0 is kept while
 * typing. Only NIS, Nama, JK and Kelas are required.
 */
class StudentTemplateExport extends ImportTemplate
{
    public function title(): string
    {
        return 'Siswa';
    }

    protected function columns(): array
    {
        return [
            'NIS' => 'Wajib. Nomor induk siswa dari sekolah. Siswa dengan NIS yang sudah terdaftar diperbarui, bukan dibuat dobel.',
            'NISN' => 'Boleh kosong. 10 digit; angka 0 di depan tetap tersimpan.',
            'Nama' => 'Wajib. Nama lengkap siswa.',
            'JK' => 'Wajib. L atau P.',
            'Kelas' => 'Wajib. Nama kelas yang sudah dibuat di menu Kelas, misalnya VII-A. Penulisan 7A, 7-a, atau VII A juga dibaca VII-A.',
            'Nama Orang Tua' => 'Boleh kosong.',
            'Hubungan' => 'Boleh kosong. ayah, ibu, atau wali.',
            'No WA Orang Tua' => 'Boleh kosong. Nomor WhatsApp, misalnya 081234567890.',
            'Bersedia Dihubungi' => 'Boleh kosong (dianggap Ya). Tulis Tidak bila orang tua tidak mau dikabari lewat WhatsApp.',
        ];
    }

    protected function textColumns(): array
    {
        return ['A', 'B', 'H'];
    }

    protected function dropdowns(): array
    {
        return [
            'D' => ['L', 'P'],
            'G' => ['ayah', 'ibu', 'wali'],
            'I' => ['Ya', 'Tidak'],
        ];
    }
}
