<?php

namespace Tests\Feature\Recap;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrendTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $academicYear;

    private Classroom $classroom;

    private Classroom $otherClassroom;

    private User $wali;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 7)->setTime(10, 0)); // Rabu
        config(['attendance.school_days' => [1, 2, 3, 4, 5]]);

        $this->academicYear = AcademicYear::factory()->active()->create([
            'odd_semester_starts_on' => '2026-07-13', 'even_semester_starts_on' => '2027-01-04', 'ends_on' => '2027-06-26',
        ]);
        $this->wali = User::factory()->waliKelas()->create();
        $this->classroom = Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-A', 'homeroom_teacher_id' => $this->wali->id]);
        $this->otherClassroom = Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-B']);

        $ayu = $this->studentIn($this->classroom);
        $budi = $this->studentIn($this->otherClassroom);

        // VII-A: September dua kali hadir; Oktober Senin alpa, Selasa hadir.
        $this->mark($ayu, $this->classroom, '2026-09-28', 'H');
        $this->mark($ayu, $this->classroom, '2026-09-29', 'H');
        $this->mark($ayu, $this->classroom, '2026-10-05', 'A');
        $this->mark($ayu, $this->classroom, '2026-10-06', 'H');
        // VII-B: semua hadir.
        $this->mark($budi, $this->otherClassroom, '2026-10-05', 'H');
        $this->mark($budi, $this->otherClassroom, '2026-10-06', 'H');
    }

    private function studentIn(Classroom $classroom): Student
    {
        $student = Student::factory()->create();
        $student->placeIn($classroom, $this->academicYear);

        return $student;
    }

    private function mark(Student $student, Classroom $classroom, string $date, string $status): void
    {
        Attendance::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'date' => $date,
            'status' => AttendanceStatus::from($status),
            'source' => AttendanceSource::Automatic,
        ]);
    }

    public function test_admin_sees_school_trends_and_class_ranking(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/dasbor')->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('trends.scopeLabel', 'Seluruh sekolah')
            ->where('trends.period.from', '2026-07-13')
            ->has('trends.monthly', 4)
            ->where('trends.monthly.0.key', '2026-07')
            ->where('trends.monthly.0.rate', null)
            ->where('trends.monthly.2.rate', 100)
            ->where('trends.monthly.3.rate', 75)
            ->where('trends.summary.rate', 83)
            ->where('trends.summary.counts.A', 1)
            ->where('trends.summary.thisMonth.key', '2026-10')
            ->where('trends.summary.lastMonth.key', '2026-09')
            ->where('trends.summary.worstWeekday.label', 'Senin')
            ->where('trends.weekdays.0.absentRate', 33)
            ->has('trends.weekdays', 5)
            ->where('trends.daily.29.key', '2026-10-07')
            ->where('trends.daily.28.rate', 100)
            ->where('trends.classroomRanking.0.name', 'VII-A')
            ->where('trends.classroomRanking.1.name', 'VII-B')
        );
    }

    public function test_wali_kelas_sees_only_their_class(): void
    {
        $this->actingAs($this->wali)->get('/dasbor?classroom='.$this->otherClassroom->id)->assertInertia(fn (Assert $page) => $page
            ->where('trends.scopeLabel', 'Kelas VII-A')
            ->where('trends.summary.counts.H', 3)
            ->where('trends.classroomRanking', [])
            ->where('trends.classrooms', [])
        );
    }

    public function test_guru_bk_can_look_at_one_class_and_guru_piket_has_no_trends(): void
    {
        $this->actingAs(User::factory()->guruBk()->create())->get('/dasbor?classroom='.$this->otherClassroom->id)->assertInertia(fn (Assert $page) => $page
            ->where('trends.scopeLabel', 'Kelas VII-B')
            ->where('trends.summary.rate', 100)
            ->where('trends.classroomRanking', [])
        );

        $this->actingAs(User::factory()->guruPiket()->create())->get('/dasbor')->assertRedirect(route('duty.monitor'));
    }
}
