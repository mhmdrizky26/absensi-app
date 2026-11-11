<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClassroomTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AcademicYear $academicYear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->academicYear = AcademicYear::factory()->active()->create();
    }

    public function test_guru_piket_cannot_see_classrooms_or_their_codes(): void
    {
        $this->actingAs(User::factory()->guruPiket()->create())->get('/kelas')->assertForbidden();
    }

    public function test_admin_creates_a_classroom(): void
    {
        $teacher = User::factory()->waliKelas()->create();

        $this->actingAs($this->admin)
            ->post('/kelas', ['grade' => 8, 'section' => 'c', 'homeroom_teacher_id' => $teacher->id])
            ->assertSessionHasNoErrors();

        $classroom = Classroom::firstWhere('name', 'VIII-C');
        $this->assertTrue($classroom->academicYear->is($this->academicYear));
        $this->assertTrue($classroom->homeroomTeacher->is($teacher));
        $this->assertSame('8C', $classroom->view_code);
    }

    public function test_classroom_name_must_be_unique_within_the_year(): void
    {
        Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-A']);

        $this->actingAs($this->admin)
            ->post('/kelas', ['grade' => 7, 'section' => 'A'])
            ->assertSessionHasErrors('name');
    }

    public function test_a_teacher_can_lead_only_one_classroom_per_year(): void
    {
        $teacher = User::factory()->waliKelas()->create();
        Classroom::factory()->for($this->academicYear)->create(['homeroom_teacher_id' => $teacher->id]);

        $this->actingAs($this->admin)
            ->post('/kelas', ['grade' => 9, 'section' => 'B', 'homeroom_teacher_id' => $teacher->id])
            ->assertSessionHasErrors('homeroom_teacher_id');
    }

    public function test_only_wali_kelas_accounts_can_be_homeroom_teachers(): void
    {
        $piket = User::factory()->guruPiket()->create();

        $this->actingAs($this->admin)
            ->post('/kelas', ['grade' => 9, 'section' => 'B', 'homeroom_teacher_id' => $piket->id])
            ->assertSessionHasErrors('homeroom_teacher_id');
    }

    public function test_batch_creates_missing_sections_and_skips_existing_ones(): void
    {
        Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-A']);

        $this->actingAs($this->admin)
            ->post('/kelas/massal', ['grades' => [7, 8, 9], 'count' => 11])
            ->assertSessionHas('success', '32 kelas baru dibuat.');

        $this->assertSame(33, $this->academicYear->classrooms()->count());
        $this->assertSame('9K', Classroom::query()->where('name', 'IX-K')->value('view_code'));
    }

    public function test_admin_sets_a_custom_class_code_and_an_empty_one_goes_back_to_standard(): void
    {
        $this->actingAs($this->admin)->post('/kelas', ['grade' => 7, 'section' => 'A', 'view_code' => ' 7A Pagi '])->assertSessionHasNoErrors();
        $classroom = Classroom::firstWhere('name', 'VII-A');
        $this->assertSame('7APAGI', $classroom->view_code);

        $this->actingAs($this->admin)->post('/kelas', ['grade' => 7, 'section' => 'B', 'view_code' => '7APAGI'])->assertSessionHasErrors('view_code');

        $this->actingAs($this->admin)->put("/kelas/{$classroom->id}", ['grade' => 7, 'section' => 'A', 'view_code' => ''])->assertSessionHasNoErrors();
        $this->assertSame('7A', $classroom->fresh()->view_code);

        $this->actingAs($this->admin)->get('/kelas')->assertInertia(fn (Assert $page) => $page->where('classrooms.0.viewCode', '7A'));
    }

    public function test_the_same_class_code_comes_back_in_the_next_year(): void
    {
        $thisYear = Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-A']);
        $nextYear = Classroom::factory()->for(AcademicYear::factory())->create(['grade' => 7, 'name' => 'VII-A']);

        $this->assertSame('7A', $thisYear->view_code);
        $this->assertSame('7A', $nextYear->view_code);
    }

    public function test_classroom_with_students_cannot_be_deleted(): void
    {
        $classroom = Classroom::factory()->for($this->academicYear)->create();
        Student::factory()->create()->placeIn($classroom, $this->academicYear);

        $this->actingAs($this->admin)->delete("/kelas/{$classroom->id}")->assertSessionHas('error');

        $this->assertModelExists($classroom);
    }

    public function test_index_lists_classrooms_of_the_active_year_only(): void
    {
        Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-A']);
        Classroom::factory()->for(AcademicYear::factory())->create(['grade' => 7, 'name' => 'VII-Z']);

        $this->actingAs($this->admin)
            ->get('/kelas')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Classrooms/Index')
                ->has('classrooms', 1)
                ->where('classrooms.0.name', 'VII-A')
            );
    }
}
