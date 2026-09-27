<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads the first sheet of a student spreadsheet into plain rows keyed by
 * slugged headings ("Nama Siswa" becomes "nama_siswa"). Saving happens in
 * App\Actions\ImportStudents so the rules can be tested without a file.
 */
class StudentsImport implements ToCollection, WithHeadingRow
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $rows = [];

    public function collection(Collection $rows): void
    {
        if ($this->rows !== []) {
            return;
        }

        $this->rows = $rows->map(fn (Collection $row): array => $row->all())->values()->all();
    }
}
