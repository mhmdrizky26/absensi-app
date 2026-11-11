<?php

namespace App\Exports;

use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GuardianContact;
use App\Models\Student;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Every active student of the active year, one sheet per class, plus a sheet
 * for active students without a class. The columns follow the import
 * template (with "Belajar Di" added at the end), so a sheet can be edited
 * and imported again. Only the first parent contact of a student is listed.
 */
class StudentsExport implements Export, WithMultipleSheets
{
    public const HEADINGS = ['NIS', 'NISN', 'Nama', 'JK', 'Kelas', 'Nama Orang Tua', 'Hubungan', 'No WA Orang Tua', 'Bersedia Dihubungi', 'Belajar Di'];

    public function __construct(private ?AcademicYear $academicYear) {}

    /**
     * @return list<ListSheet>
     */
    public function sheets(): array
    {
        $classrooms = $this->academicYear
            ? $this->academicYear->classrooms()->orderBy('grade')->orderBy('name')->get(['id', 'name'])->keyBy('id')
            : collect();

        $students = Student::query()
            ->where('status', StudentStatus::Active)
            ->with([
                'classrooms' => fn ($query) => $query->wherePivot('academic_year_id', $this->academicYear?->id),
                'guardianContacts' => fn ($query) => $query->orderBy('id'),
            ])
            ->orderBy('name')
            ->get();

        $byClassroom = $students->groupBy(fn (Student $student): int => $student->classrooms->first()?->id ?? 0);

        $sheets = $classrooms
            ->map(fn (Classroom $classroom): ListSheet => new ListSheet(
                $classroom->name,
                self::HEADINGS,
                $this->rows($byClassroom->get($classroom->id, collect()), $classroom->name, $classrooms),
            ))
            ->values()
            ->all();

        if ($byClassroom->has(0)) {
            $sheets[] = new ListSheet('Tanpa kelas', self::HEADINGS, $this->rows($byClassroom->get(0), '', $classrooms));
        }

        return $sheets === [] ? [new ListSheet('Siswa', self::HEADINGS, [])] : $sheets;
    }

    /**
     * @param  Collection<int, Student>  $students
     * @param  Collection<int, Classroom>  $classrooms
     * @return list<list<string>>
     */
    private function rows(Collection $students, string $classroomName, Collection $classrooms): array
    {
        return $students->map(function (Student $student) use ($classroomName, $classrooms): array {
            /** @var GuardianContact|null $guardian */
            $guardian = $student->guardianContacts->first();
            $studyRoom = $student->classrooms->first()?->pivot->study_classroom_id;

            return [
                $student->nis,
                (string) $student->nisn,
                $student->name,
                $student->gender->value,
                $classroomName,
                (string) $guardian?->name,
                (string) $guardian?->relation,
                $guardian ? '0'.substr($guardian->phone, 2) : '',
                $guardian ? ($guardian->consented_at ? 'Ya' : 'Tidak') : '',
                $studyRoom ? (string) $classrooms->get($studyRoom)?->name : '',
            ];
        })->values()->all();
    }
}
