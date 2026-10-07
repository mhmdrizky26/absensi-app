<?php

namespace App\Http\Controllers\Recap;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use App\Support\AttendanceReport;
use App\Support\AttendanceRisk;
use App\Support\AttendanceWindow;
use App\Support\RecapScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EarlyWarningController extends Controller
{
    /**
     * Students whose attendance this semester matches one of the early
     * warning rules. A wali kelas sees their own class only.
     */
    public function index(Request $request, RecapScope $scope, AttendanceRisk $risk): Response
    {
        $user = $request->user();
        $academicYear = AcademicYear::active();
        $classrooms = $scope->classroomsFor($user);
        $selectedId = $user->hasRole(Role::WaliKelas) ? null : ($request->integer('classroom') ?: null);
        $target = $selectedId ? new EloquentCollection([$scope->classroomFor($user, $selectedId)]) : $classrooms;

        $students = [];
        $period = null;

        if ($academicYear && $target->isNotEmpty()) {
            [$from, $to, $semester] = $academicYear->semesterSoFar(today());
            $students = $risk->flagged($target, $from, $to);
            $period = ['label' => "Semester {$semester} {$academicYear->name}", 'from' => $from->toDateString(), 'to' => $to->toDateString()];
        }

        return Inertia::render('Recap/EarlyWarning', [
            'students' => $students,
            'rules' => AttendanceRisk::rules(),
            'period' => $period,
            'classrooms' => $user->hasRole(Role::WaliKelas) ? [] : $classrooms->map(fn (Classroom $classroom): array => $classroom->only('id', 'name'))->values(),
            'filters' => ['classroom' => $selectedId],
            'homeroom' => $user->hasRole(Role::WaliKelas) ? $classrooms->first()?->name : null,
            'isHomeroomTeacher' => $user->hasRole(Role::WaliKelas),
        ]);
    }

    /**
     * One student's recap for the semester so far, for the modal opened from
     * the list: totals, every school day's mark grouped by month, and notes.
     */
    public function show(Request $request, Student $student, RecapScope $scope, AttendanceWindow $window, AttendanceReport $report): JsonResponse
    {
        $academicYear = AcademicYear::active();
        abort_if($academicYear === null, 404);

        $classroom = $student->classroomIn($academicYear);
        abort_unless($classroom && $scope->canCorrect($request->user(), $classroom), 403, 'Anda tidak punya akses ke siswa ini.');

        [$from, $to, $semester] = $academicYear->semesterSoFar(today());

        $marks = Attendance::query()
            ->where('student_id', $student->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get()
            ->keyBy(fn (Attendance $attendance): string => $attendance->date->toDateString());

        $counts = array_fill_keys(AttendanceReport::STATUSES, 0);

        foreach ($marks as $mark) {
            $counts[$mark->status->value]++;
        }

        $months = collect($window->schoolDaysBetween($from, $to))
            ->groupBy(fn (CarbonImmutable $day): string => $day->format('Y-m'))
            ->map(function ($days) use ($marks, $report): array {
                $monthCounts = array_fill_keys(AttendanceReport::STATUSES, 0);
                $cells = $days->map(function (CarbonImmutable $day) use ($marks, &$monthCounts): array {
                    $status = $marks->get($day->toDateString())?->status->value;

                    if ($status) {
                        $monthCounts[$status]++;
                    }

                    return ['date' => $day->toDateString(), 'day' => $day->day, 'weekday' => $day->translatedFormat('D'), 'status' => $status];
                })->values()->all();

                return ['label' => $days->first()->translatedFormat('F Y'), 'days' => $cells, 'counts' => $monthCounts, 'rate' => $report->rate($monthCounts)];
            })
            ->values();

        return response()->json([
            'name' => $student->name,
            'nis' => $student->nis,
            'classroom' => ['id' => $classroom->id, 'name' => $classroom->name],
            'period' => "Semester {$semester} {$academicYear->name}",
            'counts' => $counts,
            'rate' => $report->rate($counts),
            'months' => $months,
            'notes' => $marks
                ->filter(fn (Attendance $attendance): bool => $attendance->status->value !== 'H' && filled($attendance->note))
                ->map(fn (Attendance $attendance): array => [
                    'date' => $attendance->date->toDateString(),
                    'status' => $attendance->status->value,
                    'statusLabel' => $attendance->status->label(),
                    'note' => $attendance->note,
                ])
                ->values(),
        ]);
    }
}
