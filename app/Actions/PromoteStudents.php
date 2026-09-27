<?php

namespace App\Actions;

use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Dispensation;
use App\Models\Student;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Moves a school from one academic year to the next when the new year is
 * activated: the same classes are created with new codes and no wali kelas,
 * every active student goes up one grade in the same section, grade IX
 * students are deleted permanently, and chosen students repeat the grade or
 * leave the school.
 */
class PromoteStudents
{
    private const FINAL_GRADE = 9;

    /**
     * The year to promote from when $target is activated, or null when
     * activating it should not move anyone: it already has classes, it was
     * promoted before, or it is not later than the current year.
     */
    public function sourceFor(AcademicYear $target): ?AcademicYear
    {
        $source = AcademicYear::active();

        if (! $source || $source->is($target) || $target->promoted_at !== null || $target->classrooms()->exists()) {
            return null;
        }

        if ($target->odd_semester_starts_on->lessThanOrEqualTo($source->odd_semester_starts_on)) {
            return null;
        }

        return $source->classrooms()->exists() ? $source : null;
    }

    /**
     * What will happen to each class of the source year.
     *
     * @return list<array{id: int, name: string, grade: int, target: ?string, students: list<array{id: int, name: string, nis: string}>}>
     */
    public function preview(AcademicYear $source): array
    {
        return $this->sourceClassrooms($source)->map(fn (Classroom $classroom): array => [
            'id' => $classroom->id,
            'name' => $classroom->name,
            'grade' => $classroom->grade,
            'target' => $classroom->grade >= self::FINAL_GRADE ? null : Classroom::nameFor($classroom->grade + 1, $this->section($classroom)),
            'students' => $classroom->students->map(fn (Student $student): array => $student->only('id', 'name', 'nis'))->values()->all(),
        ])->values()->all();
    }

    /**
     * @param  list<int>  $repeaterIds  Students who stay in the same grade (same class name).
     * @param  list<int>  $leaverIds  Students who leave the school (status Pindah, no class).
     * @return array{classrooms: int, promoted: int, repeated: int, graduated: int, left: int}
     */
    public function handle(AcademicYear $source, AcademicYear $target, array $repeaterIds = [], array $leaverIds = []): array
    {
        $repeaters = array_flip($repeaterIds);
        $leavers = array_flip($leaverIds);
        $result = ['classrooms' => 0, 'promoted' => 0, 'repeated' => 0, 'graduated' => 0, 'left' => 0];
        $filesToDelete = [];

        DB::transaction(function () use ($source, $target, $repeaters, $leavers, &$result, &$filesToDelete): void {
            $sourceClassrooms = $this->sourceClassrooms($source);
            $targetClassrooms = $this->createTargetClassrooms($target, $sourceClassrooms);
            $result['classrooms'] = $targetClassrooms->count();

            $placements = [];
            $graduateIds = [];
            $leaverIds = [];

            foreach ($sourceClassrooms as $classroom) {
                foreach ($classroom->students as $student) {
                    if (isset($leavers[$student->id])) {
                        $leaverIds[] = $student->id;
                    } elseif (isset($repeaters[$student->id])) {
                        $placements[$student->id] = $targetClassrooms[$classroom->name]->id;
                        $result['repeated']++;
                    } elseif ($classroom->grade >= self::FINAL_GRADE) {
                        $graduateIds[] = $student->id;
                    } else {
                        $placements[$student->id] = $targetClassrooms[Classroom::nameFor($classroom->grade + 1, $this->section($classroom))]->id;
                        $result['promoted']++;
                    }
                }
            }

            $this->place($target, $placements);

            Student::query()->whereIn('id', $leaverIds)->update(['status' => StudentStatus::Transferred]);
            $result['left'] = count($leaverIds);

            $filesToDelete = $this->attachmentsOf($graduateIds);
            // Riwayat absensi, log, dan dispensasi ikut terhapus lewat foreign key cascade.
            Student::query()->whereIn('id', $graduateIds)->delete();
            $result['graduated'] = count($graduateIds);

            $target->forceFill(['promoted_at' => now()])->save();
            $target->activate();
        });

        Storage::disk('local')->delete($filesToDelete);

        return $result;
    }

    /**
     * @return Collection<int, Classroom>
     */
    private function sourceClassrooms(AcademicYear $source): Collection
    {
        return $source->classrooms()
            ->with(['students' => fn (BelongsToMany $query) => $query->where('status', StudentStatus::Active)->orderBy('name')])
            ->orderBy('grade')
            ->orderBy('name')
            ->get();
    }

    /**
     * Every class name of the source year plus the names promoted students
     * need, each with a new random code and no wali kelas.
     *
     * @param  Collection<int, Classroom>  $sourceClassrooms
     * @return Collection<string, Classroom> keyed by class name
     */
    private function createTargetClassrooms(AcademicYear $target, Collection $sourceClassrooms): Collection
    {
        $names = [];

        foreach ($sourceClassrooms as $classroom) {
            $names[$classroom->name] = $classroom->grade;

            if ($classroom->grade < self::FINAL_GRADE) {
                $names[Classroom::nameFor($classroom->grade + 1, $this->section($classroom))] = $classroom->grade + 1;
            }
        }

        return collect($names)
            ->map(fn (int $grade, string $name): Classroom => $target->classrooms()->create(['grade' => $grade, 'name' => $name]))
            ->sortKeys();
    }

    /**
     * @param  array<int, int>  $placements  student id => classroom id
     */
    private function place(AcademicYear $target, array $placements): void
    {
        $now = now();

        foreach (array_chunk($placements, 500, true) as $chunk) {
            DB::table('classroom_student')->insert(array_map(
                fn (int $studentId, int $classroomId): array => [
                    'academic_year_id' => $target->id,
                    'classroom_id' => $classroomId,
                    'student_id' => $studentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                array_keys($chunk),
                $chunk,
            ));
        }
    }

    /**
     * Letter files of the given students, deleted after their records are.
     *
     * @param  list<int>  $studentIds
     * @return list<string>
     */
    private function attachmentsOf(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        return Attendance::query()->whereIn('student_id', $studentIds)->whereNotNull('attachment_path')->distinct()->pluck('attachment_path')
            ->merge(Dispensation::query()->whereIn('student_id', $studentIds)->whereNotNull('attachment_path')->pluck('attachment_path'))
            ->unique()
            ->values()
            ->all();
    }

    private function section(Classroom $classroom): string
    {
        return Str::after($classroom->name, '-');
    }
}
