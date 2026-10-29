<?php

namespace Tests\Feature\ClassScan;

use App\Actions\CloseClassSession;
use App\Actions\RecordClassScans;
use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoomlessClassTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $academicYear;

    private Classroom $host;

    private Classroom $otherHost;

    private Classroom $roomless;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(7, 0)); // Senin
        config(['attendance.school_days' => [1, 2, 3, 4, 5], 'attendance.enforce_window' => false]);

        $this->academicYear = AcademicYear::factory()->active()->create();
        $this->host = Classroom::factory()->for($this->academicYear)->create(['grade' => 8, 'name' => 'VIII-A']);
        $this->otherHost = Classroom::factory()->for($this->academicYear)->create(['grade' => 8, 'name' => 'VIII-B']);
        $this->roomless = Classroom::factory()->for($this->academicYear)->create(['grade' => 8, 'name' => 'VIII-K', 'has_room' => false, 'homeroom_teacher_id' => User::factory()->waliKelas()]);
    }

    private function studentIn(Classroom $classroom, string $name): Student
    {
        $student = Student::factory()->create(['name' => $name]);
        $student->placeIn($classroom, $this->academicYear);

        return $student;
    }

    private function lodge(Student $student, Classroom $room): void
    {
        DB::table('classroom_student')->where('student_id', $student->id)->update(['study_classroom_id' => $room->id]);
    }

    public function test_lodging_spreads_students_evenly_over_rooms_of_the_same_grade(): void
    {
        $this->studentIn($this->host, 'Ayu');
        $this->studentIn($this->host, 'Budi');
        $guests = collect(['Citra', 'Dodi', 'Eka'])->map(fn (string $name): Student => $this->studentIn($this->roomless, $name));

        $this->actingAs(User::factory()->admin()->create())
            ->post("/kelas/{$this->roomless->id}/titipkan")
            ->assertSessionHas('success');

        $rooms = DB::table('classroom_student')->whereIn('student_id', $guests->pluck('id'))->pluck('study_classroom_id')->countBy();
        $this->assertSame([$this->otherHost->id => 2, $this->host->id => 1], $rooms->sortDesc()->all());

        $this->actingAs(User::factory()->admin()->create())->post("/kelas/{$this->host->id}/titipkan")->assertSessionHas('error');
    }

    public function test_a_lodged_student_is_scanned_in_the_host_room_but_counted_for_their_own_class(): void
    {
        $guest = $this->studentIn($this->roomless, 'Citra');
        $absentGuest = $this->studentIn($this->roomless, 'Dodi');
        $this->lodge($guest, $this->host);
        $this->lodge($absentGuest, $this->host);
        $hostStudent = $this->studentIn($this->host, 'Ayu');
        $stranger = $this->studentIn($this->otherHost, 'Eka');
        $session = AttendanceSession::factory()->create(['classroom_id' => $this->host->id, 'date' => today()]);

        $results = app(RecordClassScans::class)->handle($session, [
            ['ref' => '1', 'method' => 'scan', 'payload' => $guest->qrPayload()],
            ['ref' => '2', 'method' => 'scan', 'payload' => $hostStudent->qrPayload()],
            ['ref' => '3', 'method' => 'scan', 'payload' => $stranger->qrPayload()],
        ]);

        $this->assertSame(['recorded', 'recorded', 'rejected'], array_column($results, 'result'));
        $this->assertSame($this->roomless->id, Attendance::where('student_id', $guest->id)->value('classroom_id'));
        $this->assertSame($this->host->id, Attendance::where('student_id', $hostStudent->id)->value('classroom_id'));

        app(CloseClassSession::class)->handle($session);

        $alpa = Attendance::where('student_id', $absentGuest->id)->sole();
        $this->assertSame([AttendanceStatus::Absent, $this->roomless->id], [$alpa->status, $alpa->classroom_id]);
    }

    public function test_the_host_scanner_lists_lodged_students_and_the_roomless_code_is_refused(): void
    {
        $guest = $this->studentIn($this->roomless, 'Citra');
        $this->lodge($guest, $this->host);
        $this->studentIn($this->host, 'Ayu');

        $this->post('/absen', ['code' => $this->roomless->access_code])
            ->assertSessionHasErrors(['code' => 'Kelas VIII-K tidak punya ruang sendiri. Siswanya diabsen di kelas tempat mereka dititipkan.']);

        $this->post('/absen', ['code' => $this->host->access_code])->assertRedirect(route('class.scanner'));
        $this->get('/absen/kelas')->assertInertia(fn (Assert $page) => $page
            ->has('roster', 2)
            ->where('roster.1.name', 'Citra')
            ->where('roster.1.guestOf', 'VIII-K')
            ->where('roster.0.guestOf', null)
        );
    }

    public function test_wali_kelas_of_the_roomless_class_tracks_their_students_and_the_host_does_not(): void
    {
        $guest = $this->studentIn($this->roomless, 'Citra');
        $this->lodge($guest, $this->host);
        $session = AttendanceSession::factory()->create(['classroom_id' => $this->host->id, 'date' => today()]);
        app(RecordClassScans::class)->handle($session, [['ref' => '1', 'method' => 'scan', 'payload' => $guest->qrPayload()]]);

        $this->actingAs($this->roomless->homeroomTeacher)->get('/rekap?bulan=2026-10')->assertInertia(fn (Assert $page) => $page
            ->where('classroom.name', 'VIII-K')
            ->where('grid.students.0.name', 'Citra')
            ->where('grid.students.0.studyRoom', 'VIII-A')
            ->where('grid.students.0.marks.2026-10-05.status', 'H')
        );

        $this->actingAs(User::factory()->admin()->create())->get("/rekap?classroom={$this->host->id}&bulan=2026-10")
            ->assertInertia(fn (Assert $page) => $page->has('grid.students', 0));

        $this->actingAs(User::factory()->guruPiket()->create())->get('/pantauan')->assertInertia(fn (Assert $page) => $page
            ->where('classrooms.2.name', 'VIII-K')
            ->where('classrooms.2.session', ['state' => 'no_room', 'hosts' => 1, 'closedHosts' => 0, 'unplaced' => 0])
            ->where('classrooms.2.counts.H', 1)
        );
    }

    public function test_a_class_that_gets_its_room_back_takes_its_students_back(): void
    {
        $guest = $this->studentIn($this->roomless, 'Citra');
        $this->lodge($guest, $this->host);

        $this->actingAs(User::factory()->admin()->create())->put("/kelas/{$this->roomless->id}", [
            'grade' => 8, 'section' => 'K', 'homeroom_teacher_id' => $this->roomless->homeroom_teacher_id, 'has_room' => true,
        ])->assertSessionHasNoErrors();

        $this->assertNull(DB::table('classroom_student')->where('student_id', $guest->id)->value('study_classroom_id'));
        $this->assertTrue($this->roomless->fresh()->has_room);
    }
}
