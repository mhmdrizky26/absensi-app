<?php

namespace App\Actions;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceDay;
use App\Models\OnlineDay;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Selesaikan absen": every active student of the active year who has no
 * mark for the day becomes Alpa, which the wali kelas can later correct.
 * Marks already there (Hadir, Terlambat, Sakit, Izin, Dispensasi) stay.
 * Finishing a day twice changes nothing. On a sekolah daring day the
 * students without a mark become Hadir instead.
 */
class FinalizeAttendanceDay
{
    public function handle(CarbonInterface $date, ?User $user = null): AttendanceDay
    {
        if (OnlineDay::forDate($date)) {
            $this->finishOnline($date);

            return AttendanceDay::forDate($date);
        }

        return DB::transaction(function () use ($date, $user): AttendanceDay {
            $day = $date->toDateString();
            $existing = AttendanceDay::query()->whereDate('date', $day)->lockForUpdate()->first();

            if ($existing) {
                return $existing;
            }

            $missing = $this->studentsWithoutMark($day);
            $now = now();

            foreach ($missing->chunk(500) as $chunk) {
                DB::table('attendances')->insertOrIgnore($chunk->map(fn (object $student): array => [
                    'student_id' => $student->student_id,
                    'classroom_id' => $student->classroom_id,
                    'date' => $day,
                    'status' => AttendanceStatus::Absent->value,
                    'source' => AttendanceSource::Automatic->value,
                    'recorded_at' => $now,
                    'recorded_by' => $user?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()->all());
            }

            return AttendanceDay::create([
                'date' => $day,
                'finalized_at' => $now,
                'finalized_by' => $user?->id,
                'absent_count' => $missing->count(),
            ]);
        });
    }

    /**
     * Finish a sekolah daring day: every student without a mark, and every
     * automatic Alpa if the day was already finished, becomes Hadir. Marks
     * entered by people (Terlambat, Sakit, Izin, Dispensasi) stay.
     *
     * @return int Number of students marked Hadir.
     */
    public function finishOnline(CarbonInterface $date): int
    {
        return DB::transaction(function () use ($date): int {
            $day = $date->toDateString();
            $existing = AttendanceDay::query()->whereDate('date', $day)->lockForUpdate()->first();
            $now = now();

            $converted = Attendance::query()
                ->whereDate('date', $day)
                ->where('status', AttendanceStatus::Absent)
                ->where('source', AttendanceSource::Automatic)
                ->update(['status' => AttendanceStatus::Present->value, 'source' => AttendanceSource::Online->value, 'recorded_at' => $now, 'recorded_by' => null]);

            $missing = $this->studentsWithoutMark($day);

            foreach ($missing->chunk(500) as $chunk) {
                DB::table('attendances')->insertOrIgnore($chunk->map(fn (object $student): array => [
                    'student_id' => $student->student_id,
                    'classroom_id' => $student->classroom_id,
                    'date' => $day,
                    'status' => AttendanceStatus::Present->value,
                    'source' => AttendanceSource::Online->value,
                    'recorded_at' => $now,
                    'recorded_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()->all());
            }

            if ($existing) {
                $existing->update(['absent_count' => Attendance::query()->whereDate('date', $day)->where('status', AttendanceStatus::Absent)->count()]);
            } else {
                AttendanceDay::create(['date' => $day, 'finalized_at' => $now, 'finalized_by' => null, 'absent_count' => 0]);
            }

            return $converted + $missing->count();
        });
    }

    /**
     * Undo a sekolah daring day: the automatic Hadir marks are removed and the
     * day is open again, so it can be scanned or finished as usual.
     *
     * @return int Number of Hadir marks removed.
     */
    public function cancelOnline(CarbonInterface $date): int
    {
        return DB::transaction(function () use ($date): int {
            $day = $date->toDateString();

            $removed = Attendance::query()
                ->whereDate('date', $day)
                ->where('status', AttendanceStatus::Present)
                ->where('source', AttendanceSource::Online)
                ->delete();

            AttendanceDay::query()->whereDate('date', $day)->delete();

            return $removed;
        });
    }

    /**
     * Undo finishing the day, for practising in demo mode: the Alpa marks it
     * added are removed and the day is open again. Marks changed since then
     * (e.g. corrected to Sakit) stay.
     *
     * @return int Number of Alpa marks removed.
     */
    public function reopen(CarbonInterface $date): int
    {
        return DB::transaction(function () use ($date): int {
            $day = $date->toDateString();

            $removed = Attendance::query()
                ->whereDate('date', $day)
                ->where('status', AttendanceStatus::Absent)
                ->where('source', AttendanceSource::Automatic)
                ->delete();

            AttendanceDay::query()->whereDate('date', $day)->delete();

            return $removed;
        });
    }

    /**
     * How many students would become Alpa if the day were finished now.
     */
    public function missingCount(CarbonInterface $date): int
    {
        return $this->studentsWithoutMark($date->toDateString())->count();
    }

    /**
     * @return Collection<int, object{student_id: int, classroom_id: int}>
     */
    private function studentsWithoutMark(string $day): Collection
    {
        $academicYear = AcademicYear::active();

        if (! $academicYear) {
            return collect();
        }

        return DB::table('classroom_student')
            ->join('students', 'students.id', '=', 'classroom_student.student_id')
            ->where('classroom_student.academic_year_id', $academicYear->id)
            ->where('students.status', StudentStatus::Active->value)
            ->whereNotExists(fn ($query) => $query
                ->from('attendances')
                ->whereColumn('attendances.student_id', 'classroom_student.student_id')
                ->whereDate('attendances.date', $day))
            ->get(['classroom_student.student_id', 'classroom_student.classroom_id']);
    }
}
