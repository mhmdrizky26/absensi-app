<?php

namespace App\Http\Controllers\Recap;

use App\Actions\SetAttendanceStatus;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recap\AttendanceCorrectionRequest;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class AttendanceCorrectionController extends Controller
{
    /**
     * Change one student's mark for one day, from the recap grid.
     */
    public function store(AttendanceCorrectionRequest $request, SetAttendanceStatus $setAttendanceStatus): RedirectResponse
    {
        $student = Student::findOrFail($request->integer('student_id'));
        $date = Carbon::parse($request->validated('date'));
        $status = AttendanceStatus::from($request->validated('status'));

        $setAttendanceStatus->handle($student, $date, $status, AttendanceSource::Staff, $request->user(), [
            'note' => $request->validated('reason'),
        ]);

        return back()->with('success', "{$student->name} pada {$date->translatedFormat('j F')} diubah menjadi {$status->label()}.");
    }
}
