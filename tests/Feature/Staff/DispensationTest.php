<?php

namespace Tests\Feature\Staff;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\DispensationStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Dispensation;
use App\Models\Student;
use App\Models\User;
use App\Support\AttendanceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DispensationTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $academicYear;

    private User $wali;

    private User $piket;

    private Classroom $classroom;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(9, 0)); // Senin
        config(['attendance.school_days' => [1, 2, 3, 4, 5]]);

        $this->academicYear = AcademicYear::factory()->active()->create();
        $this->wali = User::factory()->waliKelas()->create(['name' => 'Bu Wali']);
        $this->piket = User::factory()->guruPiket()->create(['name' => 'Pak Piket']);
        $this->classroom = Classroom::factory()->for($this->academicYear)->create(['name' => 'VII-A', 'homeroom_teacher_id' => $this->wali->id]);
        $this->student = Student::factory()->create(['name' => 'Ayu']);
        $this->student->placeIn($this->classroom, $this->academicYear);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return ['student_id' => $this->student->id, 'from' => '2026-09-28', 'to' => '2026-09-29', 'reason' => 'Lomba cerdas cermat', ...$overrides];
    }

    public function test_admin_request_needs_both_wali_kelas_and_guru_piket(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/dispensasi', $this->payload())
            ->assertSessionHas('success', 'Dispensasi Ayu diajukan. Menunggu persetujuan wali kelas dan guru piket.');

        $dispensation = Dispensation::sole();

        $this->actingAs($this->wali)->post("/dispensasi/{$dispensation->id}/setujui")
            ->assertSessionHas('success', 'Persetujuan Anda tercatat. Menunggu persetujuan guru piket.');
        $this->assertDatabaseCount('attendances', 0);

        $this->actingAs($this->piket)->post("/dispensasi/{$dispensation->id}/setujui")
            ->assertSessionHas('success', 'Dispensasi Ayu disetujui. 2 hari sekolah dicatat Dispensasi.');

        $dispensation->refresh();
        $this->assertSame(DispensationStatus::Approved, $dispensation->status);
        $this->assertTrue($dispensation->homeroomApprover->is($this->wali));
        $this->assertTrue($dispensation->dutyApprover->is($this->piket));

        $marks = Attendance::orderBy('date')->get();
        $this->assertSame(['2026-09-28', '2026-09-29'], $marks->map->date->map->toDateString()->all());
        $this->assertSame([AttendanceStatus::Dispensation, AttendanceStatus::Dispensation], $marks->pluck('status')->all());
        $this->assertSame(AttendanceSource::Dispensation, $marks->first()->source);
        $this->assertSame($dispensation->id, $marks->first()->dispensation_id);
    }

    public function test_the_requester_approval_counts_when_they_are_an_approver(): void
    {
        $this->actingAs($this->wali)->post('/dispensasi', $this->payload())
            ->assertSessionHas('success', 'Dispensasi Ayu diajukan. Menunggu persetujuan guru piket.');

        $dispensation = Dispensation::sole();
        $this->assertNotNull($dispensation->homeroom_approved_at);
        $this->assertFalse($dispensation->awaitsDecisionFrom($this->wali));
        $this->assertTrue($dispensation->awaitsDecisionFrom($this->piket));

        $this->actingAs($this->piket)->post("/dispensasi/{$dispensation->id}/setujui");

        $this->assertSame(DispensationStatus::Approved, $dispensation->fresh()->status);
    }

    public function test_approval_replaces_alpa_but_keeps_days_at_school(): void
    {
        Attendance::factory()->create(['student_id' => $this->student->id, 'classroom_id' => $this->classroom->id, 'date' => '2026-09-28', 'status' => AttendanceStatus::Absent, 'source' => AttendanceSource::Automatic]);
        Attendance::factory()->create(['student_id' => $this->student->id, 'classroom_id' => $this->classroom->id, 'date' => '2026-09-29', 'status' => AttendanceStatus::Present]);

        $this->actingAs($this->piket)->post('/dispensasi', $this->payload());
        $this->actingAs($this->wali)->post('/dispensasi/'.Dispensation::sole()->id.'/setujui')
            ->assertSessionHas('success', 'Dispensasi Ayu disetujui. 1 hari sekolah dicatat Dispensasi.');

        $monday = Attendance::whereDate('date', '2026-09-28')->sole();
        $this->assertSame(AttendanceStatus::Dispensation, $monday->status);
        $this->assertSame(AttendanceStatus::Absent, $monday->logs()->sole()->from_status);
        $this->assertSame(AttendanceStatus::Present, Attendance::whereDate('date', '2026-09-29')->sole()->status);
    }

    public function test_a_rejection_stops_the_request(): void
    {
        $this->actingAs($this->wali)->post('/dispensasi', $this->payload());
        $dispensation = Dispensation::sole();

        $this->actingAs($this->piket)->post("/dispensasi/{$dispensation->id}/tolak", [])->assertSessionHasErrors('rejection_reason');
        $this->actingAs($this->piket)->post("/dispensasi/{$dispensation->id}/tolak", ['rejection_reason' => 'Tidak ada surat tugas'])
            ->assertSessionHas('success');

        $dispensation->refresh();
        $this->assertSame(DispensationStatus::Rejected, $dispensation->status);
        $this->assertSame('Tidak ada surat tugas', $dispensation->rejection_reason);

        $this->actingAs(User::factory()->guruPiket()->create())->post("/dispensasi/{$dispensation->id}/setujui")->assertSessionHas('error');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_only_the_class_wali_and_guru_piket_can_decide(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post('/dispensasi', $this->payload());
        $dispensation = Dispensation::sole();
        $otherWali = User::factory()->waliKelas()->create();
        Classroom::factory()->for($this->academicYear)->create(['homeroom_teacher_id' => $otherWali->id]);

        $this->actingAs($otherWali)->post("/dispensasi/{$dispensation->id}/setujui")->assertSessionHas('error');
        $this->actingAs(User::factory()->admin()->create())->post("/dispensasi/{$dispensation->id}/setujui")->assertSessionHas('error');

        $this->assertNull($dispensation->fresh()->homeroom_approved_at);
    }

    public function test_wali_kelas_requests_only_for_their_own_class(): void
    {
        $otherWali = User::factory()->waliKelas()->create();

        $this->actingAs($otherWali)->post('/dispensasi', $this->payload())->assertSessionHasErrors('student_id');
        $this->actingAs($this->wali)->post('/dispensasi', $this->payload(['to' => '2026-10-20']))->assertSessionHasErrors('to');

        $this->assertDatabaseCount('dispensations', 0);
    }

    public function test_pending_requests_are_counted_in_the_menu(): void
    {
        $this->actingAs($this->wali)->post('/dispensasi', $this->payload());

        $this->actingAs($this->piket)->get('/dispensasi')->assertInertia(fn (Assert $page) => $page
            ->component('Staff/Dispensations')
            ->where('navCounts.pendingDispensations', 1)
            ->has('awaiting', 1)
            ->where('awaiting.0.canDecide', true)
        );

        $this->actingAs($this->wali)->get('/dispensasi')->assertInertia(fn (Assert $page) => $page
            ->where('navCounts.pendingDispensations', 0)
            ->has('awaiting', 0)
            ->has('others', 1)
            ->where('others.0.canCancel', true)
        );
    }

    public function test_requester_can_cancel_a_pending_request_with_its_letter(): void
    {
        Storage::fake('local');
        $this->actingAs($this->piket)->post('/dispensasi', $this->payload(['attachment' => UploadedFile::fake()->image('surat-tugas.jpg')]));
        $dispensation = Dispensation::sole();
        Storage::disk('local')->assertExists($dispensation->attachment_path);

        $this->actingAs($this->wali)->get("/dispensasi/{$dispensation->id}/surat")->assertOk();
        $this->actingAs($this->wali)->delete("/dispensasi/{$dispensation->id}")->assertForbidden();
        $this->actingAs($this->piket)->delete("/dispensasi/{$dispensation->id}")->assertSessionHas('success');

        $this->assertModelMissing($dispensation);
        Storage::disk('local')->assertMissing($dispensation->attachment_path);
    }

    public function test_dispensasi_counts_as_present_in_the_rate(): void
    {
        $this->assertSame(100, app(AttendanceReport::class)->rate(['H' => 1, 'T' => 0, 'D' => 1, 'S' => 0, 'I' => 0, 'A' => 0]));
        $this->assertSame(50, app(AttendanceReport::class)->rate(['H' => 0, 'T' => 0, 'D' => 1, 'S' => 0, 'I' => 0, 'A' => 1]));
    }

    public function test_class_scan_leaves_a_dispensasi_alone_and_wali_cannot_set_it_directly(): void
    {
        Attendance::factory()->create(['student_id' => $this->student->id, 'classroom_id' => $this->classroom->id, 'date' => '2026-09-28', 'status' => AttendanceStatus::Dispensation, 'source' => AttendanceSource::Dispensation]);
        $this->assertTrue(AttendanceStatus::Dispensation->isAtSchool());

        $this->actingAs($this->wali)->post('/rekap/koreksi', [
            'student_id' => $this->student->id,
            'date' => '2026-09-25',
            'status' => 'D',
            'reason' => 'coba',
        ])->assertSessionHasErrors('status');
    }
}
