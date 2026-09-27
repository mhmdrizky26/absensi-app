<?php

namespace App\Actions;

use App\Enums\Gender;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Validates spreadsheet rows of students and saves them all at once.
 *
 * Nothing is saved when any row is invalid, so the admin can fix the file and
 * upload it again. Students are matched on NIS: known students are updated
 * and moved to the class in the file, unknown ones are created.
 */
class ImportStudents
{
    /**
     * Accepted heading names per field, after slugging ("Nama Siswa" → "nama_siswa").
     */
    private const COLUMNS = [
        'nis' => ['nis', 'nipd', 'no_induk'],
        'nisn' => ['nisn'],
        'name' => ['nama', 'nama_siswa', 'nama_lengkap', 'nama_peserta_didik'],
        'gender' => ['jk', 'lp', 'l_p', 'jk_lp', 'jenis_kelamin'],
        'classroom' => ['kelas', 'rombel', 'rombel_saat_ini', 'nama_rombel'],
    ];

    private const REQUIRED_COLUMNS = [
        'nis' => 'NIS',
        'name' => 'Nama',
        'gender' => 'JK',
        'classroom' => 'Kelas',
    ];

    /**
     * Error messages shown to the admin are capped so a completely wrong file
     * does not produce a thousand lines.
     */
    private const MAX_ERRORS = 50;

    /**
     * @param  list<array<string, mixed>>  $rows  Rows keyed by slugged heading; row 1 of the sheet is the heading.
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function handle(array $rows, AcademicYear $academicYear): array
    {
        $result = ['created' => 0, 'updated' => 0, 'errors' => []];

        $headings = array_keys($rows[0] ?? []);
        $columns = $this->resolveColumns($headings);

        foreach (self::REQUIRED_COLUMNS as $field => $label) {
            if (! isset($columns[$field])) {
                $result['errors'][] = "Kolom \"{$label}\" tidak ditemukan. Gunakan template yang disediakan.";
            }
        }

        if ($result['errors'] !== []) {
            return $result;
        }

        $classroomIds = $academicYear->classrooms()->pluck('id', 'name')->all();
        $parsed = [];
        $seenNis = [];
        $seenNisn = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $values = $this->extract($row, $columns);

            if (implode('', $values) === '') {
                continue;
            }

            $errors = [];

            if ($values['nis'] === '') {
                $errors[] = 'NIS kosong';
            } elseif (! preg_match('/^[0-9A-Za-z.\/-]{1,20}$/', $values['nis'])) {
                $errors[] = "NIS \"{$values['nis']}\" tidak valid";
            } elseif (isset($seenNis[$values['nis']])) {
                $errors[] = "NIS {$values['nis']} sudah muncul di baris {$seenNis[$values['nis']]}";
            }

            if ($values['nisn'] !== '' && ! preg_match('/^\d{10}$/', $values['nisn'])) {
                $errors[] = "NISN \"{$values['nisn']}\" harus 10 digit angka";
            } elseif ($values['nisn'] !== '' && isset($seenNisn[$values['nisn']])) {
                $errors[] = "NISN {$values['nisn']} sudah muncul di baris {$seenNisn[$values['nisn']]}";
            }

            if ($values['name'] === '') {
                $errors[] = 'Nama kosong';
            } elseif (mb_strlen($values['name']) > 100) {
                $errors[] = 'Nama lebih dari 100 karakter';
            }

            $gender = $this->parseGender($values['gender']);

            if (! $gender) {
                $errors[] = "JK \"{$values['gender']}\" harus L atau P";
            }

            $classroomName = $this->normalizeClassroomName($values['classroom']);

            if (! isset($classroomIds[$classroomName])) {
                $errors[] = $values['classroom'] === ''
                    ? 'Kelas kosong'
                    : "Kelas \"{$values['classroom']}\" belum ada di tahun ajaran {$academicYear->name}";
            }

            if ($errors !== []) {
                $result['errors'][] = "Baris {$line}: ".implode('; ', $errors).'.';

                continue;
            }

            $seenNis[$values['nis']] = $line;

            if ($values['nisn'] !== '') {
                $seenNisn[$values['nisn']] = $line;
            }

            $parsed[] = [
                'line' => $line,
                'nis' => $values['nis'],
                'nisn' => $values['nisn'] ?: null,
                'name' => $values['name'],
                'gender' => $gender,
                'classroom_id' => $classroomIds[$classroomName],
            ];
        }

        if ($parsed === [] && $result['errors'] === []) {
            $result['errors'][] = 'File tidak berisi data siswa.';
        }

        $existingStudents = Student::query()->whereIn('nis', array_column($parsed, 'nis'))->get()->keyBy('nis');

        $this->checkNisnConflicts($parsed, $result['errors']);

        if ($result['errors'] !== []) {
            $result['errors'] = $this->capErrors($result['errors']);

            return $result;
        }

        DB::transaction(function () use ($parsed, $existingStudents, $academicYear, &$result): void {
            $placements = [];

            foreach ($parsed as $row) {
                $attributes = [
                    'nisn' => $row['nisn'],
                    'name' => $row['name'],
                    'gender' => $row['gender'],
                ];

                $student = $existingStudents->get($row['nis']);

                if ($student) {
                    $student->update($attributes);
                    $result['updated']++;
                } else {
                    $student = Student::create(['nis' => $row['nis'], ...$attributes]);
                    $result['created']++;
                }

                $placements[$student->id] = $row['classroom_id'];
            }

            DB::table('classroom_student')
                ->where('academic_year_id', $academicYear->id)
                ->whereIn('student_id', array_keys($placements))
                ->delete();

            $now = now();

            foreach (array_chunk($placements, 500, true) as $chunk) {
                DB::table('classroom_student')->insert(array_map(
                    fn (int $studentId, int $classroomId): array => [
                        'academic_year_id' => $academicYear->id,
                        'classroom_id' => $classroomId,
                        'student_id' => $studentId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    array_keys($chunk),
                    $chunk,
                ));
            }
        });

        return $result;
    }

    /**
     * Turn the many ways schools write class names into the stored form:
     * "7A", "7-a", "VII A" and "Kelas VII-A" all become "VII-A".
     */
    public function normalizeClassroomName(string $value): string
    {
        $value = Str::upper(trim($value));
        $value = preg_replace('/^KELAS\s*/', '', $value);
        $value = preg_replace('/[\s_.]+/', '-', $value);

        if (preg_match('/^(VIII|VII|IX|7|8|9)-?([A-Z]{1,2})$/', $value, $matches)) {
            $grade = is_numeric($matches[1]) ? (int) $matches[1] : array_search($matches[1], Classroom::GRADES, true);

            return Classroom::nameFor($grade, $matches[2]);
        }

        return $value;
    }

