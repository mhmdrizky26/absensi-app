<?php

namespace App\Http\Controllers\ClassView;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceDay;
use App\Models\Classroom;
use App\Support\AttendanceWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every logged-in teacher, subject teachers included, sees read-only who is
 * present in any class today. Only today: recaps stay with the wali kelas.
 */
class ClassViewController extends Controller
{
    /**
     * The class picker and, for the chosen class (the wali kelas's own class
     * by default), today's marks of the students who sit in that room: its
     * own students, and students of a class without a room who study here.
     * For a class without a room, its own students wherever they study.
     */
    public function show(Request $request, AttendanceWindow $window): Response
    {
        $academicYear = AcademicYear::active();
        $classrooms = $academicYear ? $academicYear->classrooms()->orderBy('grade')->orderBy('name')->get(['id', 'academic_year_id', 'grade', 'name', 'has_room']) : collect();
        $selectedId = $request->integer('kelas') ?: $request->user()->currentHomeroom()?->id;
        $classroom = $classrooms->firstWhere('id', $selectedId);
        $today = today();

        return Inertia::render('ClassView/Show', [
            'classrooms' => $classrooms->map(fn (Classroom $classroom): array => $classroom->only('id', 'grade', 'name'))->values(),
            'classroom' => $classroom ? ['id' => $classroom->id, 'name' => $classroom->name, 'hasRoom' => $classroom->has_room] : null,
            'date' => $today->toDateString(),
            'isSchoolDay' => $window->isSchoolDay($today),
            'isFinalized' => AttendanceDay::forDate($today) !== null,
            'students' => $classroom ? $this->studentsIn($classroom) : [],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function studentsIn(Classroom $classroom): array
    {
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

        return $placements->map(function (object $student) use ($classroom, $marks): array {
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
        })->values()->all();
    }
}
