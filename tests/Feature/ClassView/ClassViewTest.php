<?php

namespace Tests\Feature\ClassView;

use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClassViewTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $academicYear;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(9, 0)); // Senin
        config(['attendance.school_days' => [1, 2, 3, 4, 5]]);

        $this->academicYear = AcademicYear::factory()->active()->create();
        $this->classroom = Classroom::factory()->for($this->academicYear)->create(['grade' => 8, 'name' => 'VIII-C']);
    }

    private function studentIn(Classroom $classroom, string $name, ?AttendanceStatus $status = null): Student
    {
        $student = Student::factory()->create(['name' => $name]);
        $student->placeIn($classroom, $this->academicYear);

        if ($status) {
            Attendance::factory()->create(['student_id' => $student->id, 'classroom_id' => $classroom->id, 'date' => '2026-09-28', 'status' => $status]);
        }

        return $student;
    }

    public function test_teacher_opens_a_class_with_its_short_code_and_sees_todays_marks(): void
    {
        $this->studentIn($this->classroom, 'Ayu', AttendanceStatus::Present);
        $this->studentIn($this->classroom, 'Budi', AttendanceStatus::Sick);
        $this->studentIn($this->classroom, 'Citra');

        $this->post('/kode', ['code' => ' 8c '])->assertRedirect(route('class-view.show'));

        $this->get('/lihat-kelas/hari-ini')->assertInertia(fn (Assert $page) => $page
            ->component('ClassView/Show')
            ->where('classroom.name', 'VIII-C')
            ->has('students', 3)
            ->where('students.0.name', 'Ayu')
            ->where('students.0.status', 'H')
            ->where('students.1.statusLabel', 'Sakit')
            ->where('students.2.status', null)
            ->where('isFinalized', false)
        );

        $this->get('/lihat-kelas')->assertRedirect('/kode');
        $this->post('/lihat-kelas/ganti')->assertRedirect(route('code.login'));
        $this->get('/lihat-kelas/hari-ini')->assertRedirect(route('code.login'));
    }

    public function test_the_room_includes_students_lodged_from_a_class_without_a_room(): void
    {
        $roomless = Classroom::factory()->for($this->academicYear)->create(['grade' => 8, 'name' => 'VIII-K', 'has_room' => false]);
        $guest = $this->studentIn($roomless, 'Dodi', AttendanceStatus::Present);
        DB::table('classroom_student')->where('student_id', $guest->id)->update(['study_classroom_id' => $this->classroom->id]);
        $this->studentIn($roomless, 'Eka');

        $this->post('/kode', ['code' => '8C']);
        $this->get('/lihat-kelas/hari-ini')->assertInertia(fn (Assert $page) => $page
            ->has('students', 1)
            ->where('students.0.name', 'Dodi')
            ->where('students.0.from', 'VIII-K')
        );

        $this->post('/kode', ['code' => '8K']);
        $this->get('/lihat-kelas/hari-ini')->assertInertia(fn (Assert $page) => $page
            ->where('classroom.hasRoom', false)
            ->has('students', 2)
            ->where('students.0.studyIn', 'VIII-C')
            ->where('students.1.studyIn', null)
        );
    }

    public function test_wrong_codes_are_rejected_and_throttled(): void
    {
        $oldYear = Classroom::factory()->for(AcademicYear::factory())->create(['grade' => 7, 'name' => 'VII-Z']);

        $this->post('/kode', ['code' => $oldYear->view_code])->assertSessionHasErrors('code');

        foreach (range(1, 9) as $attempt) {
            $this->post('/kode', ['code' => 'salah'])->assertSessionHasErrors('code');
        }

        $this->post('/kode', ['code' => '8C'])->assertSessionHasErrors(['code' => 'Terlalu banyak kode salah. Coba lagi dalam 60 detik.']);
    }

    public function test_changing_the_code_closes_pages_opened_with_the_old_one(): void
    {
        $this->post('/kode', ['code' => '8C']);
        $this->classroom->update(['view_code' => '8CRAHASIA']);

        $this->get('/lihat-kelas/hari-ini')->assertRedirect(route('code.login'));
    }
}
