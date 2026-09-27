<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicYearTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => '2026/2027',
            'odd_semester_starts_on' => '2026-07-13',
            'even_semester_starts_on' => '2027-01-04',
            'ends_on' => '2027-06-26',
            ...$overrides,
        ];
    }

    public function test_only_admins_can_manage_academic_years(): void
    {
        $this->actingAs(User::factory()->guruPiket()->create())->get('/tahun-ajaran')->assertForbidden();
        $this->actingAs(User::factory()->waliKelas()->create())->post('/tahun-ajaran', $this->validPayload())->assertForbidden();

        $this->assertDatabaseCount('academic_years', 0);
    }

    public function test_first_academic_year_becomes_active(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/tahun-ajaran', $this->validPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame('2026/2027', AcademicYear::active()?->name);
    }

    public function test_later_academic_years_are_not_activated_automatically(): void
    {
        $current = AcademicYear::factory()->active()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/tahun-ajaran', $this->validPayload(['name' => '2095/2096']))
            ->assertSessionHasNoErrors();

        $this->assertTrue(AcademicYear::active()->is($current));
    }

    public function test_name_must_be_two_consecutive_years(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/tahun-ajaran', $this->validPayload(['name' => '2026']))->assertSessionHasErrors('name');
        $this->actingAs($admin)->post('/tahun-ajaran', $this->validPayload(['name' => '2026/2028']))->assertSessionHasErrors('name');
    }

    public function test_semester_dates_must_be_in_order(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/tahun-ajaran', $this->validPayload(['even_semester_starts_on' => '2026-07-01']))
            ->assertSessionHasErrors('even_semester_starts_on');
    }

    public function test_activating_a_year_deactivates_the_others(): void
    {
        $old = AcademicYear::factory()->active()->create();
        $new = AcademicYear::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post("/tahun-ajaran/{$new->id}/aktifkan");

        $this->assertFalse($old->fresh()->is_active);
        $this->assertTrue($new->fresh()->is_active);
    }

    public function test_active_year_or_year_with_classrooms_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $active = AcademicYear::factory()->active()->create();
        $withClassrooms = AcademicYear::factory()->create();
        Classroom::factory()->for($withClassrooms)->create();
        $empty = AcademicYear::factory()->create();

        $this->actingAs($admin)->delete("/tahun-ajaran/{$active->id}")->assertSessionHas('error');
        $this->actingAs($admin)->delete("/tahun-ajaran/{$withClassrooms->id}")->assertSessionHas('error');
        $this->actingAs($admin)->delete("/tahun-ajaran/{$empty->id}")->assertSessionHas('success');

        $this->assertModelExists($active);
        $this->assertModelExists($withClassrooms);
        $this->assertModelMissing($empty);
    }
}
