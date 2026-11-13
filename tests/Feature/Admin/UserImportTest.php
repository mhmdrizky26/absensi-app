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
     * @param  list<array{0: string, 1?: string, 2?: string, 3?: string, 4?: string, 5?: string}>  $rows  nama, peran, wali kelas, username, password, hari piket
     * @return array<string, mixed>
     */
    private function import(array $rows): array
    {
        $keyed = array_map(fn (array $row): array => array_combine(['nama', 'peran', 'wali_kelas', 'username', 'password', 'hari_piket'], array_pad($row, 6, '')), $rows);

        return app(ImportUsers::class)->handle($keyed, $this->academicYear);
    }

    public function test_empty_username_and_password_are_made_up_and_a_guru_gets_their_class_and_duty_days(): void
    {
        $result = $this->import([
            ['Budi Santoso, S.Pd.', 'guru', '7a', '', '', 'Rabu'],
            ['Dewi Lestari, S.Pd.', 'guru piket', '', '', '', "senin, Jum'at"],
            ['Sri Wahyuni, S.Psi.', 'guru bk', '', 'bu.sri', 'rahasia123'],
            ['Budi Santoso, M.Pd.', ''],
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(4, $result['created']);

        $budi = User::firstWhere('username', 'budi.santoso');
        $this->assertSame(Role::Guru, $budi->role);
        $this->assertTrue($this->classA->fresh()->homeroomTeacher->is($budi));
        $this->assertSame([3], $budi->dutySchedules()->pluck('weekday')->all());
        $dewi = User::firstWhere('username', 'dewi.lestari');
        $this->assertSame(Role::Guru, $dewi->role, 'Peran lama "guru piket" dibaca sebagai guru.');
        $this->assertSame([1, 5], $dewi->dutySchedules()->orderBy('weekday')->pluck('weekday')->all());
        $this->assertSame(Role::Guru, User::firstWhere('username', 'budi.santoso2')->role, 'Peran kosong dianggap guru.');
        $this->assertSame('budi.santoso2', $result['accounts'][3]['username']);

        $password = $result['accounts'][0]['password'];
        $this->assertSame('12345', $password);
        $this->assertTrue(Auth::validate(['username' => 'budi.santoso', 'password' => $password]));
        $this->assertTrue(Auth::validate(['username' => 'bu.sri', 'password' => 'rahasia123']));
    }

    public function test_importing_again_updates_without_changing_passwords_or_an_unfilled_duty_schedule(): void
    {
        $first = $this->import([['Budi Santoso, S.Pd.', 'guru', 'VII-A', '', '', 'Selasa']]);
        $password = $first['accounts'][0]['password'];

        $again = $this->import([['Budi Santoso, S.Pd.', 'guru', 'VII-A']]);
        $this->assertSame([], $again['errors']);
        $this->assertSame([2], User::firstWhere('username', 'budi.santoso')->dutySchedules()->pluck('weekday')->all(), 'Hari Piket kosong tidak mengubah jadwal.');

        $second = $this->import([['Budi Santoso, S.Pd.', 'guru bk']]);

        $this->assertSame(['created' => 0, 'updated' => 1], ['created' => $second['created'], 'updated' => $second['updated']]);
        $this->assertNull($second['accounts'][0]['password']);
        $this->assertSame(1, User::where('role', '!=', Role::Admin)->count());
        $this->assertTrue(Auth::validate(['username' => 'budi.santoso', 'password' => $password]));
        $this->assertNull($this->classA->fresh()->homeroom_teacher_id, 'Guru BK tidak memegang kelas, jadi kelasnya dilepas.');
        $this->assertSame(0, User::firstWhere('username', 'budi.santoso')->dutySchedules()->count(), 'Guru BK juga tidak piket.');
    }

    public function test_invalid_rows_save_nothing(): void
    {
        $other = User::factory()->waliKelas()->create();
        Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-B', 'homeroom_teacher_id' => $other->id]);

        $result = $this->import([
            ['', 'guru'],
            ['Ayu', 'kepala sekolah'],
            ['Citra', 'guru bk', 'VII-A'],
            ['Dodi', 'guru', 'IX-Z'],
            ['Eka', 'guru', 'VII-B'],
            ['Fajar', 'guru bk', '', 'admin'],
            ['Gita', 'guru bk', '', 'Bukan Username!'],
            ['Hadi', 'guru bk', '', '', 'pendek'],
            ['Ivan', 'guru bk', '', '', '', 'Senin'],
            ['Joko', 'guru', '', '', '', 'Sabtu'],
        ]);

        $this->assertCount(10, $result['errors']);
        $this->assertStringContainsString('Baris 2: Nama kosong', $result['errors'][0]);
        $this->assertStringContainsString('Peran "kepala sekolah"', $result['errors'][1]);
        $this->assertStringContainsString('Kolom Wali Kelas hanya untuk peran guru', $result['errors'][2]);
        $this->assertStringContainsString('sudah punya wali kelas lain', $result['errors'][4]);
        $this->assertStringContainsString('milik akun admin', $result['errors'][5]);
        $this->assertStringContainsString('Kolom Hari Piket hanya untuk peran guru', $result['errors'][8]);
        $this->assertStringContainsString('Hari Piket "Sabtu"', $result['errors'][9]);
        $this->assertSame(2, User::count());
    }

    public function test_upload_then_download_the_accounts_to_hand_out(): void
    {
        $csv = "Nama,Peran,Wali Kelas,Username,Password,Hari Piket\nBudi Santoso,guru,VII-A,,,Kamis\n";

        $this->actingAs($this->admin)
            ->post('/pengguna/import', ['file' => UploadedFile::fake()->createWithContent('guru.csv', $csv)])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success', 'Import selesai: 1 akun baru, 0 akun diperbarui. Unduh daftar akun untuk dibagikan ke guru.');

        $this->actingAs($this->admin)->get('/pengguna')->assertInertia(fn (Assert $page) => $page->where('importedAccounts', 1));

        Excel::fake();
        $this->actingAs($this->admin)->get('/pengguna/import/akun')->assertOk();
        Excel::assertDownloaded('daftar-akun-guru.xlsx', fn (UserAccountsExport $export): bool => $export->array()[5][4] === 'budi.santoso' && $export->array()[5][5] === '12345');

        $this->actingAs($this->admin)->delete('/pengguna/import/akun')->assertSessionHas('success');
        $this->actingAs($this->admin)->get('/pengguna')->assertInertia(fn (Assert $page) => $page->where('importedAccounts', 0));
    }

    public function test_template_keeps_numbers_as_text_and_offers_the_roles(): void
    {
        $response = $this->actingAs($this->admin)->get('/pengguna/import/template')->assertOk()->assertDownload('template-import-guru.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, $response->streamedContent() ?: file_get_contents($response->baseResponse->getFile()->getPathname()));
        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertSame(['Nama', 'Peran', 'Wali Kelas', 'Username', 'Password', 'Hari Piket'], $sheet->rangeToArray('A1:F1')[0]);
        $this->assertSame('"guru,guru bk"', $sheet->getCell('B50')->getDataValidation()->getFormula1());
        $this->assertSame('@', $sheet->getStyle('E50')->getNumberFormat()->getFormatCode());
    }

    public function test_only_admins_import_users(): void
    {
        $this->actingAs(User::factory()->guruPiket()->create())->get('/pengguna/import')->assertForbidden();
    }
}
