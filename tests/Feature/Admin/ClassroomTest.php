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

    public function test_admin_creates_a_classroom_with_the_standard_access_code(): void
    {
        $teacher = User::factory()->waliKelas()->create();

        $this->actingAs($this->admin)
            ->post('/kelas', ['grade' => 8, 'section' => 'c', 'homeroom_teacher_id' => $teacher->id])
            ->assertSessionHasNoErrors();

        $classroom = Classroom::firstWhere('name', 'VIII-C');
        $this->assertTrue($classroom->academicYear->is($this->academicYear));
        $this->assertTrue($classroom->homeroomTeacher->is($teacher));
        $this->assertSame('ESSAR8C', $classroom->access_code);
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
        $this->assertSame(33, Classroom::query()->distinct()->count('access_code'));
        $this->assertSame('ESSAR9K', Classroom::firstWhere('name', 'IX-K')->access_code);
    }

    public function test_the_random_button_replaces_the_code_with_a_random_one(): void
    {
        $classroom = Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-A']);
        $this->assertSame('ESSAR7A', $classroom->access_code);

        $this->actingAs($this->admin)->put("/kelas/{$classroom->id}/kode");

        $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{6}$/', $classroom->fresh()->access_code);
    }

    public function test_all_classes_can_go_back_to_standard_codes(): void
    {
        $seventh = Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-A', 'access_code' => 'ESSAR7B']);
        $other = Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-B', 'access_code' => 'ACAK99']);

        $this->actingAs($this->admin)->post('/kelas/kode-standar')->assertSessionHas('success');

        $this->assertSame('ESSAR7A', $seventh->fresh()->access_code);
        $this->assertSame('ESSAR7B', $other->fresh()->access_code);
    }

    public function test_the_same_standard_code_is_used_again_in_the_next_year(): void
    {
        $thisYear = Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-A']);
        $nextYear = Classroom::factory()->for(AcademicYear::factory())->create(['grade' => 7, 'name' => 'VII-A']);

        $this->assertSame('ESSAR7A', $thisYear->access_code);
        $this->assertSame('ESSAR7A', $nextYear->access_code);
    }

    public function test_a_taken_standard_code_falls_back_to_a_random_one(): void
    {
        Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-A', 'access_code' => 'ESSAR7B']);

        $this->actingAs($this->admin)->post('/kelas', ['grade' => 7, 'section' => 'B'])->assertSessionHasNoErrors();

        $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{6}$/', Classroom::firstWhere('name', 'VII-B')->access_code);
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

    public function test_admin_can_set_an_easy_to_remember_code(): void
    {
        $this->actingAs($this->admin)
            ->post('/kelas', ['grade' => 7, 'section' => 'A', 'access_code' => ' 7a pagi '])
            ->assertSessionHasNoErrors();
        $this->assertSame('7APAGI', Classroom::firstWhere('name', 'VII-A')->access_code);

        $classroom = Classroom::factory()->for($this->academicYear)->create(['grade' => 8, 'name' => 'VIII-A']);
        $this->actingAs($this->admin)
            ->put("/kelas/{$classroom->id}", ['grade' => 8, 'section' => 'A', 'access_code' => 'delapana'])
            ->assertSessionHasNoErrors();
        $this->assertSame('DELAPANA', $classroom->fresh()->access_code);
    }

    public function test_leaving_the_code_empty_keeps_or_generates_one(): void
    {
        $classroom = Classroom::factory()->for($this->academicYear)->create(['grade' => 8, 'name' => 'VIII-A']);
        $oldCode = $classroom->access_code;

        $this->actingAs($this->admin)->put("/kelas/{$classroom->id}", ['grade' => 8, 'section' => 'A', 'access_code' => '']);
        $this->actingAs($this->admin)->post('/kelas', ['grade' => 9, 'section' => 'A', 'access_code' => '']);

        $this->assertSame($oldCode, $classroom->fresh()->access_code);
        $this->assertSame('ESSAR9A', Classroom::firstWhere('name', 'IX-A')->access_code);
    }

    public function test_custom_code_must_be_valid_and_unique(): void
    {
        Classroom::factory()->for($this->academicYear)->create(['access_code' => 'KELASKU']);

        $this->actingAs($this->admin)->post('/kelas', ['grade' => 7, 'section' => 'B', 'access_code' => 'kelasku'])->assertSessionHasErrors('access_code');
        $this->actingAs($this->admin)->post('/kelas', ['grade' => 7, 'section' => 'C', 'access_code' => 'ABC'])->assertSessionHasErrors('access_code');
        $this->actingAs($this->admin)->post('/kelas', ['grade' => 7, 'section' => 'D', 'access_code' => 'KODE-7D!'])->assertSessionHasErrors('access_code');
        $this->actingAs($this->admin)->post('/kelas', ['grade' => 7, 'section' => 'E', 'access_code' => 'TIGABELASHURUF'])->assertSessionHasErrors('access_code');
    }
}
