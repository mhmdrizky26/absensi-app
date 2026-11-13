<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\DutySchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
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
            'role' => 'guru',
            'is_active' => true,
            'password' => 'rahasia123',
        ])->assertSessionHasNoErrors();

        $user = User::firstWhere('username', 'rina.m');
        $this->assertSame(Role::Guru, $user->role);
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
            ->put("/pengguna/{$user->id}", ['name' => 'Nama Baru', 'username' => $user->username, 'role' => 'guru', 'is_active' => true, 'password' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame($oldHash, $user->fresh()->password);
    }

    public function test_admin_cannot_demote_or_deactivate_themselves(): void
    {
        $this->actingAs($this->admin)
            ->put("/pengguna/{$this->admin->id}", ['name' => 'Admin', 'username' => $this->admin->username, 'role' => 'guru', 'is_active' => false])
            ->assertSessionHasErrors(['role', 'is_active']);

        $this->assertSame(Role::Admin, $this->admin->fresh()->role);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $this->actingAs($this->admin)->delete("/pengguna/{$this->admin->id}")->assertSessionHas('error');

        $this->assertModelExists($this->admin);
    }

    public function test_a_guru_who_becomes_guru_bk_loses_their_classroom_and_duty_days(): void
    {
        $teacher = User::factory()->guru()->create();
        DutySchedule::factory()->create(['user_id' => $teacher->id, 'weekday' => 3]);
        $classroom = Classroom::factory()->for(AcademicYear::factory()->active())->create(['homeroom_teacher_id' => $teacher->id]);

        $this->actingAs($this->admin)->get('/pengguna')->assertInertia(fn (Assert $page) => $page
            ->where('users.data.1.homeroomClassroom', $classroom->name)
            ->where('users.data.1.dutyDays', ['Rabu'])
        );

        $this->actingAs($this->admin)
            ->put("/pengguna/{$teacher->id}", ['name' => $teacher->name, 'username' => $teacher->username, 'role' => 'guru_bk', 'is_active' => true])
            ->assertSessionHasNoErrors();

        $this->assertNull($classroom->fresh()->homeroom_teacher_id);
        $this->assertSame(0, $teacher->dutySchedules()->count());
    }

    public function test_deactivated_account_can_no_longer_log_in(): void
    {
        $teacher = User::factory()->waliKelas()->create(['username' => 'guru']);

        $this->actingAs($this->admin)
            ->put("/pengguna/{$teacher->id}", ['name' => $teacher->name, 'username' => 'guru', 'role' => 'guru', 'is_active' => false]);

        auth()->logout();

        $this->post('/masuk', ['username' => 'guru', 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}
