<?php

namespace Tests\Feature\Admin;

use App\Actions\ImportUsers;
use App\Enums\Role;
use App\Exports\UserAccountsExport;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class UserImportTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $academicYear;

    private Classroom $classA;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->academicYear = AcademicYear::factory()->active()->create();
        $this->classA = Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-A']);
        $this->admin = User::factory()->admin()->create(['username' => 'admin']);
    }

    /**
     * @param  list<array{0: string, 1?: string, 2?: string, 3?: string, 4?: string}>  $rows  nama, peran, wali kelas, username, password
     * @return array<string, mixed>
     */
    private function import(array $rows): array
    {
        $keyed = array_map(fn (array $row): array => array_combine(['nama', 'peran', 'wali_kelas', 'username', 'password'], array_pad($row, 5, '')), $rows);

        return app(ImportUsers::class)->handle($keyed, $this->academicYear);
    }

    public function test_empty_username_and_password_are_made_up_and_the_wali_kelas_gets_their_class(): void
    {
        $result = $this->import([
            ['Budi Santoso, S.Pd.', 'wali kelas', '7a'],
            ['Dewi Lestari, S.Pd.', 'guru piket'],
            ['Sri Wahyuni, S.Psi.', 'guru bk', '', 'bu.sri', 'rahasia123'],
            ['Budi Santoso, M.Pd.', ''],
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(4, $result['created']);

        $budi = User::firstWhere('username', 'budi.santoso');
        $this->assertSame(Role::WaliKelas, $budi->role);
        $this->assertTrue($this->classA->fresh()->homeroomTeacher->is($budi));
        $this->assertSame(Role::GuruPiket, User::firstWhere('username', 'dewi.lestari')->role);
        $this->assertSame('budi.santoso2', $result['accounts'][3]['username']);

        $password = $result['accounts'][0]['password'];
        $this->assertMatchesRegularExpression('/^[a-hj-km-np-z2-9]{8}$/', $password);
        $this->assertTrue(Auth::validate(['username' => 'budi.santoso', 'password' => $password]));
        $this->assertTrue(Auth::validate(['username' => 'bu.sri', 'password' => 'rahasia123']));
    }

    public function test_importing_again_updates_without_changing_passwords(): void
    {
        $first = $this->import([['Budi Santoso, S.Pd.', 'wali kelas', 'VII-A']]);
        $password = $first['accounts'][0]['password'];

        $second = $this->import([['Budi Santoso, S.Pd.', 'guru piket']]);

        $this->assertSame(['created' => 0, 'updated' => 1], ['created' => $second['created'], 'updated' => $second['updated']]);
        $this->assertNull($second['accounts'][0]['password']);
        $this->assertSame(1, User::where('role', '!=', Role::Admin)->count());
        $this->assertTrue(Auth::validate(['username' => 'budi.santoso', 'password' => $password]));
        $this->assertNull($this->classA->fresh()->homeroom_teacher_id, 'Bukan wali kelas lagi, jadi kelasnya dilepas.');
    }

    public function test_invalid_rows_save_nothing(): void
    {
        $other = User::factory()->waliKelas()->create();
        Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-B', 'homeroom_teacher_id' => $other->id]);

        $result = $this->import([
            ['', 'guru piket'],
            ['Ayu', 'kepala sekolah'],
            ['Citra', 'guru piket', 'VII-A'],
            ['Dodi', 'wali kelas', 'IX-Z'],
            ['Eka', 'wali kelas', 'VII-B'],
            ['Fajar', 'guru bk', '', 'admin'],
            ['Gita', 'guru bk', '', 'Bukan Username!'],
            ['Hadi', 'guru bk', '', '', 'pendek'],
        ]);

        $this->assertCount(8, $result['errors']);
        $this->assertStringContainsString('Baris 2: Nama kosong', $result['errors'][0]);
        $this->assertStringContainsString('Peran "kepala sekolah"', $result['errors'][1]);
        $this->assertStringContainsString('hanya untuk peran wali kelas', $result['errors'][2]);
        $this->assertStringContainsString('sudah punya wali kelas lain', $result['errors'][4]);
        $this->assertStringContainsString('milik akun admin', $result['errors'][5]);
        $this->assertSame(2, User::count());
    }

    public function test_upload_then_download_the_accounts_to_hand_out(): void
    {
        $csv = "Nama,Peran,Wali Kelas,Username,Password\nBudi Santoso,wali kelas,VII-A,,\n";

        $this->actingAs($this->admin)
            ->post('/pengguna/import', ['file' => UploadedFile::fake()->createWithContent('guru.csv', $csv)])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success', 'Import selesai: 1 akun baru, 0 akun diperbarui. Unduh daftar akun untuk dibagikan ke guru.');

        $this->actingAs($this->admin)->get('/pengguna')->assertInertia(fn (Assert $page) => $page->where('importedAccounts', 1));

        Excel::fake();
        $this->actingAs($this->admin)->get('/pengguna/import/akun')->assertOk();
        Excel::assertDownloaded('daftar-akun-guru.xlsx', fn (UserAccountsExport $export): bool => $export->array()[5][4] === 'budi.santoso' && strlen($export->array()[5][5]) === 8);

        $this->actingAs($this->admin)->delete('/pengguna/import/akun')->assertSessionHas('success');
        $this->actingAs($this->admin)->get('/pengguna')->assertInertia(fn (Assert $page) => $page->where('importedAccounts', 0));
    }

    public function test_template_keeps_numbers_as_text_and_offers_the_roles(): void
    {
        $response = $this->actingAs($this->admin)->get('/pengguna/import/template')->assertOk()->assertDownload('template-import-guru.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, $response->streamedContent() ?: file_get_contents($response->baseResponse->getFile()->getPathname()));
        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertSame(['Nama', 'Peran', 'Wali Kelas', 'Username', 'Password'], $sheet->rangeToArray('A1:E1')[0]);
        $this->assertSame('"wali kelas,guru piket,guru bk"', $sheet->getCell('B50')->getDataValidation()->getFormula1());
        $this->assertSame('@', $sheet->getStyle('E50')->getNumberFormat()->getFormatCode());
    }

    public function test_only_admins_import_users(): void
    {
        $this->actingAs(User::factory()->guruPiket()->create())->get('/pengguna/import')->assertForbidden();
    }
}
