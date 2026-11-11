<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_carry_a_title_description_and_link_preview_but_stay_out_of_search(): void
    {
        Setting::put([Setting::APP_NAME => 'SiHadir', Setting::SCHOOL_NAME => 'SMP Negeri 1 Contoh']);

        $this->get('/masuk')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<title inertia>SiHadir</title>', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('<meta name="application-name" content="SiHadir">', false)
            ->assertSee('Absensi siswa SMP Negeri 1 Contoh: scan kartu QR di gerbang', false)
            ->assertSee('<meta property="og:title" content="SiHadir">', false)
            ->assertSee('<meta property="og:image" content="'.url('/icon-512.png').'">', false)
            ->assertSee('<link rel="manifest" href="'.route('manifest').'">', false);
    }

    public function test_the_app_can_be_added_to_a_tablets_home_screen(): void
    {
        Setting::put([Setting::APP_NAME => 'SiHadir']);

        $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('short_name', 'SiHadir')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('icons.1.src', '/icon-512.png');

        $this->assertFileExists(public_path('icon-512.png'));
        $this->assertGreaterThan(0, filesize(public_path('favicon.ico')));
        $this->assertStringContainsString('Disallow: /', file_get_contents(public_path('robots.txt')));
    }
}
