<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ImportStudents;
use App\Exports\StudentTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\StudentsImport;
use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentImportController extends Controller
{
    public function create(): Response
    {
        $academicYear = AcademicYear::active();

        return Inertia::render('Admin/Students/Import', [
            'academicYear' => $academicYear?->only('id', 'name'),
            'classroomNames' => $academicYear
                ? $academicYear->classrooms()->orderBy('grade')->orderBy('name')->pluck('name')
                : [],
        ]);
    }

    public function store(Request $request, ImportStudents $importStudents): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $academicYear = AcademicYear::active();

        if (! $academicYear) {
            return back()->with('error', 'Aktifkan tahun ajaran terlebih dahulu.');
        }

        $reader = new StudentsImport;
        Excel::import($reader, $request->file('file'));

        $result = $importStudents->handle($reader->rows, $academicYear);

        if ($result['errors'] !== []) {
            return back()
                ->with('error', 'Import dibatalkan, tidak ada data yang disimpan. Perbaiki baris berikut lalu unggah ulang.')
                ->with('importErrors', $result['errors']);
        }

        return redirect()->route('students.index')->with(
            'success',
            "Import selesai: {$result['created']} siswa baru, {$result['updated']} siswa diperbarui.",
        );
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new StudentTemplateExport, 'template-import-siswa.xlsx');
    }
}
