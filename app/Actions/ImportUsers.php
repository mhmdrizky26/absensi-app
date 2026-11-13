<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\DutySchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Validates spreadsheet rows of teacher accounts (guru, guru BK) and saves
 * them all at once; nothing is saved when any row is invalid. A guru may lead
 * a class (Wali Kelas column) and have duty days (Hari Piket column). An empty username is made from the name and an empty password is
 * the default starting password (DEFAULT_PASSWORD). Teachers are matched on
 * username, or on their exact name when the username is empty, so importing the same file again updates instead
 * of creating doubles. Existing passwords are only replaced when the file
 * has one, and duty days only when the Hari Piket cell is filled. Only a
 * guru leads a class or has duty days: a teacher who becomes guru BK loses
 * both.
 */
class ImportUsers
{
    /**
     * Accepted heading names per field, after slugging ("Wali Kelas" → "wali_kelas").
     */
    private const COLUMNS = [
        'name' => ['nama', 'nama_guru', 'nama_lengkap'],
        'role' => ['peran', 'role', 'jabatan', 'tugas'],
        'classroom' => ['wali_kelas', 'kelas', 'wali_kelas_untuk'],
        'username' => ['username', 'usn', 'nama_pengguna'],
        'password' => ['password', 'kata_sandi', 'sandi'],
        'duty_days' => ['hari_piket', 'piket', 'jadwal_piket'],
    ];

    private const MAX_ERRORS = 50;

    /**
     * The starting password of a new account whose Password cell is empty,
     * kept simple so teachers can log in on day one. They change it in
     * their profile.
     */
    public const DEFAULT_PASSWORD = '12345';

    /**
     * Academic titles left out of generated usernames.
     */
    private const TITLES = ['s.pd', 's.pd.i', 's.psi', 's.ag', 's.si', 's.kom', 's.e', 's.h', 's.sos', 's.or', 's.s', 's.t', 'm.pd', 'm.pd.i', 'm.si', 'm.psi', 'm.kom', 'm.ag', 'dr', 'drs', 'dra', 'ir', 'hj', 'h'];

