<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_page(): void
    {
        $this->get('/dasbor')->assertRedirect(route('login'));
    }

    public function test_login_page_renders(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_staff_can_log_in_with_username_and_password(): void
    {
        $user = User::factory()->guruPiket()->create(['username' => 'piket']);

        $response = $this->post(route('login.store'), [
            'username' => 'piket',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['username' => 'piket']);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'username' => 'piket',
            'password' => 'salah',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['username' => 'Username atau password salah.']);
        $this->assertGuest();
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['username' => 'lama']);

        $response = $this->post(route('login.store'), [
            'username' => 'lama',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors(['username' => 'Akun ini sudah dinonaktifkan. Hubungi admin sekolah.']);
        $this->assertGuest();
    }

    public function test_login_is_locked_after_five_failed_attempts(): void
    {
        User::factory()->create(['username' => 'piket']);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), ['username' => 'piket', 'password' => 'salah']);
        }

        $response = $this->post(route('login.store'), [
            'username' => 'piket',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertStringContainsString('Terlalu banyak percobaan', session('errors')->first('username'));
        $this->assertGuest();
    }

    public function test_dashboard_shares_the_logged_in_users_role(): void
    {
        $user = User::factory()->waliKelas()->create(['name' => 'Siti Rahmawati']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.user.name', 'Siti Rahmawati')
                ->where('auth.user.role', 'wali_kelas')
                ->where('auth.user.roleLabel', 'Wali Kelas')
                ->missing('auth.user.password')
            );
    }

    public function test_logged_in_user_is_redirected_away_from_login_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_staff_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
