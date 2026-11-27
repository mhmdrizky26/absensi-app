<?php

namespace Tests\Feature\Duty;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\GreetingSchedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GreetingScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $greeter;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        // Senin, 28 September 2026 pukul 07:00; gerbang buka 06:30, jam masuk 08:00.
        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(7, 0));
        config(['attendance.enforce_window' => true, 'attendance.opens_at' => '06:30', 'attendance.closes_at' => '08:00', 'attendance.school_days' => [1, 2, 3, 4, 5]]);

        $this->greeter = User::factory()->guru()->create(['name' => 'Bu Ratna']);
        GreetingSchedule::factory()->create(['user_id' => $this->greeter->id, 'weekday' => 1]);

        $academicYear = AcademicYear::factory()->active()->create();
        $classroom = Classroom::factory()->for($academicYear)->create(['name' => 'VII-A']);
        $this->student = Student::factory()->create();
        $this->student->placeIn($classroom, $academicYear);
    }

    public function test_admin_saves_the_weekly_greeting_schedule(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->guru()->create();

        $this->actingAs($admin)
            ->put('/jadwal-sapa-pagi', ['days' => [2 => [$other->id], 3 => [$this->greeter->id, $other->id]]])
            ->assertSessionHas('success', 'Jadwal sapa pagi disimpan.');

        $this->assertEqualsCanonicalizing(
            [[$other->id, 2], [$this->greeter->id, 3], [$other->id, 3]],
            GreetingSchedule::query()->get()->map(fn (GreetingSchedule $row): array => [$row->user_id, $row->weekday])->all(),
        );

        $this->actingAs($admin)->get('/jadwal-sapa-pagi')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/GreetingSchedule')
            ->where('weekdays.2.userIds', [$this->greeter->id, $other->id])
        );
        $this->actingAs($other)->get('/jadwal-sapa-pagi')->assertForbidden();
    }

    public function test_greeter_records_a_forgotten_card_until_jam_masuk_on_their_day(): void
    {
        $this->actingAs($this->greeter)->get('/terlambat')->assertInertia(fn (Assert $page) => $page
            ->where('scanUntil', now()->setTime(8, 0)->toIso8601String())
            ->where('auth.user.isOnGreetingDuty', true)
        );

        $this->actingAs($this->greeter)->postJson('/terlambat', ['student_id' => $this->student->id])
            ->assertJson(['result' => 'recorded', 'student' => ['status' => 'H']]);
        $this->assertTrue(Attendance::sole()->recorder->is($this->greeter));

        $this->actingAs($this->greeter)->get('/cari-siswa')->assertOk();
    }

    public function test_after_jam_masuk_the_greeter_can_no_longer_record(): void
    {
        $this->travelTo(now()->setTime(8, 5));

        $this->actingAs($this->greeter)->postJson('/terlambat', ['student_id' => $this->student->id])
            ->assertJson(['result' => 'rejected', 'message' => 'Scan sapa pagi ditutup pukul 08:00. Siswa yang datang terlambat dicatat guru piket.']);

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_greeter_gets_no_other_duty_pages_and_nothing_on_other_days(): void
    {
        $this->actingAs($this->greeter)->get('/pantauan')->assertForbidden();
        $this->actingAs($this->greeter)->post('/pantauan/selesaikan')->assertForbidden();

        $this->travelTo(now()->addDay()); // Selasa

        $this->actingAs($this->greeter)->get('/terlambat')->assertForbidden();
        $this->actingAs($this->greeter)->postJson('/terlambat', ['student_id' => $this->student->id])->assertForbidden();
    }

    public function test_guru_bk_can_be_scheduled_for_sapa_pagi_and_scan(): void
    {
        $admin = User::factory()->admin()->create();
        $counselor = User::factory()->create(['role' => Role::GuruBk]);

        $this->actingAs($admin)->put('/jadwal-sapa-pagi', ['days' => [1 => [$counselor->id]]])->assertSessionHasNoErrors();
        $this->actingAs($admin)->put('/jadwal-piket', ['days' => [1 => [$counselor->id]]])->assertSessionHasErrors('days.1.0');

        $this->actingAs($counselor)->postJson('/terlambat', ['student_id' => $this->student->id])
            ->assertJson(['result' => 'recorded']);
    }

    public function test_dashboard_sends_the_sapa_pagi_schedule_beside_the_piket_schedule(): void
    {
        $both = User::factory()->guruPiket()->create(['name' => 'Pak Andi']);
        GreetingSchedule::factory()->create(['user_id' => $both->id, 'weekday' => 3]);

        $this->actingAs($both)->get('/dasbor')->assertInertia(fn (Assert $page) => $page
            ->where('duty.greetingDays', ['Rabu'])
        );
        $this->actingAs($this->greeter)->get('/dasbor')->assertInertia(fn (Assert $page) => $page
            ->where('duty.days', [])
            ->where('duty.greetingDays', ['Senin'])
        );
    }

    public function test_greeter_searches_every_class_on_scan_absen_but_not_on_staff_forms(): void
    {
        $this->actingAs($this->greeter)->getJson('/cari-siswa?unrecorded=1')
            ->assertJsonPath('students.*.id', [$this->student->id]);
        $this->actingAs($this->greeter)->getJson('/cari-siswa')->assertJsonCount(0, 'students');

        $homeroomGreeter = User::factory()->waliKelas()->create();
        $academicYear = AcademicYear::active();
        $ownStudent = Student::factory()->create();
        $ownStudent->placeIn(Classroom::factory()->for($academicYear)->create(['homeroom_teacher_id' => $homeroomGreeter->id]), $academicYear);
        GreetingSchedule::factory()->create(['user_id' => $homeroomGreeter->id, 'weekday' => 1]);

        $this->actingAs($homeroomGreeter)->getJson('/cari-siswa?unrecorded=1')->assertJsonCount(2, 'students');
        $this->actingAs($homeroomGreeter)->getJson('/cari-siswa')->assertJsonPath('students.*.id', [$ownStudent->id]);

        $this->travelTo(now()->addDay()); // Selasa

        $this->actingAs($homeroomGreeter)->getJson('/cari-siswa?unrecorded=1')->assertJsonPath('students.*.id', [$ownStudent->id]);
    }

    public function test_greeter_who_is_also_guru_piket_today_keeps_recording_late_students(): void
    {
        $both = User::factory()->guruPiket()->create();
        GreetingSchedule::factory()->create(['user_id' => $both->id, 'weekday' => 1]);
        $this->travelTo(now()->setTime(8, 10));

        $this->actingAs($both)->get('/terlambat')->assertInertia(fn (Assert $page) => $page->where('scanUntil', null));
        $this->actingAs($both)->postJson('/terlambat', ['student_id' => $this->student->id])
            ->assertJson(['result' => 'recorded', 'student' => ['status' => 'T']]);
    }
}
