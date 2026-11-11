<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class CreateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_first_admin_is_created_by_answering_the_questions(): void
    {
        $this->artisan('admin:buat')
            ->expectsQuestion('Nama lengkap', 'Bu Kepala TU')
            ->expectsQuestion('Username', 'TataUsaha')
            ->expectsQuestion('Password (minimal 8 karakter)', 'rahasia-sekolah')
            ->expectsQuestion('Ulangi password', 'rahasia-sekolah')
            ->expectsOutput('Akun admin tatausaha dibuat. Silakan masuk di halaman login.')
            ->assertSuccessful();

        $admin = User::sole();
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Auth::validate(['username' => 'tatausaha', 'password' => 'rahasia-sekolah']));
    }

    public function test_options_skip_the_questions_and_bad_input_creates_nothing(): void
    {
        $this->artisan('admin:buat', ['--name' => 'Admin', '--username' => 'admin', '--password' => 'pendek'])
            ->expectsOutputToContain('8')
            ->assertFailed();

        $this->artisan('admin:buat')
            ->expectsQuestion('Nama lengkap', 'Admin')
            ->expectsQuestion('Username', 'admin')
            ->expectsQuestion('Password (minimal 8 karakter)', 'rahasia-sekolah')
            ->expectsQuestion('Ulangi password', 'beda-sekali')
            ->expectsOutput('Password tidak sama. Akun tidak dibuat.')
            ->assertFailed();

        $this->assertSame(0, User::count());

        $this->artisan('admin:buat', ['--name' => 'Admin', '--username' => 'admin', '--password' => 'rahasia-sekolah'])->assertSuccessful();
        $this->artisan('admin:buat', ['--name' => 'Admin 2', '--username' => 'admin', '--password' => 'rahasia-sekolah'])
            ->expectsOutput('Username admin sudah dipakai.')
            ->assertFailed();
    }
}
