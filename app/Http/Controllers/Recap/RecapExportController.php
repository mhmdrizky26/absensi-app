<?php

namespace App\Http\Controllers\Recap;

use App\Enums\StudentStatus;
use App\Exports\MonthlyRecapExport;
use App\Exports\SchoolRecapExport;
use App\Exports\SemesterRecapExport;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Support\AttendanceReport;
use App\Support\RecapScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RecapExportController extends Controller
{
    public function monthly(Request $request, RecapScope $scope, AttendanceReport $report): BinaryFileResponse
    {
        $classroom = $scope->classroomFor($request->user(), $request->filled('classroom') ? $request->integer('classroom') : null);
        abort_if($classroom === null, 404);
        $month = MonthlyRecapController::parseMonth($request->string('bulan')->value());

        return Excel::download(
            new MonthlyRecapExport($classroom, $month, $report->monthGrid($classroom, $month), config('app.school_name')),
            "rekap-{$classroom->name}-{$month->format('Y-m')}.xlsx",
        );
    }

    public function semester(Request $request, RecapScope $scope, AttendanceReport $report): BinaryFileResponse
    {
        $academicYear = AcademicYear::active();
        $classroom = $scope->classroomFor($request->user(), $request->filled('classroom') ? $request->integer('classroom') : null);
        abort_if($classroom === null || $academicYear === null, 404);

        $semester = SemesterRecapController::semesterFrom($request, $academicYear);
        [$from, $to] = $academicYear->semesterRange($semester);
        $label = 'Semester '.ucfirst($semester).' '.$academicYear->name;

        return Excel::download(
            new SemesterRecapExport($classroom, $label, $report->studentTotals($classroom, $from, $to), config('app.school_name')),
            "rapor-kehadiran-{$classroom->name}-{$semester}.xlsx",
        );
    }

    public function school(Request $request, AttendanceReport $report): BinaryFileResponse
    {
        $academicYear = AcademicYear::active();
        [, $from, $to, $label] = SchoolRecapController::periodFrom($request, $academicYear);

        $classrooms = $academicYear
            ? $academicYear->classrooms()
                ->with('homeroomTeacher:id,name')
                ->withCount(['students' => fn (Builder $query) => $query->where('status', StudentStatus::Active)])
                ->orderBy('grade')
                ->orderBy('name')
                ->get()
            : collect();

        $rows = $report->classroomTotals($classrooms, $from, $to);
        $totals = $report->sum($rows);

        return Excel::download(
            new SchoolRecapExport($label, $rows, $totals, $report->rate($totals), config('app.school_name')),
            'rekap-sekolah-'.str($label)->slug().'.xlsx',
        );
    }
}
