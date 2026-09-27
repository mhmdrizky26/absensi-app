<?php

namespace Tests\Feature\Admin;

use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AcademicYear $academicYear;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->academicYear = AcademicYear::factory()->active()->create();
        $this->classroom = Classroom::factory()->for($this->academicYear)->create();
    }

    private function studentInClass(array $attributes = []): Student
    {
        $student = Student::factory()->create($attributes);
        $student->placeIn($this->classroom, $this->academicYear);

        return $student;
    }

    public function test_new_students_get_a_compact_qr_payload(): void
    {
        $student = Student::factory()->create();

        $this->assertMatchesRegularExpression('/^ABS-[A-HJKMNP-Z2-9]{12}$/', $student->qrPayload());
        $this->assertSame(1, $student->qr_version);
        $this->assertNotNull($student->qr_issued_at);
        $this->assertArrayNotHasKey('qr_token', $student->toArray());
    }

    public function test_payload_lookup_finds_only_current_active_cards(): void
    {
        $student = Student::factory()->create();
        $payload = $student->qrPayload();

        $this->assertTrue(Student::findByQrPayload("  {$payload} ")->is($student));
        $this->assertTrue(Student::findByQrPayload(strtolower($payload))->is($student));
        $this->assertNull(Student::findByQrPayload(substr($payload, 4)));

        $student->revokeCard();
        $this->assertNull(Student::findByQrPayload($payload));

        $student->restoreCard();
        $student->issueNewCard();
        $this->assertNull(Student::findByQrPayload($payload));
        $this->assertTrue(Student::findByQrPayload($student->qrPayload())->is($student));
    }

    public function test_only_admins_can_manage_cards(): void
    {
        $student = $this->studentInClass();
        $piket = User::factory()->guruPiket()->create();

        $this->actingAs($piket)->get('/kartu')->assertForbidden();
        $this->actingAs($piket)->get("/kartu/cetak?classroom={$this->classroom->id}")->assertForbidden();
        $this->actingAs($piket)->post("/siswa/{$student->id}/kartu")->assertForbidden();
    }

    public function test_admin_issues_a_replacement_card(): void
    {
        $student = $this->studentInClass();
        $oldToken = $student->qr_token;

        $this->actingAs($this->admin)->post("/siswa/{$student->id}/kartu")->assertSessionHas('success');

        $student->refresh();
        $this->assertNotSame($oldToken, $student->qr_token);
        $this->assertSame(2, $student->qr_version);
    }

    public function test_admin_revokes_and_restores_a_card(): void
    {
        $student = $this->studentInClass();

        $this->actingAs($this->admin)->post("/siswa/{$student->id}/kartu/cabut");
        $this->assertFalse($student->fresh()->hasActiveCard());

        $this->actingAs($this->admin)->delete("/siswa/{$student->id}/kartu/cabut");
        $this->assertTrue($student->fresh()->hasActiveCard());
    }

    public function test_index_shows_the_chosen_classroom(): void
    {
        $this->studentInClass(['name' => 'Ayu Lestari']);

        $this->actingAs($this->admin)
            ->get("/kartu?classroom={$this->classroom->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Cards/Index')
                ->where('classroom.id', $this->classroom->id)
                ->has('students', 1)
                ->where('students.0.name', 'Ayu Lestari')
                ->where('students.0.isRevoked', false)
            );
    }

    public function test_print_sheet_skips_revoked_cards_and_inactive_students(): void
    {
        $this->studentInClass(['name' => 'Ayu Aktif']);
        $this->studentInClass(['name' => 'Budi Lulus', 'status' => StudentStatus::Graduated]);
        $this->studentInClass(['name' => 'Citra Dicabut'])->revokeCard();

        $this->actingAs($this->admin)
            ->get("/kartu/cetak?classroom={$this->classroom->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Cards/Print')
                ->where('title', "Kelas {$this->classroom->name}")
                ->has('cards', 1)
                ->where('cards.0.name', 'Ayu Aktif')
            );
    }

    public function test_print_sheet_for_chosen_students(): void
    {
        $first = $this->studentInClass();
        $this->studentInClass();
        $third = $this->studentInClass();

        $this->actingAs($this->admin)
            ->get("/kartu/cetak?students={$first->id},{$third->id}")
            ->assertInertia(fn (Assert $page) => $page->has('cards', 2));
    }
}
