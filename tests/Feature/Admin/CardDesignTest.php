<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Support\CardDesign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CardDesignTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(CardDesign::BACKGROUND_DISK);
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(string $mode, string $orientation = 'portrait', array $overrides = []): array
    {
        $defaults = CardDesign::defaults($orientation);

        return array_replace_recursive([
            'mode' => $mode,
            'orientation' => $orientation,
            'qr' => $defaults['qr'],
            'name' => $defaults['name'],
            'nis' => $defaults['nis'],
        ], $overrides);
    }

    public function test_the_built_in_landscape_card_is_used_until_the_admin_chooses_another(): void
    {
        $this->actingAs($this->admin)->get('/kartu/desain')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Cards/Design')
            ->where('design.mode', 'landscape')
            ->where('design.backgroundUrl', null)
            ->where('sample.name', 'Ayu Lestari Rahmawati')
        );

        $this->actingAs($this->admin)->put('/kartu/desain', $this->payload('portrait'))->assertSessionHas('success');

        $this->assertSame('portrait', app(CardDesign::class)->get()['mode']);
    }

    public function test_the_school_design_needs_a_background_first(): void
    {
        $this->actingAs($this->admin)->put('/kartu/desain', $this->payload('custom'))
            ->assertSessionHas('error', 'Unggah gambar latar desain sekolah dulu sebelum memakai desain sekolah.');

        $this->assertSame('landscape', app(CardDesign::class)->get()['mode']);
    }

    public function test_admin_uploads_a_background_places_the_fields_and_cards_print_with_it(): void
    {
        $this->actingAs($this->admin)
            ->post('/kartu/desain/latar', ['background' => UploadedFile::fake()->image('desain.png', 638, 1011)])
            ->assertSessionHas('success');

        $design = app(CardDesign::class)->get();
        Storage::disk(CardDesign::BACKGROUND_DISK)->assertExists($design['background']);

        $this->actingAs($this->admin)
            ->put('/kartu/desain', $this->payload('custom', 'portrait', ['qr' => ['x' => 5, 'y' => 20, 'size' => 44], 'name' => ['color' => '#FFFFFF']]))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get('/kartu/desain/latar?v=1')->assertOk();

        $academicYear = AcademicYear::factory()->active()->create();
        $classroom = Classroom::factory()->for($academicYear)->create();
        Student::factory()->create()->placeIn($classroom, $academicYear);

        $this->actingAs($this->admin)->get("/kartu/cetak?classroom={$classroom->id}")->assertInertia(fn (Assert $page) => $page
            ->where('cardDesign.mode', 'custom')
            ->where('cardDesign.orientation', 'portrait')
            ->where('cardDesign.width', 54)
            ->where('cardDesign.qr', ['x' => 5, 'y' => 20, 'size' => 44])
            ->where('cardDesign.name.color', '#ffffff')
            ->where('cardDesign.backgroundUrl', route('cards.design.background', ['v' => 1]))
            ->has('cards', 1)
        );
    }

    public function test_fields_must_stay_on_the_card_and_the_qr_must_be_big_enough(): void
    {
        $this->actingAs($this->admin)->put('/kartu/desain', $this->payload('portrait', 'portrait', ['qr' => ['x' => 20, 'size' => 44]]))->assertSessionHasErrors('qr.size');
        $this->actingAs($this->admin)->put('/kartu/desain', $this->payload('portrait', 'portrait', ['qr' => ['size' => 15]]))->assertSessionHasErrors('qr.size');
        $this->actingAs($this->admin)->put('/kartu/desain', $this->payload('portrait', 'portrait', ['name' => ['x' => 30]]))->assertSessionHasErrors('name.x');
        $this->actingAs($this->admin)->put('/kartu/desain', $this->payload('portrait', 'portrait', ['nis' => ['color' => 'merah']]))->assertSessionHasErrors('nis.color');
    }

    public function test_removing_the_background_goes_back_to_the_built_in_card(): void
    {
        $this->actingAs($this->admin)->post('/kartu/desain/latar', ['background' => UploadedFile::fake()->image('desain.jpg', 1011, 638)]);
        $path = app(CardDesign::class)->get()['background'];
        $this->actingAs($this->admin)->put('/kartu/desain', $this->payload('custom', 'landscape'));

        $this->actingAs($this->admin)->delete('/kartu/desain/latar')->assertSessionHas('success');

        Storage::disk(CardDesign::BACKGROUND_DISK)->assertMissing($path);
        $this->assertSame('landscape', app(CardDesign::class)->get()['mode']);
        $this->actingAs($this->admin)->get('/kartu/desain/latar')->assertNotFound();
    }

    public function test_only_images_are_accepted_and_only_admins_change_the_design(): void
    {
        $this->actingAs($this->admin)->post('/kartu/desain/latar', ['background' => UploadedFile::fake()->create('desain.pdf', 100, 'application/pdf')])->assertSessionHasErrors('background');

        $wali = User::factory()->waliKelas()->create();
        $this->actingAs($wali)->get('/kartu/desain')->assertForbidden();
        $this->actingAs($wali)->put('/kartu/desain', $this->payload('portrait'))->assertForbidden();
    }
}