    /**
     * @param  list<array<string, mixed>>  $rows  Rows keyed by slugged heading; row 1 of the sheet is the heading.
     * @return array{created: int, updated: int, errors: list<string>, accounts: list<array{name: string, role: string, classroom: ?string, username: string, password: ?string}>}
     */
    public function handle(array $rows, ?AcademicYear $academicYear): array
    {
        $result = ['created' => 0, 'updated' => 0, 'errors' => [], 'accounts' => []];
        $columns = $this->resolveColumns(array_keys($rows[0] ?? []));

        if (! isset($columns['name'])) {
            $result['errors'][] = 'Kolom "Nama" tidak ditemukan. Gunakan template yang disediakan.';

            return $result;
        }

        $classrooms = $academicYear ? $academicYear->classrooms()->get(['id', 'academic_year_id', 'name', 'homeroom_teacher_id'])->keyBy('name') : collect();
        $existingByUsername = User::query()->get()->keyBy('username');
        $existingByName = User::query()->where('role', '!=', Role::Admin)->get()->groupBy(fn (User $user): string => Str::lower($user->name));
        $takenUsernames = $existingByUsername->keys()->flip()->all();
        $parsed = [];
        $seenUsernames = [];
        $seenClassrooms = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $values = $this->extract($row, $columns);

            if (implode('', $values) === '') {
                continue;
            }

            $errors = [];

            if ($values['name'] === '') {
                $errors[] = 'Nama kosong';
            } elseif (mb_strlen($values['name']) > 100) {
                $errors[] = 'Nama lebih dari 100 karakter';
            }

            $role = $this->parseRole($values['role']);

            if (! $role) {
                $errors[] = "Peran \"{$values['role']}\" harus guru atau guru bk";
            }

            $dutyDays = $this->parseDutyDays($values['duty_days']);

            if ($dutyDays === null) {
                $errors[] = "Hari Piket \"{$values['duty_days']}\" harus nama hari Senin sampai Jumat, misalnya Senin, Rabu";
            } elseif ($dutyDays !== [] && $role && $role !== Role::Guru) {
                $errors[] = 'Kolom Hari Piket hanya untuk peran guru';
            }

            $existing = $values['username'] !== ''
                ? $existingByUsername->get($values['username'])
                : (($existingByName->get(Str::lower($values['name'])) ?? collect())->count() === 1 ? $existingByName->get(Str::lower($values['name']))->first() : null);

            if ($existing?->role === Role::Admin) {
                $errors[] = "Username {$values['username']} milik akun admin dan tidak bisa diubah lewat import";
            }

            if ($values['username'] !== '' && ! preg_match('/^[a-z0-9._-]{3,50}$/', $values['username'])) {
                $errors[] = "Username \"{$values['username']}\" hanya boleh huruf kecil, angka, titik, - dan _ (3–50 karakter)";
            } elseif ($values['username'] !== '' && isset($seenUsernames[$values['username']])) {
                $errors[] = "Username {$values['username']} sudah muncul di baris {$seenUsernames[$values['username']]}";
            }

            if ($values['password'] !== '' && (mb_strlen($values['password']) < 8 || mb_strlen($values['password']) > 72)) {
                $errors[] = 'Password minimal 8 karakter';
            }

            $classroom = null;

            if ($values['classroom'] !== '') {
                $classroomName = ImportStudents::normalizeClassroomName($values['classroom']);
                $classroom = $classrooms->get($classroomName);

                if ($role && $role !== Role::Guru) {
                    $errors[] = 'Kolom Wali Kelas hanya untuk peran guru';
                } elseif (! $classroom) {
                    $errors[] = $academicYear
                        ? "Kelas \"{$values['classroom']}\" belum ada di tahun ajaran {$academicYear->name}"
                        : 'Belum ada tahun ajaran aktif untuk kolom Wali Kelas';
                } elseif (isset($seenClassrooms[$classroom->name])) {
                    $errors[] = "Kelas {$classroom->name} sudah diberikan ke wali kelas di baris {$seenClassrooms[$classroom->name]}";
                } elseif ($classroom->homeroom_teacher_id !== null && $classroom->homeroom_teacher_id !== $existing?->id) {
                    $errors[] = "Kelas {$classroom->name} sudah punya wali kelas lain. Kosongkan dulu di menu Kelas";
                }
            }

            if ($errors !== []) {
                $result['errors'][] = "Baris {$line}: ".implode('; ', $errors).'.';

                if (count($result['errors']) >= self::MAX_ERRORS) {
                    $result['errors'][] = 'Masih ada baris lain yang salah; perbaiki yang di atas dulu.';

                    return $result;
                }

                continue;
            }

            $username = $existing?->username ?? ($values['username'] !== '' ? $values['username'] : $this->uniqueUsername($values['name'], $takenUsernames));
            $takenUsernames[$username] = true;
            $seenUsernames[$username] = $line;

            if ($classroom) {
                $seenClassrooms[$classroom->name] = $line;
            }

            $parsed[] = [
                'existing' => $existing,
                'name' => $values['name'],
                'role' => $role,
                'classroom' => $classroom,
                'username' => $username,
                'password' => $values['password'] !== '' ? $values['password'] : ($existing ? null : self::DEFAULT_PASSWORD),
                'dutyDays' => $dutyDays ?? [],
            ];
        }

        if ($result['errors'] !== [] || $parsed === []) {
            if ($parsed === [] && $result['errors'] === []) {
                $result['errors'][] = 'File tidak berisi data guru.';
            }

            return $result;
        }

