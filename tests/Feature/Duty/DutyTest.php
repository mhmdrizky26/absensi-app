<?php

namespace Tests\Feature\Duty;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DutyTest extends TestCase
{
    use RefreshDatabase;

    private User $piket;

    private AcademicYear $academicYear;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        // Senin, 28 September 2026 pukul 07:45.
        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(7, 45));
        config(['attendance.enforce_window' => true, 'attendance.class_scan_closes_at' => '08:00', 'attendance.school_days' => [1, 2, 3, 4, 5]]);

        $this->piket = User::factory()->guruPiket()->create();
        $this->academicYear = AcademicYear::factory()->active()->create();
        $this->classroom = Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-A']);
    }

    private function studentInClass(array $attributes = [], ?Classroom $classroom = null): Student
    {
        $classroom ??= $this->classroom;
        $student = Student::factory()->create($attributes);
        $student->placeIn($classroom, $this->academicYear);

        return $student;
    }

    private function mark(Student $student, AttendanceStatus $status, ?string $date = '2026-09-28'): Attendance
    {
        return Attendance::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $this->classroom->id,
            'date' => $date,
            'status' => $status,
            'source' => AttendanceSource::Automatic,
        ]);
    }

    public function test_wali_kelas_cannot_use_duty_pages(): void
    {
        $wali = User::factory()->waliKelas()->create();

        $this->actingAs($wali)->get('/pantauan')->assertForbidden();
        $this->actingAs($wali)->postJson('/terlambat', ['student_id' => 1])->assertForbidden();
    }

    public function test_late_scan_turns_alpa_into_terlambat_and_logs_who_did_it(): void
    {
        $student = $this->studentInClass(['name' => 'Ayu']);
        $this->mark($student, AttendanceStatus::Absent);

        $response = $this->actingAs($this->piket)->postJson('/terlambat', ['payload' => $student->qrPayload()]);

        $response->assertOk()->assertJson(['result' => 'recorded', 'student' => ['name' => 'Ayu', 'classroom' => 'VII-A']]);
        $attendance = Attendance::firstOrFail();
        $this->assertSame(AttendanceStatus::Late, $attendance->status);
        $this->assertSame(AttendanceSource::DutyScan, $attendance->source);
        $this->assertTrue($attendance->recorder->is($this->piket));
        $this->assertSame('Datang pukul 07:45, sebelumnya Alpa.', $attendance->note);

        $log = $attendance->logs()->sole();
        $this->assertSame(AttendanceStatus::Absent, $log->from_status);
        $this->assertSame(AttendanceStatus::Late, $log->to_status);
        $this->assertTrue($log->changer->is($this->piket));
    }

    public function test_late_scan_without_an_earlier_mark_creates_one(): void
    {
        $student = $this->studentInClass();

        $this->actingAs($this->piket)->postJson('/terlambat', ['student_id' => $student->id])->assertJson(['result' => 'recorded']);

        $this->assertSame(AttendanceStatus::Late, Attendance::firstOrFail()->status);
        $this->assertTrue(Attendance::firstOrFail()->classroom->is($this->classroom));
    }

    public function test_student_already_present_is_not_marked_late(): void
    {
        $student = $this->studentInClass();
        $this->mark($student, AttendanceStatus::Present);

        $this->actingAs($this->piket)->postJson('/terlambat', ['payload' => $student->qrPayload()])
            ->assertJson(['result' => 'duplicate']);

        $this->assertSame(AttendanceStatus::Present, Attendance::firstOrFail()->status);
    }

    public function test_unknown_revoked_or_inactive_students_are_rejected(): void
    {
        $revoked = $this->studentInClass();
        $revoked->revokeCard();
        $graduated = $this->studentInClass(['status' => StudentStatus::Graduated]);

        $this->actingAs($this->piket)->postJson('/terlambat', ['payload' => 'ABS-XXXXXXXXXXXX'])->assertJson(['result' => 'rejected']);
        $this->actingAs($this->piket)->postJson('/terlambat', ['payload' => $revoked->qrPayload()])->assertJson(['result' => 'rejected']);
        $this->actingAs($this->piket)->postJson('/terlambat', ['student_id' => $graduated->id])->assertJson(['result' => 'rejected']);

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_monitor_shows_each_class_session_and_counts(): void
    {
        $openClass = Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-B']);
        $idleClass = Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-C']);
        $absent = $this->studentInClass(['name' => 'Budi']);
        $this->mark($absent, AttendanceStatus::Absent);
        $this->mark($this->studentInClass(), AttendanceStatus::Present);
        $this->studentInClass([], $idleClass);
        AttendanceSession::factory()->closed()->create(['classroom_id' => $this->classroom->id, 'date' => '2026-09-28']);
        AttendanceSession::factory()->create(['classroom_id' => $openClass->id, 'date' => '2026-09-28']);

        $this->actingAs($this->piket)->get('/pantauan')->assertInertia(fn (Assert $page) => $page
            ->component('Duty/Monitor')
            ->where('window.isSchoolDay', true)
            ->where('classrooms.0.name', 'VII-A')
            ->where('classrooms.0.session.state', 'closed')
            ->where('classrooms.0.counts.H', 1)
            ->where('classrooms.0.counts.A', 1)
            ->where('classrooms.1.session.state', 'open')
            ->where('classrooms.2.session.state', 'not_opened')
            ->where('classrooms.2.total', 1)
            ->missing('absent')
        );
    }

    public function test_class_detail_lists_every_student_with_todays_mark(): void
    {
        $sick = $this->studentInClass(['name' => 'Citra']);
        $this->mark($sick, AttendanceStatus::Sick)->update(['note' => 'Demam']);
        $this->mark($this->studentInClass(['name' => 'Ayu']), AttendanceStatus::Present);
        $this->mark($this->studentInClass(['name' => 'Budi']), AttendanceStatus::Present, '2026-09-25');
        $this->studentInClass(['name' => 'Dodi', 'status' => StudentStatus::Transferred]);

        $this->actingAs($this->piket)->getJson("/pantauan/kelas/{$this->classroom->id}")
            ->assertOk()
            ->assertJsonPath('name', 'VII-A')
            ->assertJsonPath('students.*.name', ['Ayu', 'Budi', 'Citra'])
            ->assertJsonPath('students.0.status', 'H')
            ->assertJsonPath('students.1.status', null)
            ->assertJsonPath('students.2.status', 'S')
            ->assertJsonPath('students.2.note', 'Demam');

        $this->actingAs(User::factory()->waliKelas()->create())->getJson("/pantauan/kelas/{$this->classroom->id}")->assertForbidden();
    }

    public function test_forgotten_sessions_are_closed_after_the_window(): void
    {
        $yesterdaysClass = Classroom::factory()->for($this->academicYear)->create();
        $missingYesterday = $this->studentInClass([], $yesterdaysClass);
        $yesterday = AttendanceSession::factory()->create(['classroom_id' => $yesterdaysClass->id, 'date' => '2026-09-25', 'opened_at' => '2026-09-25 07:00']);
        $missingToday = $this->studentInClass();
        $today = AttendanceSession::factory()->create(['classroom_id' => $this->classroom->id, 'date' => '2026-09-28']);

        $this->artisan('attendance:close-sessions')->assertSuccessful();

        $this->assertTrue($yesterday->fresh()->isClosed());
        $this->assertFalse($today->fresh()->isClosed(), 'Sesi hari ini masih dalam jam absensi.');
        $this->assertSame(AttendanceStatus::Absent, Attendance::where('student_id', $missingYesterday->id)->first()->status);

        $this->travelTo(now()->setTime(8, 0));
        $this->artisan('attendance:close-sessions')->assertSuccessful();

        $this->assertTrue($today->fresh()->isClosed());
        $this->assertSame(AttendanceStatus::Absent, Attendance::where('student_id', $missingToday->id)->first()->status);
    }

    public function test_todays_sessions_stay_open_when_the_window_is_not_enforced(): void
    {
        config(['attendance.enforce_window' => false]);
        $this->travelTo(now()->setTime(20, 0));
        $session = AttendanceSession::factory()->create(['classroom_id' => $this->classroom->id, 'date' => '2026-09-28']);

        $this->artisan('attendance:close-sessions')->assertSuccessful();

        $this->assertFalse($session->fresh()->isClosed());
    }

    public function test_classes_that_never_opened_are_not_marked_alpa(): void
    {
        $this->studentInClass();
        $this->travelTo(now()->setTime(9, 0));

        $this->artisan('attendance:close-sessions')->assertSuccessful();

        $this->assertDatabaseCount('attendances', 0);
    }
}
