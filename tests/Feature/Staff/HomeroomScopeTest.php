<?php

namespace Tests\Feature\Staff;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Dispensation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A wali kelas may only see and change their own students, on every page
 * where they handle izin, sakit or dispensasi.
 */
class HomeroomScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $wali;

    private Student $ownStudent;

    private Student $otherStudent;

    private Classroom $otherClassroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(9, 0));
        config(['attendance.school_days' => [1, 2, 3, 4, 5]]);
        Storage::fake('local');

        $academicYear = AcademicYear::factory()->active()->create();
        $this->wali = User::factory()->waliKelas()->create();
        $ownClassroom = Classroom::factory()->for($academicYear)->create(['name' => 'VII-A', 'homeroom_teacher_id' => $this->wali->id]);
        $this->otherClassroom = Classroom::factory()->for($academicYear)->create(['name' => 'VII-B', 'homeroom_teacher_id' => User::factory()->waliKelas()]);

        $this->ownStudent = Student::factory()->create(['name' => 'Ayu Milik Sendiri']);
        $this->ownStudent->placeIn($ownClassroom, $academicYear);
        $this->otherStudent = Student::factory()->create(['name' => 'Ayu Kelas Lain']);
        $this->otherStudent->placeIn($this->otherClassroom, $academicYear);
    }

    public function test_search_only_finds_their_own_students(): void
    {
        $this->actingAs($this->wali)->getJson('/cari-siswa?q=ayu')
            ->assertJsonCount(1, 'students')
            ->assertJsonPath('students.0.name', 'Ayu Milik Sendiri');
    }

    public function test_izin_sakit_only_for_their_own_students(): void
    {
        $payload = ['status' => 'S', 'from' => '2026-09-28', 'to' => '2026-09-28', 'note' => 'Demam'];

        $this->actingAs($this->wali)->post('/izin', ['student_id' => $this->otherStudent->id, ...$payload])->assertSessionHasErrors('student_id');
        $this->actingAs($this->wali)->post('/izin', ['student_id' => $this->ownStudent->id, ...$payload])->assertSessionHasNoErrors();

        $this->assertSame([$this->ownStudent->id], Attendance::pluck('student_id')->all());
    }

    public function test_izin_list_and_letters_of_other_classes_stay_hidden(): void
    {
        $otherMark = Attendance::factory()->create([
            'student_id' => $this->otherStudent->id,
            'classroom_id' => $this->otherClassroom->id,
            'date' => '2026-09-28',
            'status' => AttendanceStatus::Sick,
            'source' => AttendanceSource::Staff,
            'attachment_path' => 'surat/lain.jpg',
        ]);
        Storage::disk('local')->put('surat/lain.jpg', 'x');

        $this->actingAs($this->wali)->get('/izin')->assertInertia(fn (Assert $page) => $page->has('excuses', 0));
        $this->actingAs($this->wali)->get("/surat/{$otherMark->id}")->assertForbidden();
    }

    public function test_dispensasi_only_for_their_own_students(): void
    {
        $payload = ['from' => '2026-09-28', 'to' => '2026-09-28', 'reason' => 'Lomba'];

        $this->actingAs($this->wali)->post('/dispensasi', ['student_id' => $this->otherStudent->id, ...$payload])->assertSessionHasErrors('student_id');
        $this->assertDatabaseCount('dispensations', 0);

        $otherRequest = Dispensation::factory()->create([
            'student_id' => $this->otherStudent->id,
            'classroom_id' => $this->otherClassroom->id,
            'attachment_path' => 'dispensasi/lain.pdf',
        ]);
        Storage::disk('local')->put('dispensasi/lain.pdf', 'x');

        $this->actingAs($this->wali)->get('/dispensasi')->assertInertia(fn (Assert $page) => $page
            ->has('awaiting', 0)
            ->has('others', 0)
            ->where('navCounts.pendingDispensations', 0)
        );
        $this->actingAs($this->wali)->post("/dispensasi/{$otherRequest->id}/setujui")->assertSessionHas('error');
        $this->actingAs($this->wali)->post("/dispensasi/{$otherRequest->id}/tolak", ['rejection_reason' => 'x'])->assertSessionHas('error');
        $this->actingAs($this->wali)->get("/dispensasi/{$otherRequest->id}/surat")->assertForbidden();
        $this->actingAs($this->wali)->delete("/dispensasi/{$otherRequest->id}")->assertForbidden();

        $this->assertNull($otherRequest->fresh()->homeroom_approved_at);
    }

    public function test_recap_and_corrections_only_for_their_own_class(): void
    {
        $this->actingAs($this->wali)->get("/rekap?classroom={$this->otherClassroom->id}")->assertForbidden();
        $this->actingAs($this->wali)->post('/rekap/koreksi', [
            'student_id' => $this->otherStudent->id,
            'date' => '2026-09-28',
            'status' => 'S',
            'reason' => 'x',
        ])->assertSessionHasErrors('student_id');
    }
}
