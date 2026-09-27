<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_page_is_explained_in_indonesian(): void
    {
        $this->get('/tidak-ada')->assertNotFound()->assertSee('Halaman tidak ditemukan');
    }

    public function test_forbidden_page_is_explained_in_indonesian(): void
    {
        $this->actingAs(User::factory()->guruPiket()->create())
            ->get('/kelas')
            ->assertForbidden()
            ->assertSee('Akses ditolak')
            ->assertSee('hubungi admin sekolah');
    }

    public function test_forbidden_page_shows_the_specific_reason(): void
    {
        $academicYear = AcademicYear::factory()->active()->create();
        $wali = User::factory()->waliKelas()->create();
        Classroom::factory()->for($academicYear)->create(['homeroom_teacher_id' => $wali->id]);
        $otherClassroom = Classroom::factory()->for($academicYear)->create();

        $this->actingAs($wali)
            ->get("/rekap?classroom={$otherClassroom->id}")
            ->assertForbidden()
            ->assertSee('Anda tidak punya akses ke kelas ini.');
    }

    public function test_expired_form_goes_back_with_a_message(): void
    {
        Route::middleware('web')->post('/_test/kedaluwarsa', fn () => abort(419));

        $this->from('/izin')
            ->post('/_test/kedaluwarsa')
            ->assertRedirect('/izin')
            ->assertSessionHas('error', 'Halaman sudah terlalu lama dibuka, jadi tadi belum tersimpan. Silakan coba lagi.');

        $this->postJson('/_test/kedaluwarsa')->assertStatus(419);
    }
}
