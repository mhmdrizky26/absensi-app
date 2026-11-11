<?php

namespace App\Http\Controllers\Admin;

use App\Exports\StudentsExport;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Master data downloads for the admin, in the same columns as the import.
 */
class DataExportController extends Controller
{
    /**
     * Every active student, one sheet per class of the active year.
     */
    public function students(): BinaryFileResponse
    {
        $academicYear = AcademicYear::active();
        $suffix = $academicYear ? '-'.str($academicYear->name)->slug() : '';

        return Excel::download(new StudentsExport($academicYear), "data-siswa{$suffix}.xlsx");
    }
}
