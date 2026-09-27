<?php

namespace Tests\Feature\Admin;

use App\Actions\ImportStudents;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $academicYear;

    private Classroom $classA;

    private Classroom $classB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->academicYear = AcademicYear::factory()->active()->create(['name' => '2026/2027']);
        $this->classA = Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-A']);
        $this->classB = Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-B']);
    }

    /**
     * @param  list<array{0: mixed, 1: mixed, 2: mixed, 3: mixed, 4: mixed}>  $rows
     * @return array{created: int, updated: int, errors: list<string>}
     */
    private function import(array $rows): array
    {
        $keyed = array_map(fn (array $row): array => array_combine(['nis', 'nisn', 'nama', 'jk', 'kelas'], $row), $rows);

        return app(ImportStudents::class)->handle($keyed, $this->academicYear);
    }

    public function test_uploaded_csv_creates_students_and_places_them(): void
    {
        $csv = "NIS,NISN,Nama,JK,Kelas\n260001,0011111111,Ayu Lestari,P,VII-A\n260002,,Bayu Saputra,L,7b\n";

        $this->actingAs(User::factory()->admin()->create())
            ->post('/siswa/import', ['file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv)])
            ->assertRedirect(route('students.index'))
            ->assertSessionHas('success', 'Import selesai: 2 siswa baru, 0 siswa diperbarui.');

        $this->assertTrue(Student::firstWhere('nis', '260001')->classroomIn($this->academicYear)->is($this->classA));
        $this->assertTrue(Student::firstWhere('nis', '260002')->classroomIn($this->academicYear)->is($this->classB));
    }

    public function test_uploaded_xlsx_with_numeric_cells_is_imported(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['NIS', 'NISN', 'Nama Siswa', 'Jenis Kelamin', 'Rombel'],
            [260001, 12345678, 'Ayu Lestari', 'Perempuan', 'VII A'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'siswa').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/siswa/import', ['file' => new UploadedFile($path, 'siswa.xlsx', null, null, true)])
            ->assertSessionHas('success', 'Import selesai: 1 siswa baru, 0 siswa diperbarui.');

        $student = Student::firstWhere('nis', '260001');
        $this->assertSame('0012345678', $student->nisn);
        $this->assertTrue($student->classroomIn($this->academicYear)->is($this->classA));
    }

    public function test_existing_students_are_updated_and_moved(): void
    {
        $student = Student::factory()->create(['nis' => '260001', 'name' => 'Nama Lama']);
        $student->placeIn($this->classA, $this->academicYear);

        $result = $this->import([['260001', '', 'Nama Baru', 'L', 'VII-B']]);

        $this->assertSame(['created' => 0, 'updated' => 1, 'errors' => []], $result);
        $this->assertSame('Nama Baru', $student->fresh()->name);
        $this->assertTrue($student->classroomIn($this->academicYear)->is($this->classB));
        $this->assertSame(1, $student->classrooms()->count());
    }

    public function test_any_invalid_row_saves_nothing(): void
    {
        $result = $this->import([
            ['260001', '', 'Ayu Lestari', 'P', 'VII-A'],
            ['', '', 'Tanpa NIS', 'X', 'IX-Z'],
        ]);

        $this->assertCount(1, $result['errors']);
        $this->assertStringStartsWith('Baris 3: NIS kosong; JK "X" harus L atau P; Kelas "IX-Z" belum ada', $result['errors'][0]);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_duplicate_nis_in_the_file_is_reported(): void
    {
        $result = $this->import([
            ['260001', '', 'Ayu', 'P', 'VII-A'],
            ['260001', '', 'Ayu Lagi', 'P', 'VII-A'],
        ]);

        $this->assertSame(['Baris 3: NIS 260001 sudah muncul di baris 2.'], $result['errors']);
    }

    public function test_nisn_that_lost_its_leading_zeros_is_restored(): void
    {
        $this->import([[260001.0, 12345678, 'Ayu', 'Perempuan', 'Kelas 7 A']]);

        $student = Student::firstWhere('nis', '260001');
        $this->assertSame('0012345678', $student->nisn);
        $this->assertTrue($student->classroomIn($this->academicYear)->is($this->classA));
    }

    public function test_nisn_already_owned_by_another_student_is_rejected(): void
    {
        Student::factory()->create(['nis' => '250001', 'nisn' => '0099999999']);

        $result = $this->import([['260001', '0099999999', 'Ayu', 'P', 'VII-A']]);

        $this->assertSame(['Baris 2: NISN 0099999999 sudah dipakai siswa dengan NIS 250001.'], $result['errors']);
    }

    public function test_missing_required_columns_are_reported(): void
    {
        $result = app(ImportStudents::class)->handle([['nis' => '1', 'nama' => 'Ayu']], $this->academicYear);

        $this->assertSame([
            'Kolom "JK" tidak ditemukan. Gunakan template yang disediakan.',
            'Kolom "Kelas" tidak ditemukan. Gunakan template yang disediakan.',
        ], $result['errors']);
    }

    public function test_failed_upload_flashes_the_errors(): void
    {
        $csv = "NIS,Nama,JK,Kelas\n260001,Ayu,P,IX-Z\n";

        $this->actingAs(User::factory()->admin()->create())
            ->post('/siswa/import', ['file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv)])
            ->assertSessionHas('importErrors', ['Baris 2: Kelas "IX-Z" belum ada di tahun ajaran 2026/2027.']);
    }

    public function test_template_can_be_downloaded(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/siswa/import/template')
            ->assertOk()
            ->assertDownload('template-import-siswa.xlsx');
    }
}