    private function parseGender(string $value): ?Gender
    {
        return match (Str::upper(preg_replace('/[\s-]+/', '', $value))) {
            'L', 'LAKILAKI', 'PRIA' => Gender::Male,
            'P', 'PEREMPUAN', 'WANITA' => Gender::Female,
            default => null,
        };
    }

    /**
     * @param  list<string>  $headings
     * @return array<string, string>
     */
    private function resolveColumns(array $headings): array
    {
        $columns = [];

        foreach (self::COLUMNS as $field => $aliases) {
            $match = array_values(array_intersect($aliases, $headings))[0] ?? null;

            if ($match !== null) {
                $columns[$field] = $match;
            }
        }

        return $columns;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $columns
     * @return array{nis: string, nisn: string, name: string, gender: string, classroom: string}
     */
    private function extract(array $row, array $columns): array
    {
        $cell = fn (string $field): string => isset($columns[$field]) ? $this->cellToString($row[$columns[$field]] ?? null) : '';

        $nisn = $cell('nisn');

        // Excel drops leading zeros when a NISN is typed into a number cell.
        if (ctype_digit($nisn) && strlen($nisn) < 10) {
            $nisn = str_pad($nisn, 10, '0', STR_PAD_LEFT);
        }

        return [
            'nis' => $cell('nis'),
            'nisn' => $nisn,
            'name' => preg_replace('/\s+/', ' ', $cell('name')),
            'gender' => $cell('gender'),
            'classroom' => $cell('classroom'),
        ];
    }

    private function cellToString(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }

    /**
     * A NISN in the file must not already belong to a different student.
     *
     * @param  list<array{line: int, nis: string, nisn: ?string}>  $parsed
     * @param  list<string>  $errors
     */
    private function checkNisnConflicts(array $parsed, array &$errors): void
    {
        $nisnInFile = array_filter(array_column($parsed, 'nisn', 'line'));

        if ($nisnInFile === []) {
            return;
        }

        $owners = Student::query()->whereIn('nisn', $nisnInFile)->pluck('nis', 'nisn');

        foreach ($parsed as $row) {
            $owner = $row['nisn'] ? $owners->get($row['nisn']) : null;

            if ($owner !== null && $owner !== $row['nis']) {
                $errors[] = "Baris {$row['line']}: NISN {$row['nisn']} sudah dipakai siswa dengan NIS {$owner}.";
            }
        }
    }

    /**
     * @param  list<string>  $errors
     * @return list<string>
     */
    private function capErrors(array $errors): array
    {
        if (count($errors) <= self::MAX_ERRORS) {
            return $errors;
        }

        $hidden = count($errors) - self::MAX_ERRORS;

        return [...array_slice($errors, 0, self::MAX_ERRORS), "…dan {$hidden} kesalahan lainnya."];
    }
}
