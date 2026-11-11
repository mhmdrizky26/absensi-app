<?php

namespace App\Http\Controllers\Recap;

use App\Enums\StudentStatus;
use App\Exports\MonthlyRecapExport;
use App\Exports\RecapWorkbook;
use App\Exports\SchoolRecapExport;
use App\Exports\SemesterRecapExport;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Support\AttendanceReport;
use App\Support\RecapScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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

    /**
     * One class's whole semester in one file: the semester totals for the
     * report card, then one sheet per month with the daily marks. A wali
     * kelas only gets their own class.
     */
    public function semesterWorkbook(Request $request, RecapScope $scope, AttendanceReport $report): BinaryFileResponse
    {
        $academicYear = AcademicYear::active();
        $classroom = $scope->classroomFor($request->user(), $request->filled('classroom') ? $request->integer('classroom') : null);
        abort_if($classroom === null || $academicYear === null, 404);
        $classroom->loadMissing('homeroomTeacher:id,name');

        $semester = SemesterRecapController::semesterFrom($request, $academicYear);
        [$from, $to] = $academicYear->semesterRange($semester);
        $label = 'Semester '.ucfirst($semester).' '.$academicYear->name;
        $schoolName = (string) config('app.school_name');

        $sheets = [new SemesterRecapExport($classroom, $label, $report->studentTotals($classroom, $from, $to), $schoolName, 'Rekap semester')];

        foreach (self::monthsBetween($from, $to->min(CarbonImmutable::today())) as $month) {
            $sheets[] = new MonthlyRecapExport($classroom, $month, $report->monthGrid($classroom, $month), $schoolName, $month->translatedFormat('F Y'));
        }

        return Excel::download(new RecapWorkbook($sheets), "rekap-semester-{$classroom->name}-{$semester}.xlsx");
    }

    public function school(Request $request, AttendanceReport $report): BinaryFileResponse
    {
        $academicYear = AcademicYear::active();
        [, $from, $to, $label] = SchoolRecapController::periodFrom($request, $academicYear);

        $rows = $report->classroomTotals($this->schoolClassrooms($academicYear), $from, $to);
        $totals = $report->sum($rows);

        return Excel::download(
            new SchoolRecapExport($label, $rows, $totals, $report->rate($totals), config('app.school_name')),
            'rekap-sekolah-'.str($label)->slug().'.xlsx',
        );
    }

    /**
     * Every class of the school in one file for a month or a semester: a
     * summary sheet, then one sheet per class named after it. A month shows
     * the daily marks; a semester the totals per student.
     */
    public function schoolWorkbook(Request $request, AttendanceReport $report): BinaryFileResponse
    {
        $academicYear = AcademicYear::active();
        [$period, $from, $to, $label] = SchoolRecapController::periodFrom($request, $academicYear);
        $classrooms = $this->schoolClassrooms($academicYear);
        $schoolName = (string) config('app.school_name');

        $rows = $report->classroomTotals($classrooms, $from, $to);
        $totals = $report->sum($rows);
        $sheets = [new SchoolRecapExport($label, $rows, $totals, $report->rate($totals), $schoolName, 'Ringkasan')];

        foreach ($classrooms as $classroom) {
            $sheets[] = $period === 'semester'
                ? new SemesterRecapExport($classroom, $label, $report->studentTotals($classroom, $from, $to), $schoolName, $classroom->name)
                : new MonthlyRecapExport($classroom, $from, $report->monthGrid($classroom, $from), $schoolName, $classroom->name);
        }

        return Excel::download(new RecapWorkbook($sheets), 'rekap-semua-kelas-'.str($label)->slug().'.xlsx');
    }

    /**
     * @return Collection<int, Classroom>
     */
    private function schoolClassrooms(?AcademicYear $academicYear): Collection
    {
        return $academicYear
            ? $academicYear->classrooms()
                ->with('homeroomTeacher:id,name')
                ->withCount(['students' => fn (Builder $query) => $query->where('status', StudentStatus::Active)])
                ->orderBy('grade')
                ->orderBy('name')
                ->get()
            : new Collection;
    }

    /**
     * The first day of every month from $from to $to.
     *
     * @return list<CarbonImmutable>
     */
    private static function monthsBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $months = [];

        for ($month = $from->startOfMonth(); $month->lessThanOrEqualTo($to); $month = $month->addMonth()) {
            $months[] = $month;
        }

        return $months;
    }
}
