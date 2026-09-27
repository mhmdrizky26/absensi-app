<?php

namespace Tests\Feature\Recap;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Exports\MonthlyRecapExport;
use App\Exports\SchoolRecapExport;
use App\Exports\SemesterRecapExport;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Holiday;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class RecapTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $academicYear;

    private User $wali;

    private Classroom $classroom;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(10, 0)); // Senin
        config(['attendance.school_days' => [1, 2, 3, 4, 5]]);

        $this->academicYear = AcademicYear::factory()->active()->create([
            'odd_semester_starts_on' => '2026-07-13',
            'even_semester_starts_on' => '2027-01-04',
            'ends_on' => '2027-06-26',
        ]);
        $this->wali = User::factory()->waliKelas()->create();
        $this->classroom = Classroom::factory()->for($this->academicYear)->create(['name' => 'VII-A', 'homeroom_teacher_id' => $this->wali->id]);
        $this->student = Student::factory()->create(['name' => 'Ayu']);
        $this->student->placeIn($this->classroom, $this->academicYear);
    }

    private function mark(string $date, AttendanceStatus $status, ?Student $student = null): Attendance
    {
        return Attendance::factory()->create([
            'student_id' => ($student ?? $this->student)->id,
            'classroom_id' => $this->classroom->id,
            'date' => $date,
            'status' => $status,
            'source' => AttendanceSource::ClassScan,
        ]);
    }

    public function test_guru_piket_has_no_recap(): void
    {
        $this->actingAs(User::factory()->guruPiket()->create())->get('/rekap')->assertForbidden();
    }

    public function test_wali_kelas_sees_only_their_class(): void
    {
        $other = Classroom::factory()->for($this->academicYear)->create();

        $this->actingAs($this->wali)->get('/rekap')->assertInertia(fn (Assert $page) => $page
            ->where('classroom.name', 'VII-A')
            ->has('classrooms', 1)
        );
        $this->actingAs($this->wali)->get("/rekap?classroom={$other->id}")->assertForbidden();
        $this->actingAs($this->wali)->get("/rekap/ekspor/bulanan?classroom={$other->id}")->assertForbidden();
    }

    public function test_month_grid_has_school_days_marks_and_rates(): void
    {
        Holiday::factory()->create(['date' => '2026-09-15']);
        $this->mark('2026-09-01', AttendanceStatus::Present);
        $this->mark('2026-09-02', AttendanceStatus::Late);
        $this->mark('2026-09-03', AttendanceStatus::Absent);
        $this->mark('2026-09-04', AttendanceStatus::Sick);

        $this->actingAs($this->wali)->get('/rekap?bulan=2026-09')->assertInertia(fn (Assert $page) => $page
            ->component('Recap/Monthly')
            ->where('month', '2026-09')
            ->has('grid.days', 21) // 22 hari kerja September 2026, dikurangi 1 hari libur
            ->where('grid.days.0', '2026-09-01')
            ->where('grid.students.0.marks.2026-09-03.status', 'A')
            ->where('grid.students.0.counts', ['H' => 1, 'T' => 1, 'D' => 0, 'S' => 1, 'I' => 0, 'A' => 1])
            ->where('grid.students.0.rate', 50)
            ->where('grid.dailyRates.2026-09-01', 100)
        );
    }

    public function test_wali_corrects_alpa_to_sakit_and_the_change_is_logged(): void
    {
        $attendance = $this->mark('2026-09-21', AttendanceStatus::Absent);

        $this->actingAs($this->wali)->post('/rekap/koreksi', [
            'student_id' => $this->student->id,
            'date' => '2026-09-21',
            'status' => 'S',
            'reason' => 'Ada surat dokter',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $attendance->refresh();
        $this->assertSame(AttendanceStatus::Sick, $attendance->status);
        $this->assertSame(AttendanceSource::Staff, $attendance->source);
        $log = $attendance->logs()->sole();
        $this->assertSame(AttendanceStatus::Absent, $log->from_status);
        $this->assertTrue($log->changer->is($this->wali));
        $this->assertSame('Ada surat dokter', $log->reason);

        $this->actingAs($this->wali)
            ->getJson("/rekap/riwayat?student_id={$this->student->id}&date=2026-09-21")
            ->assertOk()
            ->assertJsonPath('mark.statusLabel', 'Sakit')
            ->assertJsonPath('logs.0.from', 'Alpa')
            ->assertJsonPath('logs.0.to', 'Sakit');
    }

    public function test_an_empty_day_can_be_filled_in(): void
    {
        $this->actingAs($this->wali)->post('/rekap/koreksi', [
            'student_id' => $this->student->id,
            'date' => '2026-09-22',
            'status' => 'H',
            'reason' => 'Kelas lupa di-scan, siswa hadir',
        ])->assertSessionHasNoErrors();

        $this->assertSame(AttendanceStatus::Present, Attendance::sole()->status);
    }

    public function test_corrections_need_a_reason_a_past_date_and_access_to_the_class(): void
    {
        $stranger = Student::factory()->create();
        $stranger->placeIn(Classroom::factory()->for($this->academicYear)->create(), $this->academicYear);

        $this->actingAs($this->wali)->post('/rekap/koreksi', ['student_id' => $this->student->id, 'date' => '2026-09-21', 'status' => 'S'])
            ->assertSessionHasErrors('reason');
        $this->actingAs($this->wali)->post('/rekap/koreksi', ['student_id' => $this->student->id, 'date' => '2026-09-29', 'status' => 'S', 'reason' => 'x'])
            ->assertSessionHasErrors('date');
        $this->actingAs($this->wali)->post('/rekap/koreksi', ['student_id' => $stranger->id, 'date' => '2026-09-21', 'status' => 'S', 'reason' => 'x'])
            ->assertSessionHasErrors('student_id');
        $this->actingAs($this->wali)->getJson("/rekap/riwayat?student_id={$stranger->id}&date=2026-09-21")->assertForbidden();

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_semester_recap_counts_only_that_semester(): void
    {
        $this->mark('2026-09-21', AttendanceStatus::Sick);
        $this->mark('2026-09-22', AttendanceStatus::Absent);
        $this->mark('2027-02-01', AttendanceStatus::Excused);

        $this->actingAs($this->wali)->get('/rekap/semester?semester=ganjil')->assertInertia(fn (Assert $page) => $page
            ->component('Recap/Semester')
            ->where('range', ['from' => '2026-07-13', 'to' => '2027-01-03'])
            ->where('rows.0.counts', ['H' => 0, 'T' => 0, 'D' => 0, 'S' => 1, 'I' => 0, 'A' => 1])
            ->where('totals.S', 1)
        );

        $this->actingAs($this->wali)->get('/rekap/semester?semester=genap')->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.counts.I', 1)
            ->where('rows.0.counts.S', 0)
        );
    }

    public function test_school_recap_is_for_admins_and_totals_every_class(): void
    {
        $this->mark('2026-09-21', AttendanceStatus::Present);
        $this->mark('2026-09-22', AttendanceStatus::Absent);

        $this->actingAs($this->wali)->get('/rekap/sekolah')->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())->get('/rekap/sekolah?jenis=bulan&bulan=2026-09')->assertInertia(fn (Assert $page) => $page
            ->component('Recap/School')
            ->where('rows.0.name', 'VII-A')
            ->where('rows.0.students', 1)
            ->where('totals.H', 1)
            ->where('totals.A', 1)
            ->where('rate', 50)
        );
    }

    public function test_recaps_download_as_excel(): void
    {
        Excel::fake();
        $this->mark('2026-09-21', AttendanceStatus::Sick);
        $admin = User::factory()->admin()->create();

        $this->actingAs($this->wali)->get('/rekap/ekspor/bulanan?bulan=2026-09');
        Excel::assertDownloaded('rekap-VII-A-2026-09.xlsx', fn (MonthlyRecapExport $export): bool => $export->array()[6][2] === 'Ayu');

        $this->actingAs($this->wali)->get('/rekap/ekspor/semester?semester=ganjil');
        Excel::assertDownloaded('rapor-kehadiran-VII-A-ganjil.xlsx', fn (SemesterRecapExport $export): bool => $export->array()[5] === [1, $this->student->nis, $this->student->nisn, 'Ayu', 1, 0, 0, 0, 0, 0, '0%']);

        $this->actingAs($admin)->get('/rekap/ekspor/sekolah?jenis=bulan&bulan=2026-09');
        Excel::assertDownloaded('rekap-sekolah-september-2026.xlsx', fn (SchoolRecapExport $export): bool => collect($export->array())->last()[0] === 'Total');
    }
}
