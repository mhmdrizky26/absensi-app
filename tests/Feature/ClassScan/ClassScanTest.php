<?php

namespace Tests\Feature\ClassScan;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Http\Middleware\EnsureClassSession;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClassScanTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $academicYear;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        // Senin, 28 September 2026 pukul 07:00 WIB: di dalam jam absensi.
        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(7, 0));
        config([
            'attendance.enforce_window' => true,
            'attendance.class_scan_opens_at' => '06:30',
            'attendance.class_scan_closes_at' => '08:00',
            'attendance.school_days' => [1, 2, 3, 4, 5],
        ]);

        $this->academicYear = AcademicYear::factory()->active()->create();
        $this->classroom = Classroom::factory()->for($this->academicYear)->create(['name' => 'VII-A']);
    }

    private function studentInClass(?Classroom $classroom = null, array $attributes = []): Student
    {
        $classroom ??= $this->classroom;
        $student = Student::factory()->create($attributes);
        $student->placeIn($classroom, $classroom->academicYear);

        return $student;
    }

    private function openSession(): AttendanceSession
    {
        $this->post('/absen', ['code' => $this->classroom->access_code])->assertRedirect(route('class.scanner'));

        return AttendanceSession::firstOrFail();
    }

    /**
     * @param  list<array<string, mixed>>  $scans
     */
    private function sendScans(array $scans): array
    {
        return $this->postJson('/absen/kelas/scan', ['scans' => $scans])->assertOk()->json('results');
    }

    public function test_correct_code_opens_todays_session(): void
    {
        $this->post('/absen', ['code' => strtolower($this->classroom->access_code)])
            ->assertRedirect(route('class.scanner'));

        $session = AttendanceSession::firstOrFail();
        $this->assertTrue($session->classroom->is($this->classroom));
        $this->assertSame('2026-09-28', $session->date->toDateString());
        $this->assertSame($session->id, session(EnsureClassSession::SESSION_KEY));
    }

    public function test_a_custom_code_set_by_the_admin_opens_the_class(): void
    {
        $this->classroom->forceFill(['access_code' => 'VIIAPAGI'])->save();

        $this->post('/absen', ['code' => 'viia pagi'])->assertRedirect(route('class.scanner'));
    }

    public function test_wrong_code_is_rejected_and_repeated_failures_are_throttled(): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->post('/absen', ['code' => 'ZZZZZZ'])->assertSessionHasErrors('code');
        }

        $this->post('/absen', ['code' => $this->classroom->access_code])
            ->assertSessionHasErrors(['code' => 'Terlalu banyak kode salah. Coba lagi dalam 60 detik.']);
        $this->assertDatabaseCount('attendance_sessions', 0);
    }

    public function test_code_of_an_inactive_academic_year_does_not_work(): void
    {
        $oldClassroom = Classroom::factory()->for(AcademicYear::factory())->create();

        $this->post('/absen', ['code' => $oldClassroom->access_code])->assertSessionHasErrors('code');
    }

    public function test_code_only_works_inside_the_scan_window(): void
    {
        $this->travelTo(now()->setTime(8, 5));
        $this->post('/absen', ['code' => $this->classroom->access_code])->assertSessionHasErrors('code');

        $this->travelTo(now()->setDate(2026, 9, 27)->setTime(7, 0)); // Minggu
        $this->post('/absen', ['code' => $this->classroom->access_code])->assertSessionHasErrors('code');

        $this->assertDatabaseCount('attendance_sessions', 0);
    }

    public function test_window_can_be_disabled_for_demos(): void
    {
        config(['attendance.enforce_window' => false]);
        $this->travelTo(now()->setDate(2026, 9, 27)->setTime(21, 0));

        $this->post('/absen', ['code' => $this->classroom->access_code])->assertRedirect(route('class.scanner'));
    }

    public function test_scanner_requires_a_class_session(): void
    {
        $this->get('/absen/kelas')->assertRedirect(route('class.login'));
        $this->postJson('/absen/kelas/scan', ['scans' => []])->assertUnauthorized();
    }

    public function test_session_from_an_earlier_day_expires(): void
    {
        $this->openSession();
        $this->travelTo(now()->addDay());

        $this->get('/absen/kelas')->assertRedirect(route('class.login'));
    }

    public function test_scanner_gets_card_fingerprints_but_never_the_tokens(): void
    {
        $student = $this->studentInClass();
        $revoked = $this->studentInClass();
        $revoked->revokeCard();
        $this->openSession();

        $response = $this->get('/absen/kelas');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Scan/Scanner')
            ->where('classroom.name', 'VII-A')
            ->has('roster', 2)
            ->where('roster', fn ($roster) => collect($roster)->firstWhere('id', $student->id)['hash'] === $student->qrHash()
                && collect($roster)->firstWhere('id', $revoked->id)['hash'] === null)
        );
        $response->assertDontSee($student->qr_token);
    }

    public function test_scanning_a_card_marks_the_student_present(): void
    {
        $student = $this->studentInClass();
        $session = $this->openSession();

        $results = $this->sendScans([['ref' => 'a', 'method' => 'scan', 'payload' => $student->qrPayload(), 'scanned_at' => now()->toIso8601String()]]);

        $this->assertSame('recorded', $results[0]['result']);
        $attendance = Attendance::firstOrFail();
        $this->assertSame(AttendanceStatus::Present, $attendance->status);
        $this->assertSame(AttendanceSource::ClassScan, $attendance->source);
        $this->assertTrue($attendance->session->is($session));
    }

    public function test_second_scan_of_the_same_student_is_a_duplicate(): void
    {
        $student = $this->studentInClass();
        $this->openSession();
        $scan = ['method' => 'scan', 'payload' => $student->qrPayload()];

        $this->sendScans([['ref' => 'a', ...$scan]]);
        $results = $this->sendScans([['ref' => 'b', ...$scan]]);

        $this->assertSame('duplicate', $results[0]['result']);
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_cards_from_other_classes_and_revoked_cards_are_rejected(): void
    {
        $otherClassroom = Classroom::factory()->for($this->academicYear)->create(['name' => 'VIII-B']);
        $stranger = $this->studentInClass($otherClassroom, ['name' => 'Rudi']);
        $revoked = $this->studentInClass();
        $revoked->revokeCard();
        $this->openSession();

        $results = $this->sendScans([
            ['ref' => 'a', 'method' => 'scan', 'payload' => $stranger->qrPayload()],
            ['ref' => 'b', 'method' => 'scan', 'payload' => $revoked->qrPayload()],
            ['ref' => 'c', 'method' => 'scan', 'payload' => 'ABS-TIDAKADA1234'],
        ]);

        $this->assertSame(['rejected', 'rejected', 'rejected'], array_column($results, 'result'));
        $this->assertSame('Rudi adalah siswa kelas VIII-B.', $results[0]['message']);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_teacher_can_mark_a_student_without_a_card(): void
    {
        $student = $this->studentInClass();
        $stranger = $this->studentInClass(Classroom::factory()->for($this->academicYear)->create());
        $this->openSession();

        $results = $this->sendScans([
            ['ref' => 'a', 'method' => 'manual', 'student_id' => $student->id],
            ['ref' => 'b', 'method' => 'manual', 'student_id' => $stranger->id],
        ]);

        $this->assertSame(['recorded', 'rejected'], array_column($results, 'result'));
        $this->assertSame(AttendanceSource::ClassManual, Attendance::firstOrFail()->source);
    }

    public function test_offline_scan_keeps_its_time_but_not_before_the_session_opened(): void
    {
        $early = $this->studentInClass();
        $offline = $this->studentInClass();
        $this->openSession();
        $this->travelTo(now()->addMinutes(20));

        $this->sendScans([
            ['ref' => 'a', 'method' => 'scan', 'payload' => $early->qrPayload(), 'scanned_at' => now()->subHours(3)->toIso8601String()],
            ['ref' => 'b', 'method' => 'scan', 'payload' => $offline->qrPayload(), 'scanned_at' => now()->subMinutes(15)->toIso8601String()],
        ]);

        $this->assertSame('07:00', Attendance::where('student_id', $early->id)->first()->recorded_at->format('H:i'));
        $this->assertSame('07:05', Attendance::where('student_id', $offline->id)->first()->recorded_at->format('H:i'));
    }

    public function test_student_reported_sick_who_shows_up_becomes_present(): void
    {
        $student = $this->studentInClass();
        Attendance::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $this->classroom->id,
            'date' => '2026-09-28',
            'status' => AttendanceStatus::Sick,
            'source' => AttendanceSource::Staff,
        ]);
        $this->openSession();

        $this->sendScans([['ref' => 'a', 'method' => 'scan', 'payload' => $student->qrPayload()]]);

        $attendance = Attendance::firstOrFail();
        $this->assertSame(AttendanceStatus::Present, $attendance->status);
        $this->assertSame('Sebelumnya Sakit, ternyata hadir di kelas.', $attendance->note);
    }

    public function test_finishing_marks_the_rest_absent_and_locks_the_session(): void
    {
        $present = $this->studentInClass();
        $missing = $this->studentInClass();
        $sick = $this->studentInClass();
        $this->studentInClass(attributes: ['status' => StudentStatus::Transferred]);
        Attendance::factory()->create([
            'student_id' => $sick->id,
            'classroom_id' => $this->classroom->id,
            'date' => '2026-09-28',
            'status' => AttendanceStatus::Sick,
            'source' => AttendanceSource::Staff,
        ]);
        $session = $this->openSession();
        $this->sendScans([['ref' => 'a', 'method' => 'scan', 'payload' => $present->qrPayload()]]);

        $this->post('/absen/kelas/selesai')
            ->assertRedirect(route('class.scanner'))
            ->assertSessionHas('success', 'Absensi selesai. 1 siswa yang belum absen ditandai Alpa.');

        $this->assertTrue($session->fresh()->isClosed());
        $this->assertSame(AttendanceStatus::Absent, Attendance::where('student_id', $missing->id)->first()->status);
        $this->assertSame(AttendanceStatus::Sick, Attendance::where('student_id', $sick->id)->first()->status);
        $this->assertDatabaseCount('attendances', 3);

        $this->get('/absen/kelas')->assertInertia(fn (Assert $page) => $page->component('Scan/Summary'));

        $late = $this->sendScans([['ref' => 'b', 'method' => 'manual', 'student_id' => $missing->id]]);
        $this->assertSame('rejected', $late[0]['result']);
    }

    public function test_reopening_a_closed_class_shows_the_summary(): void
    {
        $session = $this->openSession();
        $this->post('/absen/kelas/selesai');
        $this->post('/absen/keluar')->assertRedirect(route('class.login'));

        $this->post('/absen', ['code' => $this->classroom->access_code])->assertRedirect(route('class.scanner'));

        $this->get('/absen/kelas')->assertInertia(fn (Assert $page) => $page->component('Scan/Summary'));
        $this->assertDatabaseCount('attendance_sessions', 1);
        $this->assertTrue($session->fresh()->isClosed());
    }
}
