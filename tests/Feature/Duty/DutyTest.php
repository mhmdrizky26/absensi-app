<?php

namespace Tests\Feature\Duty;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceDay;
use App\Models\Classroom;
use App\Models\GateStation;
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

        // Senin, 28 September 2026 pukul 07:45, jam masuk 08:00.
        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(7, 45));
        config(['attendance.enforce_window' => true, 'attendance.opens_at' => '06:30', 'attendance.closes_at' => '08:00', 'attendance.school_days' => [1, 2, 3, 4, 5]]);

        $this->piket = User::factory()->guruPiket()->create(['name' => 'Bu Sari']);
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
        $this->actingAs($wali)->post('/pantauan/selesaikan')->assertForbidden();
    }

    public function test_before_jam_masuk_piket_records_a_forgotten_card_as_hadir(): void
    {
        $student = $this->studentInClass(['name' => 'Ayu']);

        $this->actingAs($this->piket)->postJson('/terlambat', ['student_id' => $student->id])
            ->assertOk()
            ->assertJson(['result' => 'recorded', 'message' => 'Dicatat hadir pukul 07:45 (lupa kartu).', 'student' => ['name' => 'Ayu', 'classroom' => 'VII-A', 'status' => 'H']]);

        $attendance = Attendance::sole();
        $this->assertSame(AttendanceStatus::Present, $attendance->status);
        $this->assertSame(AttendanceSource::DutyScan, $attendance->source);
        $this->assertTrue($attendance->recorder->is($this->piket));
        $this->assertSame('Lupa membawa kartu, diabsen guru piket.', $attendance->note);
    }

    public function test_after_jam_masuk_piket_records_terlambat_and_logs_who_did_it(): void
    {
        $student = $this->studentInClass(['name' => 'Ayu']);
        $this->mark($student, AttendanceStatus::Excused);
        $this->travelTo(now()->setTime(8, 10));

        $this->actingAs($this->piket)->postJson('/terlambat', ['payload' => $student->qrPayload()])->assertJson(['result' => 'recorded', 'student' => ['status' => 'T']]);

        $attendance = Attendance::sole();
        $this->assertSame(AttendanceStatus::Late, $attendance->status);
        $this->assertSame('Datang pukul 08:10.', $attendance->note);

        $log = $attendance->logs()->sole();
        $this->assertSame(AttendanceStatus::Excused, $log->from_status);
        $this->assertSame(AttendanceStatus::Late, $log->to_status);
        $this->assertTrue($log->changer->is($this->piket));

        $this->actingAs($this->piket)->get('/terlambat')->assertInertia(fn (Assert $page) => $page
            ->component('Duty/LateScan')
            ->where('recorded.0.statusLabel', 'Terlambat')
            ->where('isFinalized', false)
        );
    }

    public function test_student_already_present_is_not_recorded_again(): void
    {
        $student = $this->studentInClass();
        $this->mark($student, AttendanceStatus::Present);

        $this->actingAs($this->piket)->postJson('/terlambat', ['payload' => $student->qrPayload()])->assertJson(['result' => 'duplicate']);

        $this->assertSame(AttendanceStatus::Present, Attendance::sole()->status);
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

    public function test_finishing_the_day_marks_students_without_a_mark_alpa(): void
    {
        $ayu = $this->studentInClass(['name' => 'Ayu']);
        $budi = $this->studentInClass(['name' => 'Budi']);
        $citra = $this->studentInClass(['name' => 'Citra']);
        $this->studentInClass(['name' => 'Dodi', 'status' => StudentStatus::Graduated]);
        $this->mark($ayu, AttendanceStatus::Present);
        $this->mark($budi, AttendanceStatus::Sick);

        $this->actingAs($this->piket)->post('/pantauan/selesaikan')->assertSessionHas('error');
        $this->assertDatabaseCount('attendance_days', 0);

        $this->travelTo(now()->setTime(8, 30));
        $this->actingAs($this->piket)->get('/pantauan')->assertInertia(fn (Assert $page) => $page->where('missingCount', 1)->where('finalized', null));

        $this->actingAs($this->piket)->post('/pantauan/selesaikan')
            ->assertSessionHas('success', 'Absen hari ini selesai. 1 siswa yang belum absen dicatat Alpa.');

        $this->assertSame(AttendanceStatus::Absent, Attendance::where('student_id', $citra->id)->sole()->status);
        $this->assertSame(AttendanceStatus::Sick, Attendance::where('student_id', $budi->id)->sole()->status);
        $this->assertSame(3, Attendance::count());

        $this->actingAs($this->piket)->get('/pantauan')->assertInertia(fn (Assert $page) => $page
            ->where('finalized.by', 'Bu Sari')
            ->where('finalized.absentCount', 1)
            ->where('missingCount', 0)
        );
        $this->actingAs($this->piket)->post('/pantauan/selesaikan')->assertSessionHas('error', 'Absen hari ini sudah diselesaikan.');
    }

    public function test_in_demo_mode_the_day_can_be_finished_at_any_time(): void
    {
        config(['attendance.enforce_window' => false]);
        $this->travelTo(now()->setDate(2026, 9, 27)->setTime(6, 0)); // Minggu, sebelum jam masuk
        $this->studentInClass();

        // Hari Minggu tidak ada jadwal piket; admin tetap bisa menjalankan tugas piket.
        $this->actingAs($this->piket)->post('/pantauan/selesaikan')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post('/pantauan/selesaikan')->assertSessionHas('success');

        $this->assertSame(1, AttendanceDay::forDate(now())->absent_count);
    }

    public function test_in_demo_mode_a_finished_day_can_be_opened_again_for_another_practice(): void
    {
        config(['attendance.enforce_window' => false]);
        $absent = $this->studentInClass();
        $present = $this->studentInClass();
        $this->mark($present, AttendanceStatus::Present);

        $this->actingAs($this->piket)->post('/pantauan/selesaikan')->assertSessionHas('success');
        $this->assertSame(AttendanceStatus::Absent, Attendance::where('student_id', $absent->id)->sole()->status);

        $this->actingAs($this->piket)->delete('/pantauan/selesaikan')->assertSessionHas('success');

        $this->assertNull(AttendanceDay::forDate(now()));
        $this->assertFalse(Attendance::where('student_id', $absent->id)->exists());
        $this->assertSame(AttendanceStatus::Present, Attendance::where('student_id', $present->id)->sole()->status);
        $this->actingAs($this->piket)->postJson('/terlambat', ['student_id' => $absent->id])->assertJson(['result' => 'recorded']);
    }

    public function test_a_finished_day_cannot_be_opened_again_outside_demo_mode(): void
    {
        $this->studentInClass();
        $this->travelTo(now()->setTime(8, 30));
        $this->actingAs($this->piket)->post('/pantauan/selesaikan');

        $this->actingAs($this->piket)->delete('/pantauan/selesaikan')->assertSessionHas('error');

        $this->assertNotNull(AttendanceDay::forDate(now()));
        $this->assertSame(1, Attendance::count());
    }

    public function test_after_finishing_piket_can_no_longer_record(): void
    {
        $student = $this->studentInClass();
        $this->travelTo(now()->setTime(9, 0));
        AttendanceDay::create(['date' => '2026-09-28', 'finalized_at' => now(), 'absent_count' => 1]);

        $this->actingAs($this->piket)->postJson('/terlambat', ['student_id' => $student->id])
            ->assertJson(['result' => 'rejected', 'message' => 'Absen hari ini sudah diselesaikan. Perubahan dilakukan wali kelas lewat koreksi rekap.']);
    }

    public function test_monitor_shows_counts_per_class_and_the_gate_tablets(): void
    {
        $otherClass = Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-B']);
        $this->mark($this->studentInClass(), AttendanceStatus::Present);
        $this->studentInClass();
        $this->studentInClass([], $otherClass);
        GateStation::factory()->create(['name' => 'Tab 1', 'code' => 'TAB1'])->markSeen(3);
        GateStation::factory()->revoked()->create(['code' => 'TAB2']);

        $this->actingAs($this->piket)->get('/pantauan')->assertInertia(fn (Assert $page) => $page
            ->component('Duty/Monitor')
            ->where('window.isSchoolDay', true)
            ->where('classrooms.0.name', 'VII-A')
            ->where('classrooms.0.total', 2)
            ->where('classrooms.0.counts.H', 1)
            ->where('classrooms.1.total', 1)
            ->where('missingCount', 2)
            ->has('stations', 1)
            ->where('stations.0', ['id' => GateStation::where('code', 'TAB1')->value('id'), 'name' => 'Tab 1', 'isOnline' => true, 'pendingScans' => 3])
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

    public function test_forgotten_days_are_finished_automatically_at_night(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 20)); // Minggu sebelumnya
        GateStation::factory()->create();
        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(7, 45));

        $present = $this->studentInClass();
        $missing = $this->studentInClass();
        $this->mark($present, AttendanceStatus::Present, '2026-09-25'); // Jumat: ada absen
        $this->mark($present, AttendanceStatus::Present, '2026-09-28');

        $this->artisan('attendance:finalize-days')->assertSuccessful();

        $this->assertNotNull(AttendanceDay::forDate(now()->setDate(2026, 9, 25)));
        $this->assertSame(AttendanceStatus::Absent, Attendance::where('student_id', $missing->id)->whereDate('date', '2026-09-25')->sole()->status);
        $this->assertNull(AttendanceDay::forDate(now()->setDate(2026, 9, 24)), 'Hari tanpa absen sama sekali dilewati.');
        $this->assertNull(AttendanceDay::forDate(now()), 'Hari ini baru diselesaikan malam hari.');

        $this->travelTo(now()->setTime(23, 0));
        $this->artisan('attendance:finalize-days')->assertSuccessful();

        $this->assertSame(1, AttendanceDay::forDate(now())->absent_count);
    }

    public function test_days_before_the_first_gate_tablet_are_never_finished(): void
    {
        $present = $this->studentInClass();
        $this->studentInClass();
        $this->mark($present, AttendanceStatus::Present, '2026-09-25');

        $this->artisan('attendance:finalize-days')->assertSuccessful();
        $this->assertDatabaseCount('attendance_days', 0);

        GateStation::factory()->create(); // dibuat hari ini, Senin 28 September
        $this->artisan('attendance:finalize-days')->assertSuccessful();
        $this->assertDatabaseCount('attendance_days', 0);
        $this->assertSame(1, Attendance::count());
    }
}
