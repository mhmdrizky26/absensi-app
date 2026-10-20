<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentTest extends TestCase
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

    public function test_wali_kelas_cannot_manage_students(): void
    {
        $this->actingAs(User::factory()->waliKelas()->create())->get('/siswa')->assertForbidden();
    }

    public function test_admin_adds_a_student_to_a_classroom(): void
    {
        $classroom = Classroom::factory()->for($this->academicYear)->create();

        $this->actingAs($this->admin)->post('/siswa', [
            'nis' => '260001',
            'nisn' => '0012345678',
            'name' => 'Ayu Lestari',
            'gender' => 'P',
            'status' => 'aktif',
            'classroom_id' => $classroom->id,
        ])->assertSessionHasNoErrors();

        $student = Student::firstWhere('nis', '260001');
        $this->assertTrue($student->classroomIn($this->academicYear)->is($classroom));
    }

    public function test_updating_a_student_moves_them_to_the_new_classroom(): void
    {
        $from = Classroom::factory()->for($this->academicYear)->create();
        $to = Classroom::factory()->for($this->academicYear)->create();
        $student = Student::factory()->create();
        $student->placeIn($from, $this->academicYear);

        $this->actingAs($this->admin)->put("/siswa/{$student->id}", [
            'nis' => $student->nis,
            'name' => $student->name,
            'gender' => $student->gender->value,
            'status' => 'aktif',
            'classroom_id' => $to->id,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($student->classroomIn($this->academicYear)->is($to));
        $this->assertSame(1, $student->classrooms()->count());
    }

    public function test_classroom_must_belong_to_the_active_year(): void
    {
        $oldClassroom = Classroom::factory()->for(AcademicYear::factory())->create();

        $this->actingAs($this->admin)->post('/siswa', [
            'nis' => '260002',
            'name' => 'Budi',
            'gender' => 'L',
            'status' => 'aktif',
            'classroom_id' => $oldClassroom->id,
        ])->assertSessionHasErrors('classroom_id');
    }

    public function test_nis_is_unique_and_nisn_has_ten_digits(): void
    {
        Student::factory()->create(['nis' => '260003']);

        $this->actingAs($this->admin)->post('/siswa', [
            'nis' => '260003',
            'nisn' => '12345',
            'name' => 'Citra',
            'gender' => 'P',
            'status' => 'aktif',
        ])->assertSessionHasErrors(['nis', 'nisn']);
    }

    public function test_index_filters_by_classroom_and_search(): void
    {
        $classroom = Classroom::factory()->for($this->academicYear)->create();
        $inClass = Student::factory()->create(['name' => 'Dimas Pratama']);
        $inClass->placeIn($classroom, $this->academicYear);
        Student::factory()->create(['name' => 'Dimas Saputra']);

        $this->actingAs($this->admin)
            ->get("/siswa?classroom={$classroom->id}&q=dimas")
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 1)
                ->where('students.data.0.name', 'Dimas Pratama')
                ->where('students.data.0.classroom.id', $classroom->id)
            );

        $this->actingAs($this->admin)
            ->get('/siswa?classroom=tanpa-kelas')
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 1)
                ->where('students.data.0.name', 'Dimas Saputra')
            );
    }

    public function test_parent_whatsapp_numbers_are_saved_normalized_with_consent(): void
    {
        $this->actingAs($this->admin)->post('/siswa', [
            'nis' => '260001', 'name' => 'Ayu', 'gender' => 'P', 'status' => 'aktif',
            'guardians' => [
                ['name' => 'Bu Sari', 'relation' => 'ibu', 'phone' => '0812-3456-7890', 'consented' => true],
                ['name' => 'Pak Budi', 'relation' => 'ayah', 'phone' => '+62 813 1111 2222', 'consented' => false],
            ],
        ])->assertSessionHasNoErrors();

        $student = Student::firstWhere('nis', '260001');
        $contacts = $student->guardianContacts()->orderBy('phone')->get();
        $this->assertSame(['6281234567890', '6281311112222'], $contacts->pluck('phone')->all());
        $this->assertNotNull($contacts[0]->consented_at);
        $this->assertNull($contacts[1]->consented_at);

        $consentedAt = $contacts[0]->consented_at;
        $this->travel(1)->days();
        $this->actingAs($this->admin)->put("/siswa/{$student->id}", [
            'nis' => '260001', 'name' => 'Ayu', 'gender' => 'P', 'status' => 'aktif',
            'guardians' => [['name' => 'Bu Sari', 'relation' => 'ibu', 'phone' => '6281234567890', 'consented' => true]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['6281234567890'], $student->guardianContacts()->pluck('phone')->all());
        $this->assertTrue($student->guardianContacts()->first()->consented_at->equalTo($consentedAt));

        $this->actingAs($this->admin)->put("/siswa/{$student->id}", [
            'nis' => '260001', 'name' => 'Ayu', 'gender' => 'P', 'status' => 'aktif',
            'guardians' => [['phone' => '12345', 'consented' => true]],
        ])->assertSessionHasErrors(['guardians.0.phone' => 'Nomor WA tidak valid. Contoh: 0812 3456 7890.']);
    }
}
