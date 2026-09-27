<?php

namespace App\Actions;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use Illuminate\Support\Facades\DB;

/**
 * Ends a class's first-period scan: every active student without a mark for
 * the day becomes Alpa, which the wali kelas can later correct.
 */
class CloseClassSession
{
    /**
     * @return int Number of students marked Alpa.
     */
    public function handle(AttendanceSession $session): int
    {
        if ($session->isClosed()) {
            return 0;
        }

        return DB::transaction(function () use ($session): int {
            $markedIds = Attendance::query()
                ->whereDate('date', $session->date)
                ->whereIn('student_id', $session->classroom->students()->select('students.id'))
                ->pluck('student_id');

            $missingIds = $session->classroom->students()
                ->where('status', StudentStatus::Active)
                ->whereNotIn('students.id', $markedIds)
                ->pluck('students.id');

            $now = now();

            Attendance::query()->insertOrIgnore($missingIds->map(fn (int $studentId): array => [
                'student_id' => $studentId,
                'classroom_id' => $session->classroom_id,
                'date' => $session->date->toDateString(),
                'status' => AttendanceStatus::Absent->value,
                'source' => AttendanceSource::Automatic->value,
                'recorded_at' => $now,
                'attendance_session_id' => $session->id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());

            $session->forceFill(['closed_at' => $now])->save();

            return $missingIds->count();
        });
    }
}
