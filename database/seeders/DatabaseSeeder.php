<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Sections per grade and the student total, sized like the target school
     * (3 grades × 11 classes, about 1,200 students).
     */
    private const SECTIONS_PER_GRADE = 11;

    private const STUDENT_COUNT = 1200;

    /**
     * Seed a demo school. Every demo account uses the password "password".
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Administrator',
            'username' => 'admin',
        ]);

        User::factory()->guruPiket()->create(['name' => 'Budi Santoso, S.Pd.', 'username' => 'piket']);
        User::factory()->guruPiket()->create(['name' => 'Dewi Lestari, S.Pd.', 'username' => 'piket2']);
        User::factory()->guruBk()->create(['name' => 'Sri Wahyuni, S.Psi.', 'username' => 'bk']);

        $academicYear = AcademicYear::factory()->active()->create([
            'name' => '2026/2027',
            'odd_semester_starts_on' => '2026-07-13',
            'even_semester_starts_on' => '2027-01-04',
            'ends_on' => '2027-06-26',
        ]);

        $classrooms = $this->seedClassrooms($academicYear);
        $this->seedStudents($academicYear, $classrooms);

        $this->call(DemoAttendanceSeeder::class);
    }

    /**
     * @return list<Classroom>
     */
    private function seedClassrooms(AcademicYear $academicYear): array
    {
        $classrooms = [];
        $sections = array_slice(range('A', 'Z'), 0, self::SECTIONS_PER_GRADE);

        foreach (array_keys(Classroom::GRADES) as $grade) {
            foreach ($sections as $section) {
                $name = Classroom::nameFor($grade, $section);

                $teacher = User::factory()->waliKelas()->create([
                    'name' => fake()->firstName().' '.fake()->lastName().', S.Pd.',
                    'username' => $name === 'VII-A' ? 'walikelas' : 'wali.'.Str::lower(Str::remove('-', $name)),
                ]);

                $classrooms[] = $academicYear->classrooms()->create([
                    'grade' => $grade,
                    'name' => $name,
                    'homeroom_teacher_id' => $teacher->id,
                ]);
            }
        }

        return $classrooms;
    }

    /**
     * NIS starts with the two-digit year the student entered SMP, so grade
     * VII of 2026/2027 gets 26xxxx and grade IX gets 24xxxx.
     *
     * @param  list<Classroom>  $classrooms
     */
    private function seedStudents(AcademicYear $academicYear, array $classrooms): void
    {
        $perClassroom = intdiv(self::STUDENT_COUNT, count($classrooms));
        $remainder = self::STUDENT_COUNT % count($classrooms);
        $sequenceByGrade = [7 => 0, 8 => 0, 9 => 0];
        $placements = [];

        foreach ($classrooms as $index => $classroom) {
            $count = $perClassroom + ($index < $remainder ? 1 : 0);
            $entryYear = 26 - ($classroom->grade - 7);

            $students = Student::factory()
                ->count($count)
                ->sequence(function () use (&$sequenceByGrade, $classroom, $entryYear): array {
                    $sequenceByGrade[$classroom->grade]++;

                    return ['nis' => $entryYear.str_pad((string) $sequenceByGrade[$classroom->grade], 4, '0', STR_PAD_LEFT)];
                })
                ->create();

            foreach ($students as $student) {
                $placements[] = [
                    'academic_year_id' => $academicYear->id,
                    'classroom_id' => $classroom->id,
                    'student_id' => $student->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($placements, 500) as $chunk) {
            DB::table('classroom_student')->insert($chunk);
        }
    }
}
