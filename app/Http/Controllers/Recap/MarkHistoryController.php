<?php

namespace App\Http\Controllers\Recap;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Student;
use App\Support\RecapScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarkHistoryController extends Controller
{
    /**
     * How one student's mark for one day came about: the current mark and
     * every change staff made to it.
     */
    public function show(Request $request, RecapScope $scope): JsonResponse
    {
        $request->validate(['student_id' => ['required', 'integer'], 'date' => ['required', 'date']]);

        $student = Student::findOrFail($request->integer('student_id'));
        $classroom = AcademicYear::active() ? $student->classroomIn(AcademicYear::active()) : null;
        abort_unless($classroom && $scope->canCorrect($request->user(), $classroom), 403);

        $attendance = Attendance::query()
            ->with(['recorder:id,name', 'logs.changer:id,name'])
            ->where('student_id', $student->id)
            ->whereDate('date', $request->date('date'))
            ->first();

        return response()->json([
            'mark' => $attendance ? [
                'id' => $attendance->id,
                'status' => $attendance->status->value,
                'statusLabel' => $attendance->status->label(),
                'source' => $attendance->source->label(),
                'recordedAt' => $attendance->recorded_at->toIso8601String(),
                'recorder' => $attendance->recorder?->name,
                'note' => $attendance->note,
                'hasAttachment' => $attendance->attachment_path !== null,
            ] : null,
            'logs' => $attendance
                ? $attendance->logs->sortByDesc('id')->values()->map(fn (AttendanceLog $log): array => [
                    'from' => $log->from_status?->label(),
                    'to' => $log->to_status->label(),
                    'source' => $log->source->label(),
                    'by' => $log->changer?->name,
                    'reason' => $log->reason,
                    'at' => $log->created_at->toIso8601String(),
                ])
                : [],
        ]);
    }
}
