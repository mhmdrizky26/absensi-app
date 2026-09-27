<?php

namespace App\Http\Controllers\Recap;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Support\AttendanceReport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchoolRecapController extends Controller
{
    /**
     * Attendance per class across the school, for a month or a semester.
     */
    public function index(Request $request, AttendanceReport $report): Response
    {
        $academicYear = AcademicYear::active();
        [$period, $from, $to, $label] = self::periodFrom($request, $academicYear);

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

        return Inertia::render('Recap/School', [
            'academicYear' => $academicYear?->name,
            'period' => $period,
            'month' => $request->string('bulan')->value() ?: today()->format('Y-m'),
            'semester' => SemesterRecapController::semesterFrom($request, $academicYear),
            'label' => $label,
            'rows' => $rows,
            'totals' => $totals,
            'rate' => $report->rate($totals),
        ]);
    }

    /**
     * @return array{0: string, 1: CarbonImmutable, 2: CarbonImmutable, 3: string}
     */
    public static function periodFrom(Request $request, ?AcademicYear $academicYear): array
    {
        if ($request->string('jenis')->value() === 'semester' && $academicYear) {
            $semester = SemesterRecapController::semesterFrom($request, $academicYear);
            [$from, $to] = $academicYear->semesterRange($semester);

            return ['semester', $from, $to, 'Semester '.ucfirst($semester).' '.$academicYear->name];
        }

        $month = MonthlyRecapController::parseMonth($request->string('bulan')->value());

        return ['bulan', $month, $month->endOfMonth(), $month->translatedFormat('F Y')];
    }
}
