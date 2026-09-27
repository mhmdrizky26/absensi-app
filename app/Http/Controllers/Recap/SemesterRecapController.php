<?php

namespace App\Http\Controllers\Recap;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Support\AttendanceReport;
use App\Support\RecapScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SemesterRecapController extends Controller
{
    /**
     * Totals per student for a semester, in the S/I/A form used on report cards.
     */
    public function index(Request $request, RecapScope $scope, AttendanceReport $report): Response
    {
        $user = $request->user();
        $academicYear = AcademicYear::active();
        $classroom = $scope->classroomFor($user, $request->filled('classroom') ? $request->integer('classroom') : null);
        $semester = self::semesterFrom($request, $academicYear);

        $rows = [];
        $range = null;

        if ($academicYear && $classroom) {
            [$from, $to] = $academicYear->semesterRange($semester);
            $rows = $report->studentTotals($classroom, $from, $to);
            $range = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
        }

        return Inertia::render('Recap/Semester', [
            'academicYear' => $academicYear?->name,
            'classrooms' => $scope->classroomsFor($user)->map->only('id', 'name')->values(),
            'classroom' => $classroom ? ['id' => $classroom->id, 'name' => $classroom->name, 'homeroomTeacher' => $classroom->homeroomTeacher?->name] : null,
            'semester' => $semester,
            'range' => $range,
            'rows' => $rows,
            'totals' => $report->sum($rows),
        ]);
    }

    public static function semesterFrom(Request $request, ?AcademicYear $academicYear): string
    {
        $semester = $request->string('semester')->value();

        if (in_array($semester, ['ganjil', 'genap'], true)) {
            return $semester;
        }

        return $academicYear?->semesterOn(today()) ?? 'ganjil';
    }
}
