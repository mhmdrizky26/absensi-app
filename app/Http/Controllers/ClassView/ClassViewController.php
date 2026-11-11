<?php

namespace App\Http\Controllers\ClassView;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceDay;
use App\Models\Classroom;
use App\Support\AttendanceWindow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Subject teachers type a class code ("7A") on the code screen to see,
 * read-only, who in that room is present today. No account needed; the code
 * only shows today.
 */
class ClassViewController extends Controller
{
    /**
     * Where the opened class is remembered; set by CodeLoginController.
     */
    public const SESSION_KEY = 'class_view';

    /**
     * Today's marks of the students who sit in this room: its own students,
     * and students of a class without a room who study here. For a class
     * without a room, its own students wherever they study.
     */
    public function show(Request $request, AttendanceWindow $window): Response|RedirectResponse
    {
        $classroom = $this->classroomFrom($request);

        if (! $classroom) {
            return redirect()->route('code.login')->with('error', 'Masukkan kode kelas.');
        }

        $placements = DB::table('classroom_student')
            ->join('students', 'students.id', '=', 'classroom_student.student_id')
            ->join('classrooms as home', 'home.id', '=', 'classroom_student.classroom_id')
            ->leftJoin('classrooms as study', 'study.id', '=', 'classroom_student.study_classroom_id')
            ->where('classroom_student.academic_year_id', $classroom->academic_year_id)
            ->where('students.status', StudentStatus::Active->value)
            ->where(fn ($query) => $classroom->has_room
                ? $query
                    ->where(fn ($query) => $query->where('classroom_student.classroom_id', $classroom->id)->whereNull('classroom_student.study_classroom_id'))
                    ->orWhere('classroom_student.study_classroom_id', $classroom->id)
                : $query->where('classroom_student.classroom_id', $classroom->id))
            ->orderBy('students.name')
            ->get(['students.id', 'students.name', 'students.nis', 'home.name as home', 'study.name as study']);

        $marks = Attendance::query()
            ->whereDate('date', today())
            ->whereIn('student_id', $placements->pluck('id'))
            ->get(['student_id', 'status', 'recorded_at'])
            ->keyBy('student_id');

        $today = today();

        return Inertia::render('ClassView/Show', [
            'classroom' => ['name' => $classroom->name, 'hasRoom' => $classroom->has_room],
            'date' => $today->toDateString(),
            'isSchoolDay' => $window->isSchoolDay($today),
            'isFinalized' => AttendanceDay::forDate($today) !== null,
            'students' => $placements->map(function (object $student) use ($classroom, $marks): array {
                $mark = $marks->get($student->id);

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'nis' => $student->nis,
                    'from' => $student->home !== $classroom->name ? $student->home : null,
                    'studyIn' => ! $classroom->has_room ? $student->study : null,
                    'status' => $mark?->status->value,
                    'statusLabel' => $mark?->status->label(),
                    'recordedAt' => $mark?->recorded_at->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    /**
     * Back to the code screen, to look at another class.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('code.login');
    }

    /**
     * The class this browser opened, as long as its code was not changed.
     */
    private function classroomFrom(Request $request): ?Classroom
    {
        $opened = $request->session()->get(self::SESSION_KEY);

        if (! is_array($opened)) {
            return null;
        }

        $classroom = Classroom::query()
            ->where('academic_year_id', AcademicYear::active()?->id)
            ->find($opened['id'] ?? null);

        if (! $classroom || $classroom->view_code !== ($opened['code'] ?? null)) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return $classroom;
    }
}
