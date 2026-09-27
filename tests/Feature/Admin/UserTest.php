<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_guru_piket_cannot_manage_users(): void
    {
        $this->actingAs(User::factory()->guruPiket()->create())->get('/pengguna')->assertForbidden();
    }

    public function test_admin_creates_an_account(): void
    {
        $this->actingAs($this->admin)->post('/pengguna', [
            'name' => 'Rina Marlina',
            'username' => ' Rina.M ',
            'role' => 'guru_piket',
            'is_active' => true,
            'password' => 'rahasia123',
        ])->assertSessionHasNoErrors();

        $user = User::firstWhere('username', 'rina.m');
        $this->assertSame(Role::GuruPiket, $user->role);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_password_is_required_only_when_creating(): void
    {
        $this->actingAs($this->admin)
            ->post('/pengguna', ['name' => 'A', 'username' => 'aaa', 'role' => 'admin', 'is_active' => true])
            ->assertSessionHasErrors('password');

        $user = User::factory()->create();
        $oldHash = $user->password;

        $this->actingAs($this->admin)
            ->put("/pengguna/{$user->id}", ['name' => 'Nama Baru', 'username' => $user->username, 'role' => 'wali_kelas', 'is_active' => true, 'password' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame($oldHash, $user->fresh()->password);
    }

    public function test_admin_cannot_demote_or_deactivate_themselves(): void
    {
        $this->actingAs($this->admin)
            ->put("/pengguna/{$this->admin->id}", ['name' => 'Admin', 'username' => $this->admin->username, 'role' => 'guru_piket', 'is_active' => false])
            ->assertSessionHasErrors(['role', 'is_active']);

        $this->assertSame(Role::Admin, $this->admin->fresh()->role);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $this->actingAs($this->admin)->delete("/pengguna/{$this->admin->id}")->assertSessionHas('error');

        $this->assertModelExists($this->admin);
    }

    public function test_changing_a_wali_kelas_role_releases_their_classroom(): void
    {
        $teacher = User::factory()->waliKelas()->create();
        $classroom = Classroom::factory()->for(AcademicYear::factory()->active())->create(['homeroom_teacher_id' => $teacher->id]);

        $this->actingAs($this->admin)
            ->put("/pengguna/{$teacher->id}", ['name' => $teacher->name, 'username' => $teacher->username, 'role' => 'guru_piket', 'is_active' => true])
            ->assertSessionHasNoErrors();

        $this->assertNull($classroom->fresh()->homeroom_teacher_id);
    }

    public function test_deactivated_account_can_no_longer_log_in(): void
    {
        $teacher = User::factory()->waliKelas()->create(['username' => 'guru']);

        $this->actingAs($this->admin)
            ->put("/pengguna/{$teacher->id}", ['name' => $teacher->name, 'username' => 'guru', 'role' => 'wali_kelas', 'is_active' => false]);

        auth()->logout();

        $this->post('/masuk', ['username' => 'guru', 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}
