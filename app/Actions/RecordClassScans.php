<?php

namespace App\Actions;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * Saves the marks a class scanner sends, possibly in a batch after the phone
 * was offline. Every mark gets its own result so the phone can clear its queue.
 */
class RecordClassScans
{
    /**
     * @param  list<array{ref: string, method: string, payload?: ?string, student_id?: ?int, scanned_at?: ?string}>  $items
     * @return list<array{ref: string, result: string, studentId?: int, recordedAt?: string, message?: string}>
     */
    public function handle(AttendanceSession $session, array $items): array
    {
        if ($session->isClosed()) {
            return array_map(fn (array $item): array => [
                'ref' => $item['ref'],
                'result' => 'rejected',
                'message' => 'Absensi kelas ini sudah ditutup.',
            ], $items);
        }

        $roster = $session->classroom->students()
            ->where('status', StudentStatus::Active)
            ->get()
            ->keyBy('id');

        return array_map(fn (array $item): array => ['ref' => $item['ref'], ...$this->record($session, $roster, $item)], $items);
    }

    /**
     * @param  Collection<int, Student>  $roster
     * @param  array{method: string, payload?: ?string, student_id?: ?int, scanned_at?: ?string}  $item
     * @return array{result: string, studentId?: int, recordedAt?: string, message?: string}
     */
    private function record(AttendanceSession $session, Collection $roster, array $item): array
    {
        $isScan = $item['method'] === 'scan';

        $student = $isScan
            ? Student::findByQrPayload((string) ($item['payload'] ?? ''))
            : $roster->get((int) ($item['student_id'] ?? 0));

        if (! $student) {
            return ['result' => 'rejected', 'message' => $isScan ? 'Kartu tidak dikenal atau sudah dicabut.' : 'Siswa bukan anggota kelas ini.'];
        }

        if (! $roster->has($student->id)) {
            $otherClassroom = $student->classroomIn($session->classroom->academicYear);

            return [
                'result' => 'rejected',
                'message' => $otherClassroom ? "{$student->name} adalah siswa kelas {$otherClassroom->name}." : "{$student->name} tidak terdaftar di kelas ini.",
            ];
        }

        $recordedAt = $this->clampTime($item['scanned_at'] ?? null, $session);

        $existing = Attendance::query()
            ->where('student_id', $student->id)
            ->whereDate('date', $session->date)
            ->first();

        if ($existing?->status->isAtSchool()) {
            return ['result' => 'duplicate', 'studentId' => $student->id, 'recordedAt' => $existing->recorded_at->toIso8601String()];
        }

        $attributes = [
            'classroom_id' => $session->classroom_id,
            'status' => AttendanceStatus::Present,
            'source' => $isScan ? AttendanceSource::ClassScan : AttendanceSource::ClassManual,
            'recorded_at' => $recordedAt,
            'attendance_session_id' => $session->id,
            'recorded_by' => null,
            'note' => $existing ? "Sebelumnya {$existing->status->label()}, ternyata hadir di kelas." : null,
        ];

        try {
            $existing
                ? $existing->update($attributes)
                : Attendance::create(['student_id' => $student->id, 'date' => $session->date, ...$attributes]);
        } catch (UniqueConstraintViolationException) {
            // Another phone in the same class saved this student a moment ago.
            return ['result' => 'duplicate', 'studentId' => $student->id, 'recordedAt' => $recordedAt->toIso8601String()];
        }

        return ['result' => 'recorded', 'studentId' => $student->id, 'recordedAt' => $recordedAt->toIso8601String()];
    }

    /**
     * Use the phone's scan time (it may have been offline), but never before
     * the session opened or after the server's clock.
     */
    private function clampTime(?string $scannedAt, AttendanceSession $session): CarbonImmutable
    {
        $now = CarbonImmutable::now();

        try {
            $time = $scannedAt ? CarbonImmutable::parse($scannedAt)->setTimezone($now->getTimezone()) : $now;
        } catch (\Throwable) {
            $time = $now;
        }

        return $time->max($session->opened_at)->min($now);
    }
}
