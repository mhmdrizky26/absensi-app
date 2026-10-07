<?php

namespace App\Http\Controllers\Duty;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Http\JsonResponse;

class MonitorClassroomController extends Controller
{
    /**
     * Every active student of one class with today's mark, for the class
     * detail opened from the monitor.
     */
    public function show(Classroom $classroom): JsonResponse
    {
        $students = $classroom->students()
            ->where('status', StudentStatus::Active)
            ->orderBy('name')
            ->get(['students.id', 'students.name', 'students.nis']);

        $marks = Attendance::query()
            ->with('recorder:id,name')
            ->whereDate('date', today())
            ->whereIn('student_id', $students->modelKeys())
            ->get()
            ->keyBy('student_id');

        return response()->json([
            'name' => $classroom->name,
            'homeroomTeacher' => $classroom->homeroomTeacher?->name,
            'students' => $students->map(function (Student $student) use ($marks): array {
                $mark = $marks->get($student->id);

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'nis' => $student->nis,
                    'status' => $mark?->status->value,
                    'recordedAt' => $mark?->recorded_at->toIso8601String(),
                    'note' => $mark?->note,
                    'recorder' => $mark?->recorder?->name,
                    'attachmentId' => $mark?->attachment_path ? $mark->id : null,
                ];
            }),
        ]);
    }
}
