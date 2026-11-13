<?php

namespace App\Http\Controllers\Duty;

use App\Actions\FinalizeAttendanceDay;
use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceDay;
use App\Models\Classroom;
use App\Models\GateStation;
use App\Support\AttendanceWindow;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class MonitorController extends Controller
{
    /**
     * Today's attendance across the school: how many of each mark per class,
     * who came late, whether the gate tablets are online, and whether the
     * day has been finished.
     */
    public function index(AttendanceWindow $window, FinalizeAttendanceDay $finalize): Response
    {
        $today = today();
        $academicYear = AcademicYear::active();

        $classrooms = $academicYear
            ? $academicYear->classrooms()
                ->with('homeroomTeacher:id,name')
                ->withCount(['students as active_students_count' => fn (Builder $query) => $query->where('status', StudentStatus::Active)])
                ->orderBy('grade')
                ->orderBy('name')
                ->get()
            : collect();

        $countsByClassroom = Attendance::query()
            ->whereDate('date', $today)
            ->whereIn('classroom_id', $classrooms->pluck('id'))
            ->selectRaw('classroom_id, status, count(*) as total')
            ->groupBy('classroom_id', 'status')
            ->get()
            ->groupBy('classroom_id');

        $rows = $classrooms->map(function (Classroom $classroom) use ($countsByClassroom): array {
            $counts = array_fill_keys(array_column(AttendanceStatus::cases(), 'value'), 0);

            foreach ($countsByClassroom->get($classroom->id, collect()) as $count) {
                $counts[$count->status->value] = (int) $count->total;
            }

            return [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'homeroomTeacher' => $classroom->homeroomTeacher?->name,
                'total' => $classroom->active_students_count,
                'counts' => $counts,
            ];
        });

        $late = Attendance::query()
            ->with(['student:id,name,nis', 'classroom:id,name'])
            ->whereDate('attendances.date', $today)
            ->where('attendances.status', AttendanceStatus::Late)
            ->join('classrooms', 'classrooms.id', '=', 'attendances.classroom_id')
            ->join('students', 'students.id', '=', 'attendances.student_id')
            ->orderBy('classrooms.grade')
            ->orderBy('classrooms.name')
            ->orderBy('students.name')
            ->limit(300)
            ->get(['attendances.*'])
            ->map(fn (Attendance $attendance): array => [
                'id' => $attendance->id,
                'studentId' => $attendance->student_id,
                'name' => $attendance->student->name,
                'nis' => $attendance->student->nis,
                'classroom' => $attendance->classroom->name,
                'recordedAt' => $attendance->recorded_at->toIso8601String(),
                'note' => $attendance->note,
            ]);

        $holiday = $window->holidayOn($today);
        $finalizedDay = AttendanceDay::query()->with('finalizer:id,name')->whereDate('date', $today)->first();

        return Inertia::render('Duty/Monitor', [
            'date' => $today->toDateString(),
            'window' => [
                'opensAt' => $window->opensAt($today)->toIso8601String(),
                'closesAt' => $window->closesAt($today)->toIso8601String(),
                'isEnforced' => $window->isEnforced(),
                'isSchoolDay' => $window->isSchoolDay($today),
                'holiday' => $holiday?->description,
                'onlineDay' => $window->onlineDayOn($today)?->description,
            ],
            'finalized' => $finalizedDay ? [
                'at' => $finalizedDay->finalized_at->toIso8601String(),
                'by' => $finalizedDay->finalizer?->name,
                'absentCount' => $finalizedDay->absent_count,
            ] : null,
            'missingCount' => $finalizedDay ? 0 : $finalize->missingCount($today),
            'stations' => GateStation::query()
                ->whereNull('revoked_at')
                ->orderByRaw('LENGTH(code), code')
                ->get()
                ->map(fn (GateStation $station): array => [
                    'id' => $station->id,
                    'name' => $station->name,
                    'isOnline' => $station->isOnline(),
                    'pendingScans' => $station->pending_scans,
                ]),
            'classrooms' => $rows,
            'late' => $late,
        ]);
    }
}
