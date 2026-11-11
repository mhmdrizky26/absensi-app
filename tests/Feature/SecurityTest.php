<?php

namespace Tests\Feature;

use App\Exports\UserAccountsExport;
use App\Models\GateStation;
use App\Models\User;
use App\Support\GateNetwork;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_cannot_fake_their_ip_with_x_forwarded_for(): void
    {
        GateStation::factory()->create(['code' => 'TAB1']);
        app(GateNetwork::class)->save(['203.0.113.7']);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
            ->withHeader('X-Forwarded-For', '203.0.113.7')
            ->post('/kode', ['code' => 'TAB1'])
            ->assertSessionHasErrors(['code' => 'Tab gerbang hanya bisa dipakai dari jaringan WiFi sekolah.']);
    }

    public function test_cloudflare_tunnel_on_this_server_passes_the_real_visitor_ip(): void
    {
        GateStation::factory()->create(['code' => 'TAB1']);
        app(GateNetwork::class)->save(['203.0.113.7']);
        config(['attendance.enforce_window' => false]);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['CF-Connecting-IP' => '203.0.113.7', 'X-Forwarded-For' => '198.51.100.9'])
            ->post('/kode', ['code' => 'TAB1'])
            ->assertRedirect(route('gate.scanner'));
    }

    public function test_every_page_sends_browser_protection_headers(): void
    {
        $this->get('/masuk')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'")
            ->assertHeaderMissing('Strict-Transport-Security');

        $response = $this->get('https://localhost/masuk')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $this->assertTrue(collect($response->headers->getCookies())->every(fn ($cookie): bool => $cookie->isSecure()), 'Cookie hanya dikirim lewat https.');
    }

    public function test_a_deactivated_account_is_logged_out_on_its_next_request(): void
    {
        $user = User::factory()->guruPiket()->create();
        $this->actingAs($user)->get('/pantauan')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/pantauan')->assertRedirect(route('login'))->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_admins_are_warned_when_the_server_runs_with_debug_on(): void
    {
        config(['app.debug' => true]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('http://sekolah.example/dasbor')->assertInertia(fn ($page) => $page->has('serverWarnings', 2));
        $this->actingAs($admin)->get('http://localhost/dasbor')->assertInertia(fn ($page) => $page->where('serverWarnings', []));
        $this->actingAs(User::factory()->waliKelas()->create())->get('http://sekolah.example/dasbor')->assertInertia(fn ($page) => $page->where('serverWarnings', []));
    }

    public function test_text_that_looks_like_an_excel_formula_stays_text(): void
    {
        $raw = Excel::raw(new UserAccountsExport([
            ['name' => '=HYPERLINK("http://jahat.example","klik")', 'role' => 'Wali Kelas', 'classroom' => null, 'username' => 'x', 'password' => null],
        ], 'SMP'), ExcelFormat::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'akun').'.xlsx';
        file_put_contents($path, $raw);

        $cell = IOFactory::load($path)->getActiveSheet()->getCell('B6');

        $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
        $this->assertSame(1, IOFactory::load($path)->getActiveSheet()->getCell('A6')->getValue(), 'Angka tetap angka.');
    }
}
