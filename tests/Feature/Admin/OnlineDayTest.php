<?php

namespace Tests\Feature\Admin;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceDay;
use App\Models\Classroom;
use App\Models\Holiday;
use App\Models\OnlineDay;
use App\Models\Student;
use App\Models\User;
use App\Support\AttendanceWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlineDayTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AcademicYear $academicYear;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        // Senin, 28 September 2026 pukul 07:00.
        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(7, 0));
        config(['attendance.enforce_window' => true, 'attendance.opens_at' => '06:30', 'attendance.closes_at' => '08:00', 'attendance.school_days' => [1, 2, 3, 4, 5]]);

        $this->admin = User::factory()->admin()->create();
        $this->academicYear = AcademicYear::factory()->active()->create();
        $this->classroom = Classroom::factory()->for($this->academicYear)->create();
    }

    private function student(): Student
    {
        $student = Student::factory()->create();
        $student->placeIn($this->classroom, $this->academicYear);

        return $student;
    }

    public function test_today_as_online_day_marks_everyone_hadir_but_keeps_existing_marks_and_shuts_the_gate(): void
    {
        $sick = $this->student();
        $others = collect([$this->student(), $this->student()]);
        Attendance::factory()->create(['student_id' => $sick->id, 'classroom_id' => $this->classroom->id, 'date' => '2026-09-28', 'status' => AttendanceStatus::Sick, 'source' => AttendanceSource::Staff]);

        $this->actingAs($this->admin)
            ->post('/daring', ['from' => '2026-09-28', 'description' => 'Rapat guru'])
            ->assertSessionHas('success', '1 hari daring ditambahkan. 2 tanda Hadir otomatis dicatat.');

        foreach ($others as $student) {
            $attendance = Attendance::query()->where('student_id', $student->id)->sole();
            $this->assertSame(AttendanceStatus::Present, $attendance->status);
            $this->assertSame(AttendanceSource::Online, $attendance->source);
        }

        $this->assertSame(AttendanceStatus::Sick, Attendance::query()->where('student_id', $sick->id)->sole()->status);
        $this->assertNotNull(AttendanceDay::forDate(today()));
        $window = app(AttendanceWindow::class);
        $this->assertTrue($window->isSchoolDay(today()));
        $this->assertSame('Hari ini sekolah daring (Rapat guru), semua siswa otomatis Hadir, jadi tab gerbang tidak dibuka.', $window->gateClosedReason(now()));
    }

    public function test_a_range_skips_weekends_and_holidays_and_future_days_are_marked_by_the_scheduler(): void
    {
        $student = $this->student();
        Holiday::factory()->create(['date' => '2026-10-01']);

        $this->actingAs($this->admin)
            ->post('/daring', ['from' => '2026-09-29', 'to' => '2026-10-05', 'description' => 'Belajar dari rumah'])
            ->assertSessionHas('success', '4 hari daring ditambahkan. Siswa otomatis dicatat Hadir pada harinya.');

        $this->assertSame(['2026-09-29', '2026-09-30', '2026-10-02', '2026-10-05'], OnlineDay::query()->orderBy('date')->get()->map(fn (OnlineDay $day): string => $day->date->toDateString())->all());
        $this->assertSame(0, Attendance::query()->count());

        $this->travelTo(now()->setDate(2026, 9, 29)->setTime(6, 0));
        $this->artisan('attendance:finalize-days')->assertSuccessful();

        $attendance = Attendance::query()->where('student_id', $student->id)->sole();
        $this->assertSame('2026-09-29', $attendance->date->toDateString());
        $this->assertSame(AttendanceStatus::Present, $attendance->status);
        $this->assertNotNull(AttendanceDay::forDate(today()));
    }

    public function test_a_past_day_already_finished_turns_its_automatic_alpa_into_hadir(): void
    {
        $student = $this->student();
        Attendance::factory()->create(['student_id' => $student->id, 'classroom_id' => $this->classroom->id, 'date' => '2026-09-25', 'status' => AttendanceStatus::Absent, 'source' => AttendanceSource::Automatic]);
        AttendanceDay::create(['date' => '2026-09-25', 'finalized_at' => now(), 'absent_count' => 1]);

        $this->actingAs($this->admin)->post('/daring', ['from' => '2026-09-25', 'description' => 'Daring'])->assertSessionHas('success');

        $this->assertSame(AttendanceStatus::Present, Attendance::query()->where('student_id', $student->id)->sole()->status);
        $this->assertSame(0, AttendanceDay::forDate(now()->setDate(2026, 9, 25))->absent_count);
    }

    public function test_cancelling_today_removes_the_automatic_hadir_and_reopens_the_gate(): void
    {
        $this->student();
        $this->actingAs($this->admin)->post('/daring', ['from' => '2026-09-28', 'description' => 'Rapat guru']);

        $this->actingAs($this->admin)->delete('/daring/'.OnlineDay::sole()->id)->assertSessionHas('success');

        $this->assertSame(0, Attendance::query()->count());
        $this->assertNull(AttendanceDay::forDate(today()));
        $this->assertNull(app(AttendanceWindow::class)->gateClosedReason(now()));
    }

    public function test_non_school_days_only_are_refused_and_only_admins_may_add(): void
    {
        $this->actingAs($this->admin)
            ->post('/daring', ['from' => '2026-10-03', 'to' => '2026-10-04', 'description' => 'Daring'])
            ->assertSessionHasErrors('from');

        $this->actingAs(User::factory()->guruPiket()->create())
            ->post('/daring', ['from' => '2026-09-29', 'description' => 'Daring'])
            ->assertForbidden();
    }
}
