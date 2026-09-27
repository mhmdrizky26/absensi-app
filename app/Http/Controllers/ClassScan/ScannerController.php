<?php

namespace App\Http\Controllers\ClassScan;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Support\AttendanceWindow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScannerController extends Controller
{
    /**
     * The phone scanner for an open session, or the day's result once closed.
     */
    public function show(Request $request, AttendanceWindow $window): Response
    {
        /** @var AttendanceSession $attendanceSession */
        $attendanceSession = $request->attributes->get('attendanceSession');
        $classroom = $attendanceSession->classroom;

        $students = $classroom->students()
            ->where('status', StudentStatus::Active)
            ->orderBy('name')
            ->get();

        $marks = Attendance::query()
            ->whereDate('date', $attendanceSession->date)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->mapWithKeys(fn (Attendance $attendance): array => [
                $attendance->student_id => [
                    'status' => $attendance->status->value,
                    'statusLabel' => $attendance->status->label(),
                    'recordedAt' => $attendance->recorded_at->toIso8601String(),
                ],
            ]);

        return Inertia::render($attendanceSession->isClosed() ? 'Scan/Summary' : 'Scan/Scanner', [
            'classroom' => ['name' => $classroom->name],
            'session' => [
                'id' => $attendanceSession->id,
                'date' => $attendanceSession->date->toDateString(),
                'openedAt' => $attendanceSession->opened_at->toIso8601String(),
                'closedAt' => $attendanceSession->closed_at?->toIso8601String(),
                'closesAt' => $window->isEnforced() ? $window->closesAt($attendanceSession->date)->toIso8601String() : null,
            ],
            'roster' => $students->map(fn (Student $student): array => [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
                'hash' => $student->hasActiveCard() ? $student->qrHash() : null,
            ]),
            'marks' => (object) $marks->all(),
        ]);
    }
}
