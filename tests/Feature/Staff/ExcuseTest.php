<?php

namespace Tests\Feature\Staff;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Holiday;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExcuseTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $academicYear;

    private Classroom $classroom;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(9, 0)); // Senin
        config(['attendance.school_days' => [1, 2, 3, 4, 5]]);

        $this->academicYear = AcademicYear::factory()->active()->create();
        $this->classroom = Classroom::factory()->for($this->academicYear)->create(['name' => 'VII-A']);
        $this->student = Student::factory()->create(['name' => 'Ayu']);
        $this->student->placeIn($this->classroom, $this->academicYear);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'student_id' => $this->student->id,
            'status' => 'S',
            'from' => '2026-09-28',
            'to' => '2026-09-28',
            'note' => 'Demam',
            ...$overrides,
        ];
    }

    public function test_piket_records_sick_leave_over_school_days_only(): void
    {
        Holiday::factory()->create(['date' => '2026-10-02']);

        // Kamis 1 Okt s.d. Senin 5 Okt: Jumat libur, Sabtu–Minggu bukan hari sekolah.
        $this->actingAs(User::factory()->guruPiket()->create())
            ->post('/izin', $this->payload(['from' => '2026-10-01', 'to' => '2026-10-05']))
            ->assertSessionHas('success', 'Ayu dicatat Sakit untuk 2 hari (1 Okt, 5 Okt).');

        $this->assertSame(['2026-10-01', '2026-10-05'], Attendance::orderBy('date')->pluck('date')->map->toDateString()->all());
        $this->assertSame(AttendanceSource::Staff, Attendance::first()->source);
    }

    public function test_alpa_becomes_izin_but_days_at_school_are_kept(): void
    {
        Attendance::factory()->create(['student_id' => $this->student->id, 'classroom_id' => $this->classroom->id, 'date' => '2026-09-28', 'status' => AttendanceStatus::Absent, 'source' => AttendanceSource::Automatic]);
        Attendance::factory()->create(['student_id' => $this->student->id, 'classroom_id' => $this->classroom->id, 'date' => '2026-09-29', 'status' => AttendanceStatus::Present]);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/izin', $this->payload(['status' => 'I', 'to' => '2026-09-30', 'note' => 'Acara keluarga']))
            ->assertSessionHas('success', 'Ayu dicatat Izin untuk 2 hari (28 Sep, 30 Sep). 1 hari dilewati karena siswa hadir.');

        $monday = Attendance::whereDate('date', '2026-09-28')->first();
        $this->assertSame(AttendanceStatus::Excused, $monday->status);
        $this->assertSame(AttendanceStatus::Absent, $monday->logs()->sole()->from_status);
        $this->assertSame(AttendanceStatus::Present, Attendance::whereDate('date', '2026-09-29')->first()->status);
    }

    public function test_letter_is_stored_privately_and_shown_to_staff(): void
    {
        Storage::fake('local');
        $piket = User::factory()->guruPiket()->create();

        $this->actingAs($piket)->post('/izin', $this->payload(['attachment' => UploadedFile::fake()->image('surat.jpg')]));

        $attendance = Attendance::firstOrFail();
        $this->assertNotNull($attendance->attachment_path);
        Storage::disk('local')->assertExists($attendance->attachment_path);
        $this->actingAs($piket)->get("/surat/{$attendance->id}")->assertOk();

        $otherWali = User::factory()->waliKelas()->create();
        Classroom::factory()->for($this->academicYear)->create(['homeroom_teacher_id' => $otherWali->id]);
        $this->actingAs($otherWali)->get("/surat/{$attendance->id}")->assertForbidden();
    }

    public function test_wali_kelas_can_only_excuse_their_own_students(): void
    {
        $ownWali = User::factory()->waliKelas()->create();
        $this->classroom->update(['homeroom_teacher_id' => $ownWali->id]);
        $otherWali = User::factory()->waliKelas()->create();

        $this->actingAs($otherWali)->post('/izin', $this->payload())->assertSessionHasErrors('student_id');
        $this->actingAs($ownWali)->post('/izin', $this->payload())->assertSessionHasNoErrors();

        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_one_letter_covers_at_most_fourteen_days(): void
    {
        $this->actingAs(User::factory()->guruPiket()->create())
            ->post('/izin', $this->payload(['to' => '2026-10-12']))
            ->assertSessionHasErrors('to');
    }

    public function test_search_finds_students_and_limits_wali_kelas_to_their_class(): void
    {
        $wali = User::factory()->waliKelas()->create();
        $otherClassroom = Classroom::factory()->for($this->academicYear)->create(['homeroom_teacher_id' => $wali->id]);
        $ownStudent = Student::factory()->create(['name' => 'Ayu Lestari']);
        $ownStudent->placeIn($otherClassroom, $this->academicYear);

        $this->actingAs(User::factory()->guruPiket()->create())
            ->getJson('/cari-siswa?q=ayu')
            ->assertJsonCount(2, 'students');

        $this->actingAs($wali)
            ->getJson('/cari-siswa?q=ayu')
            ->assertJsonCount(1, 'students')
            ->assertJsonPath('students.0.name', 'Ayu Lestari');
    }
}
