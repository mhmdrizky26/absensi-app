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

class EarlyWarningTest extends TestCase
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
        $this->classroom = Classroom::factory()->for($this->academicYear)->create(['name' => 'VII-A', 'homeroom_teacher_id' => $this->wali->id]);
        $this->otherClassroom = Classroom::factory()->for($this->academicYear)->create(['name' => 'VII-B']);
    }

    /**
     * @param  array<string, string>  $marks  date => status letter
     */
    private function studentWith(string $name, array $marks, ?Classroom $classroom = null): Student
    {
        $classroom ??= $this->classroom;
        $student = Student::factory()->create(['name' => $name]);
        $student->placeIn($classroom, $this->academicYear);

        foreach ($marks as $date => $status) {
            Attendance::factory()->create([
                'student_id' => $student->id,
                'classroom_id' => $classroom->id,
                'date' => $date,
                'status' => AttendanceStatus::from($status),
                'source' => AttendanceSource::Automatic,
            ]);
        }

        return $student;
    }

    public function test_recent_absent_streak_is_high_risk_and_unscanned_days_do_not_break_it(): void
    {
        // Jumat 2 Okt kelas tidak di-scan: rentetan tetap 3 hari.
        $this->studentWith('Ayu', ['2026-09-30' => 'H', '2026-10-01' => 'A', '2026-10-05' => 'A', '2026-10-06' => 'A', '2026-10-07' => 'H']);
        $this->studentWith('Budi', ['2026-10-01' => 'H', '2026-10-05' => 'H', '2026-10-06' => 'T']);

        $this->actingAs(User::factory()->admin()->create())->get('/siswa-berisiko')->assertInertia(fn (Assert $page) => $page
            ->component('Recap/EarlyWarning')
            ->has('students', 1)
            ->where('students.0.name', 'Ayu')
            ->where('students.0.level', 'tinggi')
            ->where('students.0.reasons.0.text', 'Alpa 3 hari berturut-turut (s.d. 6 Okt).')
            ->where('students.0.recent.9', ['date' => '2026-10-07', 'status' => 'H'])
            ->where('period.from', '2026-07-13')
        );
    }

    public function test_weekday_pattern_and_old_streak_only_need_attention(): void
    {
        $marks = ['2026-08-03' => 'A', '2026-08-04' => 'A', '2026-08-05' => 'A'];

        foreach (['2026-09-14', '2026-09-21', '2026-09-28'] as $monday) {
            $marks[$monday] = 'A';
        }

        foreach (['2026-08-06', '2026-08-07', '2026-08-10', '2026-08-11', '2026-08-12', '2026-08-13', '2026-08-14', '2026-08-18', '2026-09-15', '2026-09-16', '2026-09-17', '2026-09-22', '2026-09-23', '2026-09-29', '2026-09-30', '2026-10-01', '2026-10-06', '2026-10-07'] as $day) {
            $marks[$day] = 'H';
        }

        $this->studentWith('Citra', $marks);

        $this->actingAs(User::factory()->admin()->create())->get('/siswa-berisiko')->assertInertia(fn (Assert $page) => $page
            ->where('students.0.level', 'perhatian')
            ->where('students.0.reasons', [
                ['level' => 'perhatian', 'text' => 'Alpa 3 hari berturut-turut (s.d. 5 Agt).'],
                ['level' => 'perhatian', 'text' => 'Alpa 6 kali semester ini.'],
                ['level' => 'perhatian', 'text' => 'Kehadiran 75%.'],
                ['level' => 'perhatian', 'text' => 'Sering alpa hari Senin (4 dari 6 alpa).'],
            ])
        );
    }

    public function test_wali_kelas_sees_only_their_class_and_bk_can_filter(): void
    {
        $streak = ['2026-10-05' => 'A', '2026-10-06' => 'A', '2026-10-07' => 'A'];
        $this->studentWith('Ayu', $streak);
        $this->studentWith('Dodi', $streak, $this->otherClassroom);

        $this->actingAs($this->wali)->get('/siswa-berisiko?classroom='.$this->otherClassroom->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('students', 1)
            ->where('students.0.name', 'Ayu')
            ->where('homeroom', 'VII-A')
            ->where('classrooms', [])
        );

        $bk = User::factory()->guruBk()->create();
        $this->actingAs($bk)->get('/siswa-berisiko')->assertInertia(fn (Assert $page) => $page->has('students', 2));
        $this->actingAs($bk)->get('/siswa-berisiko?classroom='.$this->otherClassroom->id)->assertInertia(fn (Assert $page) => $page
            ->has('students', 1)
            ->where('students.0.name', 'Dodi')
        );
    }

    public function test_guru_piket_cannot_open_early_warning(): void
    {
        $this->actingAs(User::factory()->guruPiket()->create())->get('/siswa-berisiko')->assertForbidden();
    }

    public function test_student_recap_modal_shows_one_students_semester(): void
    {
        $ayu = $this->studentWith('Ayu', ['2026-10-05' => 'S', '2026-10-06' => 'H']);
        Attendance::query()->whereDate('date', '2026-10-05')->update(['note' => 'Demam']);
        $dodi = $this->studentWith('Dodi', ['2026-10-05' => 'A'], $this->otherClassroom);

        $this->actingAs($this->wali)->getJson("/siswa-berisiko/{$ayu->id}")
            ->assertOk()
            ->assertJsonPath('name', 'Ayu')
            ->assertJsonPath('classroom.name', 'VII-A')
            ->assertJsonPath('counts.S', 1)
            ->assertJsonPath('rate', 50)
            ->assertJsonPath('months.0.label', 'Juli 2026')
            ->assertJsonPath('months.3.days.2', ['date' => '2026-10-05', 'day' => 5, 'weekday' => 'Sen', 'status' => 'S'])
            ->assertJsonPath('notes.0.note', 'Demam');

        $this->actingAs($this->wali)->getJson("/siswa-berisiko/{$dodi->id}")->assertForbidden();
        $this->actingAs(User::factory()->guruBk()->create())->getJson("/siswa-berisiko/{$dodi->id}")->assertOk()->assertJsonPath('counts.A', 1);
        $this->actingAs(User::factory()->guruPiket()->create())->getJson("/siswa-berisiko/{$ayu->id}")->assertForbidden();
    }
}
