<?php

namespace App\Actions;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The one way staff change a student's mark for a day. Creates or updates
 * the mark and writes an audit log entry of the change.
 */
class SetAttendanceStatus
{
    /**
     * @param  array{note?: ?string, attachment_path?: ?string, dispensation_id?: ?int}  $details
     *
     * @throws InvalidArgumentException when the student has no class this academic year
     */
    public function handle(
        Student $student,
        CarbonInterface $date,
        AttendanceStatus $status,
        AttendanceSource $source,
        ?User $user,
        array $details = [],
    ): Attendance {
        return DB::transaction(function () use ($student, $date, $status, $source, $user, $details): Attendance {
            $attendance = Attendance::query()
                ->where('student_id', $student->id)
                ->whereDate('date', $date->toDateString())
                ->lockForUpdate()
                ->first();

            $previousStatus = $attendance?->status;

            $attributes = [
                'status' => $status,
                'source' => $source,
                'recorded_at' => now(),
                'recorded_by' => $user?->id,
                ...array_intersect_key($details, array_flip(['note', 'attachment_path', 'dispensation_id'])),
            ];

            if ($attendance) {
                $attendance->update($attributes);
            } else {
                $classroom = AcademicYear::active() ? $student->classroomIn(AcademicYear::active()) : null;

                if (! $classroom) {
                    throw new InvalidArgumentException("{$student->name} belum punya kelas di tahun ajaran aktif.");
                }

                $attendance = Attendance::create([
                    'student_id' => $student->id,
                    'classroom_id' => $classroom->id,
                    'date' => $date->toDateString(),
                    ...$attributes,
                ]);
            }

            if ($previousStatus !== $status) {
                $attendance->logs()->create([
                    'from_status' => $previousStatus,
                    'to_status' => $status,
                    'source' => $source,
                    'changed_by' => $user?->id,
                    'reason' => $details['note'] ?? null,
                ]);
            }

            return $attendance;
        });
    }
}
