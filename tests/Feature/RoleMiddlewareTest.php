<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'role:admin,guru_piket'])
            ->get('/_test/khusus-admin-piket', fn () => 'ok');
    }

    public function test_allowed_roles_can_access_the_route(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/_test/khusus-admin-piket')
            ->assertOk();

        $this->actingAs(User::factory()->guruPiket()->create())
            ->get('/_test/khusus-admin-piket')
            ->assertOk();
    }

    public function test_other_roles_are_forbidden(): void
    {
        $this->actingAs(User::factory()->waliKelas()->create())
            ->get('/_test/khusus-admin-piket')
            ->assertForbidden();
    }

    public function test_guru_bk_reaches_only_their_pages(): void
    {
        $bk = User::factory()->guruBk()->create();

        $this->actingAs($bk)->get('/dasbor')->assertOk();

        foreach (['/rekap', '/izin', '/pantauan', '/dispensasi', '/siswa', '/pengguna'] as $path) {
            $this->actingAs($bk)->get($path)->assertForbidden();
        }
    }
}