        DB::transaction(function () use ($parsed, &$result): void {
            foreach ($parsed as $row) {
                $user = $row['existing'] ?? new User(['username' => $row['username'], 'is_active' => true]);
                $user->fill(['name' => $row['name'], 'role' => $row['role']]);

                if ($row['password'] !== null) {
                    $user->password = $row['password'];
                }

                $isNew = ! $user->exists;
                $user->save();
                $result[$isNew ? 'created' : 'updated']++;

                if ($row['role'] !== Role::Guru) {
                    $user->homeroomClassrooms()->update(['homeroom_teacher_id' => null]);
                    $user->dutySchedules()->delete();
                }

                if ($row['dutyDays'] !== []) {
                    $user->dutySchedules()->delete();

                    foreach ($row['dutyDays'] as $weekday) {
                        $user->dutySchedules()->create(['weekday' => $weekday]);
                    }
                }

                if ($row['classroom']) {
                    Classroom::query()
                        ->where('academic_year_id', $row['classroom']->academic_year_id)
                        ->where('homeroom_teacher_id', $user->id)
                        ->update(['homeroom_teacher_id' => null]);
                    Classroom::query()->whereKey($row['classroom']->id)->update(['homeroom_teacher_id' => $user->id]);
                }

                $result['accounts'][] = [
                    'name' => $user->name,
                    'role' => $user->role->label(),
                    'classroom' => $row['classroom']?->name,
                    'username' => $user->username,
                    'password' => $row['password'],
                ];
            }
        });

        return $result;
    }

    /**
     * "Budi Santoso, S.Pd." → "budi.santoso", with a number added when taken.
     *
     * @param  array<string, mixed>  $taken
     */
    private function uniqueUsername(string $name, array $taken): string
    {
        $words = preg_split('/[\s,]+/', Str::lower(Str::ascii($name))) ?: [];
        $words = array_filter($words, fn (string $word): bool => $word !== '' && ! in_array(rtrim($word, '.'), self::TITLES, true));
        $base = substr(trim((string) preg_replace('/[^a-z0-9.]+/', '', implode('.', array_slice(array_values($words), 0, 2))), '.'), 0, 40) ?: 'guru';
        $base = strlen($base) < 3 ? "{$base}.guru" : $base;

        $username = $base;

        for ($suffix = 2; isset($taken[$username]); $suffix++) {
            $username = $base.$suffix;
        }

        return $username;
    }

    private function parseRole(string $value): ?Role
    {
        return match (Str::lower((string) preg_replace('/[\s_.-]+/', '', $value))) {
            '', 'guru', 'gurumapel', 'mapel', 'walikelas', 'wali', 'walas', 'gurupiket', 'piket' => Role::Guru,
            'gurubk', 'bk', 'bimbingankonseling' => Role::GuruBk,
            default => null,
        };
    }

    /**
     * "Senin, Rabu" → [1, 3]; empty → []; null when a day is not Senin–Jumat.
     *
     * @return list<int>|null
     */
    private function parseDutyDays(string $value): ?array
    {
        $words = preg_split('/[\s,;\/&]+/', Str::lower(str_replace("'", '', $value)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $weekdays = array_flip(array_map(fn (string $day): string => Str::lower($day), DutySchedule::WEEKDAYS));
        $days = [];

        foreach (array_diff($words, ['dan']) as $word) {
            if (! isset($weekdays[$word])) {
                return null;
            }

            $days[] = $weekdays[$word];
        }

        $days = array_values(array_unique($days));
        sort($days);

        return $days;
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
     * @return array{name: string, role: string, classroom: string, username: string, password: string, duty_days: string}
     */
    private function extract(array $row, array $columns): array
    {
        $cell = function (string $field) use ($row, $columns): string {
            $value = isset($columns[$field]) ? ($row[$columns[$field]] ?? null) : null;

            return is_float($value) && floor($value) === $value ? (string) (int) $value : trim((string) $value);
        };

        return [
            'name' => (string) preg_replace('/\s+/', ' ', $cell('name')),
            'role' => $cell('role'),
            'classroom' => $cell('classroom'),
            'username' => Str::lower($cell('username')),
            'password' => $cell('password'),
            'duty_days' => $cell('duty_days'),
        ];
    }
}
