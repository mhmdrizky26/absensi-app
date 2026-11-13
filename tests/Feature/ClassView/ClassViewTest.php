<?php

namespace Tests\Feature\ClassView;

use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClassViewTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $academicYear;

    private Classroom $classroom;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(9, 0)); // Senin
        config(['attendance.school_days' => [1, 2, 3, 4, 5]]);

        $this->academicYear = AcademicYear::factory()->active()->create();
        $this->classroom = Classroom::factory()->for($this->academicYear)->create(['grade' => 8, 'name' => 'VIII-C']);
        $this->teacher = User::factory()->guru()->create();
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

    public function test_any_teacher_picks_a_class_and_sees_todays_marks(): void
    {
        $this->studentIn($this->classroom, 'Ayu', AttendanceStatus::Present);
        $this->studentIn($this->classroom, 'Budi', AttendanceStatus::Sick);
        $this->studentIn($this->classroom, 'Citra');

        $this->actingAs($this->teacher)->get('/absen-kelas')->assertInertia(fn (Assert $page) => $page
            ->component('ClassView/Show')
            ->where('classroom', null)
            ->has('classrooms', 1)
            ->where('students', [])
        );

        $this->actingAs($this->teacher)->get("/absen-kelas?kelas={$this->classroom->id}")->assertInertia(fn (Assert $page) => $page
            ->where('classroom.name', 'VIII-C')
            ->has('students', 3)
            ->where('students.0.name', 'Ayu')
            ->where('students.0.status', 'H')
            ->where('students.1.statusLabel', 'Sakit')
            ->where('students.2.status', null)
            ->where('isFinalized', false)
        );
    }

    public function test_guru_bk_and_admin_see_it_too_but_guests_must_log_in(): void
    {
        $this->actingAs(User::factory()->guruBk()->create())->get('/absen-kelas')->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get('/absen-kelas')->assertOk();

        auth()->logout();
        $this->get('/absen-kelas')->assertRedirect(route('login'));
        $this->get('/lihat-kelas')->assertRedirect('/absen-kelas');
    }

    public function test_a_wali_kelas_opens_their_own_class_first(): void
    {
        $this->classroom->update(['homeroom_teacher_id' => $this->teacher->id]);

        $this->actingAs($this->teacher)->get('/absen-kelas')->assertInertia(fn (Assert $page) => $page->where('classroom.name', 'VIII-C'));
    }

    public function test_the_room_includes_students_lodged_from_a_class_without_a_room(): void
    {
        $roomless = Classroom::factory()->for($this->academicYear)->create(['grade' => 8, 'name' => 'VIII-K', 'has_room' => false]);
        $guest = $this->studentIn($roomless, 'Dodi', AttendanceStatus::Present);
        DB::table('classroom_student')->where('student_id', $guest->id)->update(['study_classroom_id' => $this->classroom->id]);
        $this->studentIn($roomless, 'Eka');

        $this->actingAs($this->teacher)->get("/absen-kelas?kelas={$this->classroom->id}")->assertInertia(fn (Assert $page) => $page
            ->has('students', 1)
            ->where('students.0.name', 'Dodi')
            ->where('students.0.from', 'VIII-K')
        );

        $this->actingAs($this->teacher)->get("/absen-kelas?kelas={$roomless->id}")->assertInertia(fn (Assert $page) => $page
            ->where('classroom.hasRoom', false)
            ->has('students', 2)
            ->where('students.0.studyIn', 'VIII-C')
            ->where('students.1.studyIn', null)
        );
    }

    public function test_classes_of_other_years_cannot_be_opened(): void
    {
        $oldClass = Classroom::factory()->for(AcademicYear::factory())->create(['grade' => 7, 'name' => 'VII-Z']);

        $this->actingAs($this->teacher)->get("/absen-kelas?kelas={$oldClass->id}")->assertInertia(fn (Assert $page) => $page->where('classroom', null));
    }

    public function test_class_codes_no_longer_open_anything_on_the_code_screen(): void
    {
        $this->post('/kode', ['code' => '8C'])->assertSessionHasErrors(['code' => 'Kode tidak dikenal. Periksa lagi atau tanyakan ke admin.']);
    }
}
