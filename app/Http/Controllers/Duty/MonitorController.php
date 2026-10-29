<?php

namespace App\Http\Controllers\Duty;

use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Support\AttendanceWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MonitorController extends Controller
{
    /**
     * Today's attendance across the school: which classes have scanned,
     * how many of each mark, and who came late.
     */
    public function index(AttendanceWindow $window): Response
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

        $sessions = AttendanceSession::query()
            ->whereDate('date', $today)
            ->whereIn('classroom_id', $classrooms->pluck('id'))
            ->get()
            ->keyBy('classroom_id');

        // Kelas tanpa ruang: siswanya tersebar ke ruang kelas lain.
        $lodgings = DB::table('classroom_student')
            ->join('students', 'students.id', '=', 'classroom_student.student_id')
            ->where('students.status', StudentStatus::Active)
            ->whereIn('classroom_student.classroom_id', $classrooms->where('has_room', false)->pluck('id'))
            ->get(['classroom_student.classroom_id', 'classroom_student.study_classroom_id'])
            ->groupBy('classroom_id');

        $rows = $classrooms->map(function (Classroom $classroom) use ($countsByClassroom, $sessions, $lodgings): array {
            $counts = array_fill_keys(array_column(AttendanceStatus::cases(), 'value'), 0);

            foreach ($countsByClassroom->get($classroom->id, collect()) as $count) {
                $counts[$count->status->value] = (int) $count->total;
            }

            $session = $sessions->get($classroom->id);

            return [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'homeroomTeacher' => $classroom->homeroomTeacher?->name,
                'total' => $classroom->active_students_count,
                'counts' => $counts,
                'hasRoom' => $classroom->has_room,
                'session' => ! $classroom->has_room ? $this->lodgedState($lodgings->get($classroom->id, collect()), $sessions) : match (true) {
                    $session === null => ['state' => 'not_opened'],
                    $session->isClosed() => ['state' => 'closed', 'closedAt' => $session->closed_at->toIso8601String()],
                    default => ['state' => 'open', 'openedAt' => $session->opened_at->toIso8601String()],
                },
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

        return Inertia::render('Duty/Monitor', [
            'date' => $today->toDateString(),
            'window' => [
                'opensAt' => $window->opensAt($today)->toIso8601String(),
                'closesAt' => $window->closesAt($today)->toIso8601String(),
                'isSchoolDay' => $window->isSchoolDay($today),
                'holiday' => $holiday?->description,
            ],
            'classrooms' => $rows,
            'late' => $late,
        ]);
    }

    /**
     * Scan status of a class without a room: in how many rooms its students
     * sit, and how many of those rooms finished today's scan.
     *
     * @param  Collection<int, object>  $lodgings
     * @param  Collection<int, AttendanceSession>  $sessions
     * @return array{state: string, hosts: int, closedHosts: int, unplaced: int}
     */
    private function lodgedState(Collection $lodgings, Collection $sessions): array
    {
        $hosts = $lodgings->pluck('study_classroom_id')->filter()->unique();

        return [
            'state' => 'no_room',
            'hosts' => $hosts->count(),
            'closedHosts' => $hosts->filter(fn (int $id): bool => (bool) $sessions->get($id)?->isClosed())->count(),
            'unplaced' => $lodgings->whereNull('study_classroom_id')->count(),
        ];
    }
}
