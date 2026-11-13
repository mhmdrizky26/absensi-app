<?php

namespace Tests\Feature\Admin;

use App\Actions\RecordGateScans;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GateStation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A class without its own room (e.g. VIII-K): its students study in other
 * classes but their attendance, recap and report stay with VIII-K.
 */
class RoomlessClassTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $academicYear;

    private Classroom $host;

    private Classroom $otherHost;

    private Classroom $roomless;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(7, 0)); // Senin
        config(['attendance.school_days' => [1, 2, 3, 4, 5], 'attendance.enforce_window' => false]);

        $this->academicYear = AcademicYear::factory()->active()->create();
        $this->host = Classroom::factory()->for($this->academicYear)->create(['grade' => 8, 'name' => 'VIII-A']);
        $this->otherHost = Classroom::factory()->for($this->academicYear)->create(['grade' => 8, 'name' => 'VIII-B']);
        $this->roomless = Classroom::factory()->for($this->academicYear)->create(['grade' => 8, 'name' => 'VIII-K', 'has_room' => false, 'homeroom_teacher_id' => User::factory()->waliKelas()]);
        $this->admin = User::factory()->admin()->create();
    }

    private function studentIn(Classroom $classroom, string $name): Student
    {
        $student = Student::factory()->create(['name' => $name]);
        $student->placeIn($classroom, $this->academicYear);

        return $student;
    }

    private function studyRoomOf(Student $student): ?int
    {
        $id = DB::table('classroom_student')->where('student_id', $student->id)->where('academic_year_id', $this->academicYear->id)->value('study_classroom_id');

        return $id === null ? null : (int) $id;
    }

    public function test_lodging_spreads_students_evenly_over_rooms_of_the_same_grade(): void
    {
        $this->studentIn($this->host, 'Ayu');
        $this->studentIn($this->host, 'Budi');
        $guests = collect(['Citra', 'Dodi', 'Eka'])->map(fn (string $name): Student => $this->studentIn($this->roomless, $name));

        $this->actingAs($this->admin)->post("/kelas/{$this->roomless->id}/titipkan")->assertSessionHas('success');

        $rooms = DB::table('classroom_student')->whereIn('student_id', $guests->pluck('id'))->pluck('study_classroom_id')->countBy();
        $this->assertSame([$this->otherHost->id => 2, $this->host->id => 1], $rooms->sortDesc()->all());

        $this->actingAs($this->admin)->post("/kelas/{$this->host->id}/titipkan")->assertSessionHas('error');
    }

    public function test_admin_sets_where_each_student_studies(): void
    {
        $citra = $this->studentIn($this->roomless, 'Citra');
        $ayu = $this->studentIn($this->host, 'Ayu');

        $this->actingAs($this->admin)->put("/siswa/{$citra->id}/ruang-belajar", ['study_classroom_id' => $this->otherHost->id])
            ->assertSessionHas('success', 'Citra (VIII-K) sekarang belajar di VIII-B.');
        $this->assertSame($this->otherHost->id, $this->studyRoomOf($citra));

        $this->actingAs($this->admin)->put("/siswa/{$citra->id}/ruang-belajar", ['study_classroom_id' => $this->roomless->id])->assertSessionHasErrors('study_classroom_id');
        $this->actingAs($this->admin)->put("/siswa/{$ayu->id}/ruang-belajar", ['study_classroom_id' => $this->otherHost->id])->assertSessionHas('error');

        $this->actingAs($this->admin)->put("/siswa/{$citra->id}/ruang-belajar", ['study_classroom_id' => null])->assertSessionHas('success');
        $this->assertNull($this->studyRoomOf($citra));

        $this->actingAs(User::factory()->guruPiket()->create())->put("/siswa/{$citra->id}/ruang-belajar", ['study_classroom_id' => $this->host->id])->assertForbidden();
    }

    public function test_a_gate_scan_counts_for_their_own_class_and_shows_the_study_room(): void
    {
        $guest = $this->studentIn($this->roomless, 'Citra');
        DB::table('classroom_student')->where('student_id', $guest->id)->update(['study_classroom_id' => $this->host->id]);
        app(RecordGateScans::class)->handle(GateStation::factory()->create(), [['ref' => '1', 'payload' => $guest->qrPayload()]]);

        $this->actingAs($this->roomless->homeroomTeacher)->get('/rekap?bulan=2026-10')->assertInertia(fn (Assert $page) => $page
            ->where('classroom.name', 'VIII-K')
            ->where('grid.students.0.name', 'Citra')
            ->where('grid.students.0.studyRoom', 'VIII-A')
            ->where('grid.students.0.marks.2026-10-05.status', 'H')
        );

        $this->actingAs($this->admin)->get("/rekap?classroom={$this->host->id}&bulan=2026-10")
            ->assertInertia(fn (Assert $page) => $page->has('grid.students', 0));

        $this->actingAs(User::factory()->guruPiket()->create())->get('/pantauan')->assertInertia(fn (Assert $page) => $page
            ->where('classrooms.2.name', 'VIII-K')
            ->where('classrooms.2.counts.H', 1)
        );
    }

    public function test_admin_brings_lodged_students_back_to_their_own_class(): void
    {
        $guests = collect(['Ayu', 'Budi'])->map(fn (string $name): Student => $this->studentIn($this->roomless, $name));
        DB::table('classroom_student')->whereIn('student_id', $guests->pluck('id'))->update(['study_classroom_id' => $this->host->id]);
        $hostStudent = $this->studentIn($this->host, 'Citra');

        $this->actingAs(User::factory()->guruPiket()->create())->delete("/kelas/{$this->roomless->id}/titipkan")->assertForbidden();

        $this->actingAs($this->admin)->delete("/kelas/{$this->roomless->id}/titipkan")
            ->assertSessionHas('success', 'Kelas VIII-K kini punya ruang sendiri. 2 siswa titipan kembali belajar di VIII-K.');

        $this->assertTrue($this->roomless->fresh()->has_room);
        $guests->each(fn (Student $guest) => $this->assertNull($this->studyRoomOf($guest)));
        $this->assertNull($this->studyRoomOf($hostStudent));

        $this->actingAs($this->admin)->delete("/kelas/{$this->roomless->id}/titipkan")->assertSessionHas('error');
    }

    public function test_a_class_that_gets_its_room_back_takes_its_students_back(): void
    {
        $guest = $this->studentIn($this->roomless, 'Citra');
        DB::table('classroom_student')->where('student_id', $guest->id)->update(['study_classroom_id' => $this->host->id]);

        $this->actingAs($this->admin)->put("/kelas/{$this->roomless->id}", [
            'grade' => 8, 'section' => 'K', 'homeroom_teacher_id' => $this->roomless->homeroom_teacher_id, 'has_room' => true,
        ])->assertSessionHasNoErrors();

        $this->assertNull($this->studyRoomOf($guest));
        $this->assertTrue($this->roomless->fresh()->has_room);
    }
}
