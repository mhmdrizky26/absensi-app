<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_change_an_account(): void
    {
        $this->put('/profil/username', ['username' => 'baru', 'current_password' => 'password'])->assertRedirect(route('login'));
        $this->put('/profil/password', ['current_password' => 'password', 'password' => 'rahasia-baru', 'password_confirmation' => 'rahasia-baru'])->assertRedirect(route('login'));
    }

    public function test_username_changes_after_confirming_the_current_password(): void
    {
        $user = User::factory()->guruPiket()->create(['username' => 'piket']);

        $this->actingAs($user)
            ->put('/profil/username', ['username' => 'salah', 'current_password' => 'keliru123'])
            ->assertSessionHasErrors(['current_password' => 'Password saat ini salah.']);
        $this->assertSame('piket', $user->fresh()->username);

        $this->actingAs($user)
            ->put('/profil/username', ['username' => '  Budi.Piket ', 'current_password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
        $this->assertSame('budi.piket', $user->fresh()->username);

        $this->post('/keluar');
        $this->post('/masuk', ['username' => 'budi.piket', 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_username_must_be_unique_and_well_formed(): void
    {
        User::factory()->waliKelas()->create(['username' => 'walikelas']);
        $user = User::factory()->waliKelas()->create(['username' => 'wali2']);

        $this->actingAs($user)->put('/profil/username', ['username' => 'walikelas', 'current_password' => 'password'])->assertSessionHasErrors('username');
        $this->actingAs($user)->put('/profil/username', ['username' => 'bu guru', 'current_password' => 'password'])->assertSessionHasErrors('username');

        $this->assertSame('wali2', $user->fresh()->username);
    }

    public function test_password_changes_and_other_remembered_devices_are_signed_out(): void
    {
        $user = User::factory()->waliKelas()->create(['remember_token' => 'token-lama']);

        $this->actingAs($user)
            ->put('/profil/password', ['current_password' => 'password', 'password' => 'rahasia-baru', 'password_confirmation' => 'rahasia-baru'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Password berhasil diganti.');

        $user->refresh();
        $this->assertTrue(Hash::check('rahasia-baru', $user->password));
        $this->assertNotSame('token-lama', $user->remember_token);
    }

    public function test_password_change_is_validated(): void
    {
        $user = User::factory()->guruPiket()->create();

        $this->actingAs($user)
            ->put('/profil/password', ['current_password' => 'keliru123', 'password' => 'rahasia-baru', 'password_confirmation' => 'rahasia-baru'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($user)
            ->put('/profil/password', ['current_password' => 'password', 'password' => 'rahasia-baru', 'password_confirmation' => 'beda-sekali'])
            ->assertSessionHasErrors('password');
        $this->actingAs($user)
            ->put('/profil/password', ['current_password' => 'password', 'password' => 'pendek', 'password_confirmation' => 'pendek'])
            ->assertSessionHasErrors('password');
        $this->actingAs($user)
            ->put('/profil/password', ['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasErrors(['password' => 'Password baru harus berbeda dari password saat ini.']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_admin_has_no_profile_and_changes_their_account_from_the_users_page(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'admin']);

        $this->actingAs($admin)->put('/profil/username', ['username' => 'kepala', 'current_password' => 'password'])->assertForbidden();
        $this->actingAs($admin)->put('/profil/password', ['current_password' => 'password', 'password' => 'rahasia-baru', 'password_confirmation' => 'rahasia-baru'])->assertForbidden();
        $this->assertSame('admin', $admin->fresh()->username);

        $this->actingAs($admin)->put("/pengguna/{$admin->id}", [
            'name' => $admin->name, 'username' => 'admin', 'role' => 'admin', 'is_active' => true, 'password' => 'rahasia-baru',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('rahasia-baru', $admin->fresh()->password));
    }
}
