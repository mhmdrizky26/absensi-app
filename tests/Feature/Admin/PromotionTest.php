<?php

namespace Tests\Feature\Admin;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Exports\GraduatesArchiveExport;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AcademicYear $current;

    private AcademicYear $next;

    /**
     * @var array<string, Classroom>
     */
    private array $classrooms = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->current = AcademicYear::factory()->active()->create([
            'name' => '2026/2027', 'odd_semester_starts_on' => '2026-07-13', 'even_semester_starts_on' => '2027-01-04', 'ends_on' => '2027-06-26',
        ]);
        $this->next = AcademicYear::factory()->create([
            'name' => '2027/2028', 'odd_semester_starts_on' => '2027-07-12', 'even_semester_starts_on' => '2028-01-03', 'ends_on' => '2028-06-24',
        ]);

        foreach ([7 => 'VII-A', 8 => 'VIII-A', 9 => 'IX-A'] as $grade => $name) {
            $this->classrooms[$name] = Classroom::factory()->for($this->current)->create([
                'grade' => $grade,
                'name' => $name,
                'homeroom_teacher_id' => User::factory()->waliKelas(),
            ]);
        }
    }

    private function studentIn(string $classroomName, array $attributes = []): Student
    {
        $student = Student::factory()->create($attributes);
        $student->placeIn($this->classrooms[$classroomName], $this->current);

        return $student;
    }

    public function test_activating_the_next_year_opens_the_promotion_screen(): void
    {
        $this->studentIn('VII-A', ['name' => 'Ayu']);

        $this->actingAs($this->admin)
            ->post("/tahun-ajaran/{$this->next->id}/aktifkan")
            ->assertRedirect(route('academic-years.promotion', $this->next));

        $this->assertTrue($this->current->fresh()->is_active);

        $this->actingAs($this->admin)->get("/tahun-ajaran/{$this->next->id}/kenaikan-kelas")->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AcademicYears/Promotion')
            ->where('source.name', '2026/2027')
            ->where('classrooms.0.name', 'VII-A')
            ->where('classrooms.0.target', 'VIII-A')
            ->where('classrooms.0.students.0.name', 'Ayu')
            ->where('classrooms.2.name', 'IX-A')
            ->where('classrooms.2.target', null)
        );
    }

    public function test_promotion_moves_everyone_up_and_deletes_grade_nine(): void
    {
        Storage::fake('local');
        $seventh = $this->studentIn('VII-A');
        $eighth = $this->studentIn('VIII-A');
        $ninth = $this->studentIn('IX-A');
        $graduated = $this->studentIn('IX-A', ['status' => StudentStatus::Graduated]);
        $oldMark = Attendance::factory()->create(['student_id' => $seventh->id, 'classroom_id' => $this->classrooms['VII-A']->id, 'date' => '2026-09-28', 'status' => AttendanceStatus::Present]);
        Storage::disk('local')->put('surat/ix.jpg', 'x');
        Attendance::factory()->create(['student_id' => $ninth->id, 'classroom_id' => $this->classrooms['IX-A']->id, 'date' => '2026-09-28', 'status' => AttendanceStatus::Sick, 'source' => AttendanceSource::Staff, 'attachment_path' => 'surat/ix.jpg']);

        $this->actingAs($this->admin)
            ->post("/tahun-ajaran/{$this->next->id}/kenaikan-kelas", ['confirm_delete' => true])
            ->assertRedirect(route('classrooms.index'))
            ->assertSessionHas('success');

        $this->assertTrue($this->next->fresh()->is_active);
        $this->assertNotNull($this->next->fresh()->promoted_at);

        $newClassrooms = $this->next->classrooms()->get()->keyBy('name');
        $this->assertSame(['IX-A', 'VII-A', 'VIII-A'], $newClassrooms->keys()->sort()->values()->all());
        $this->assertTrue($newClassrooms->every(fn (Classroom $classroom) => $classroom->homeroom_teacher_id === null));
        $this->assertSame('ESSAR7A', $newClassrooms['VII-A']->access_code);
        $this->assertSame('ESSAR8A', $newClassrooms['VIII-A']->access_code);

        $this->assertSame('VIII-A', $seventh->classroomIn($this->next)->name);
        $this->assertSame('IX-A', $eighth->classroomIn($this->next)->name);
        $this->assertModelMissing($ninth);
        Storage::disk('local')->assertMissing('surat/ix.jpg');
        $this->assertModelExists($graduated);

        $this->assertModelExists($oldMark);
        $this->assertSame($this->classrooms['VII-A']->id, $oldMark->fresh()->classroom_id);
        $this->assertSame('VII-A', $seventh->classroomIn($this->current)->name);
    }

    public function test_repeaters_stay_and_leavers_are_marked_transferred(): void
    {
        $repeater = $this->studentIn('VIII-A');
        $ninthRepeater = $this->studentIn('IX-A');
        $leaver = $this->studentIn('VII-A');

        $this->actingAs($this->admin)->post("/tahun-ajaran/{$this->next->id}/kenaikan-kelas", [
            'repeaters' => [$repeater->id, $ninthRepeater->id],
            'leavers' => [$leaver->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame('VIII-A', $repeater->classroomIn($this->next)->name);
        $this->assertSame('IX-A', $ninthRepeater->classroomIn($this->next)->name);
        $this->assertSame(StudentStatus::Transferred, $leaver->fresh()->status);
        $this->assertNull($leaver->classroomIn($this->next));
    }

    public function test_deleting_grade_nine_needs_explicit_confirmation(): void
    {
        $ninth = $this->studentIn('IX-A');

        $this->actingAs($this->admin)
            ->post("/tahun-ajaran/{$this->next->id}/kenaikan-kelas")
            ->assertSessionHasErrors('confirm_delete');

        $this->assertModelExists($ninth);
        $this->assertFalse($this->next->fresh()->is_active);
        $this->assertSame(0, $this->next->classrooms()->count());
    }

    public function test_promotion_runs_only_once(): void
    {
        $seventh = $this->studentIn('VII-A');
        $this->actingAs($this->admin)->post("/tahun-ajaran/{$this->next->id}/kenaikan-kelas");

        $this->actingAs($this->admin)->post("/tahun-ajaran/{$this->current->id}/aktifkan")->assertRedirect(route('academic-years.index'));
        $this->actingAs($this->admin)->post("/tahun-ajaran/{$this->next->id}/aktifkan")->assertRedirect(route('academic-years.index'));
        $this->actingAs($this->admin)->post("/tahun-ajaran/{$this->next->id}/kenaikan-kelas")->assertSessionHas('error');

        $this->assertSame('VIII-A', $seventh->classroomIn($this->next)->name);
        $this->assertSame(3, $this->next->classrooms()->count());
    }

    public function test_admin_can_activate_without_promotion(): void
    {
        $this->studentIn('VII-A');

        $this->actingAs($this->admin)
            ->post("/tahun-ajaran/{$this->next->id}/aktifkan", ['without_promotion' => true])
            ->assertRedirect(route('academic-years.index'));

        $this->assertTrue($this->next->fresh()->is_active);
        $this->assertSame(0, $this->next->classrooms()->count());
    }

    public function test_grade_nine_archive_downloads_before_deletion(): void
    {
        Excel::fake();
        $ninth = $this->studentIn('IX-A', ['name' => 'Budi Lulusan']);
        Attendance::factory()->create(['student_id' => $ninth->id, 'classroom_id' => $this->classrooms['IX-A']->id, 'date' => '2026-09-28', 'status' => AttendanceStatus::Absent]);

        $this->actingAs($this->admin)->get("/tahun-ajaran/{$this->next->id}/kenaikan-kelas/arsip")->assertOk();

        Excel::assertDownloaded('arsip-kelas-ix-2026-2027.xlsx', fn (GraduatesArchiveExport $export): bool => $export->array()[5][4] === 'Budi Lulusan' && $export->array()[5][10] === 1);
    }

    public function test_only_admins_can_promote(): void
    {
        $this->actingAs(User::factory()->waliKelas()->create())->get("/tahun-ajaran/{$this->next->id}/kenaikan-kelas")->assertForbidden();
    }
}
