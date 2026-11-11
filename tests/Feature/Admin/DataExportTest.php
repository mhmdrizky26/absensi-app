<?php

namespace Tests\Feature\Admin;

use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GuardianContact;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class DataExportTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $academicYear;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->academicYear = AcademicYear::factory()->active()->create(['name' => '2026/2027']);
        $this->admin = User::factory()->admin()->create(['name' => 'Administrator']);
    }

    private function download(string $url, string $filename): Spreadsheet
    {
        $response = $this->actingAs($this->admin)->get($url)->assertOk()->assertDownload($filename);

        /** @var BinaryFileResponse $file */
        $file = $response->baseResponse;

        return IOFactory::load($file->getFile()->getPathname());
    }

    public function test_students_come_out_one_sheet_per_class_with_leading_zeros_kept(): void
    {
        $classA = Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-A']);
        Classroom::factory()->for($this->academicYear)->create(['grade' => 7, 'name' => 'VII-B']);
        $roomless = Classroom::factory()->for($this->academicYear)->create(['grade' => 8, 'name' => 'VIII-K', 'has_room' => false]);

        $ayu = Student::factory()->create(['nis' => '0260001', 'nisn' => '0012345678', 'name' => 'Ayu']);
        $ayu->placeIn($classA, $this->academicYear);
        GuardianContact::query()->create(['student_id' => $ayu->id, 'name' => 'Bu Sari', 'relation' => 'ibu', 'phone' => '6281234567890', 'consented_at' => now()]);
        $budi = Student::factory()->create(['name' => 'Budi']);
        $budi->placeIn($roomless, $this->academicYear);
        DB::table('classroom_student')->where('student_id', $budi->id)->update(['study_classroom_id' => $classA->id]);
        Student::factory()->create(['name' => 'Cici']);
        Student::factory()->create(['name' => 'Dodi', 'status' => StudentStatus::Graduated])->placeIn($classA, $this->academicYear);

        $book = $this->download('/siswa/ekspor', 'data-siswa-20262027.xlsx');

        $this->assertSame(['VII-A', 'VII-B', 'VIII-K', 'Tanpa kelas'], $book->getSheetNames());

        $sheetA = $book->getSheetByName('VII-A');
        $this->assertSame(['NIS', 'NISN', 'Nama', 'JK', 'Kelas', 'Nama Orang Tua', 'Hubungan', 'No WA Orang Tua', 'Bersedia Dihubungi', 'Belajar Di'], $sheetA->rangeToArray('A1:J1')[0]);
        $this->assertSame(['0260001', '0012345678', 'Ayu', $ayu->gender->value, 'VII-A', 'Bu Sari', 'ibu', '081234567890', 'Ya', null], $sheetA->rangeToArray('A2:J2')[0]);
        $this->assertNull($sheetA->getCell('C3')->getValue(), 'Siswa yang sudah lulus tidak ikut.');

        $this->assertSame('VII-A', $book->getSheetByName('VIII-K')->getCell('J2')->getValue());
        $this->assertSame('Cici', $book->getSheetByName('Tanpa kelas')->getCell('C2')->getValue());

        $path = tempnam(sys_get_temp_dir(), 'siswa').'.xlsx';
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        $this->actingAs($this->admin)
            ->post('/siswa/import', ['file' => new UploadedFile($path, 'data-siswa.xlsx', null, null, true)])
            ->assertSessionHas('success', 'Import selesai: 0 siswa baru, 1 siswa diperbarui.');
        $this->assertSame('0012345678', $ayu->fresh()->nisn);
    }

    public function test_only_admins_can_export(): void
    {
        $wali = User::factory()->waliKelas()->create();

        $this->actingAs($wali)->get('/siswa/ekspor')->assertForbidden();
    }
}
