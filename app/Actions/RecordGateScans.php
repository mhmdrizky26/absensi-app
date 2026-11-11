<?php

namespace App\Actions;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceDay;
use App\Models\GateStation;
use App\Models\Student;
use App\Support\AttendanceWindow;
use App\Support\GateRoster;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Saves the card scans a gate tablet sends, possibly in a batch after it was
 * offline. Scans count as Hadir only when made before jam masuk; later
 * arrivals are recorded by the guru piket. Every scan gets its own result
 * so the tablet can clear its queue.
 */
class RecordGateScans
{
    public function __construct(private AttendanceWindow $window, private GateRoster $roster) {}

    /**
     * @param  list<array{ref: string, payload: string, scanned_at?: ?string}>  $items
     * @return list<array{ref: string, result: string, studentId?: int, recordedAt?: string, status?: string, message?: string}>
     */
    public function handle(GateStation $station, array $items): array
    {
        $station->markSeen();
        $today = CarbonImmutable::today();
        $closedReason = $this->closedReason($today);

        return array_map(fn (array $item): array => [
            'ref' => $item['ref'],
            ...($closedReason ? ['result' => 'rejected', 'message' => $closedReason] : $this->record($station, $today, $item)),
        ], $items);
    }

    private function closedReason(CarbonImmutable $today): ?string
    {
        if (AttendanceDay::forDate($today)) {
            return 'Absen hari ini sudah diselesaikan guru piket. Silakan lapor ke guru piket.';
        }

        if (! $this->window->isEnforced()) {
            return null;
        }

        if ($holiday = $this->window->holidayOn($today)) {
            return "Hari ini libur ({$holiday->description}).";
        }

        return $this->window->isSchoolDay($today) ? null : 'Hari ini bukan hari sekolah.';
    }

    /**
     * @param  array{payload: string, scanned_at?: ?string}  $item
     * @return array{result: string, studentId?: int, recordedAt?: string, status?: string, message?: string}
     */
    private function record(GateStation $station, CarbonImmutable $today, array $item): array
    {
        $student = Student::findByQrPayload($item['payload']);

        if (! $student) {
            return ['result' => 'rejected', 'message' => 'Kartu tidak dikenal atau sudah dicabut.'];
        }

        $placement = $this->roster->students([$student->id])->first();

        if (! $placement) {
            return ['result' => 'rejected', 'studentId' => $student->id, 'message' => "{$student->name} bukan siswa aktif di tahun ajaran ini."];
        }

        $scannedAt = $this->scanTime($item['scanned_at'] ?? null);

        if ($scannedAt->lessThan($today)) {
            return [
                'result' => 'rejected',
                'studentId' => $student->id,
                'message' => 'Scan tanggal '.$scannedAt->translatedFormat('j F').' baru terkirim hari ini, jadi tidak dicatat. Minta wali kelas mengoreksi.',
            ];
        }

        if ($this->window->isEnforced() && $scannedAt->lessThan($this->window->opensAt($today))) {
            return ['result' => 'rejected', 'studentId' => $student->id, 'message' => 'Gerbang baru dibuka pukul '.$this->window->opensAt($today)->format('H:i').'.'];
        }

        if ($this->window->isEnforced() && $scannedAt->greaterThanOrEqualTo($this->window->closesAt($today))) {
            return [
                'result' => 'rejected',
                'studentId' => $student->id,
                'message' => 'Gerbang sudah ditutup pukul '.$this->window->closesAt($today)->format('H:i').'. Silakan lapor ke guru piket.',
            ];
        }

        $existing = Attendance::query()->where('student_id', $student->id)->whereDate('date', $today)->first();

        if ($existing?->status->isAtSchool()) {
            return $this->duplicate($existing);
        }

        $attributes = [
            'classroom_id' => $placement->classroom_id,
            'status' => AttendanceStatus::Present,
            'source' => AttendanceSource::GateScan,
            'recorded_at' => $scannedAt,
            'gate_station_id' => $station->id,
            'recorded_by' => null,
            'note' => $existing ? "Sebelumnya {$existing->status->label()}, ternyata datang ke sekolah." : null,
        ];

        try {
            $existing
                ? $existing->update($attributes)
                : Attendance::create(['student_id' => $student->id, 'date' => $today->toDateString(), ...$attributes]);
        } catch (UniqueConstraintViolationException) {
            // Tab lain baru saja menyimpan siswa ini.
            return $this->duplicate(Attendance::query()->where('student_id', $student->id)->whereDate('date', $today)->firstOrFail());
        }

        return ['result' => 'recorded', 'studentId' => $student->id, 'recordedAt' => $scannedAt->toIso8601String(), 'status' => AttendanceStatus::Present->value];
    }

    /**
     * @return array{result: string, studentId: int, recordedAt: string, status: string}
     */
    private function duplicate(Attendance $attendance): array
    {
        return [
            'result' => 'duplicate',
            'studentId' => $attendance->student_id,
            'recordedAt' => $attendance->recorded_at->toIso8601String(),
            'status' => $attendance->status->value,
        ];
    }

    /**
     * Use the tablet's scan time (it may have been offline), but never after
     * the server's clock.
     */
    private function scanTime(?string $scannedAt): CarbonImmutable
    {
        $now = CarbonImmutable::now();

        try {
            $time = $scannedAt ? CarbonImmutable::parse($scannedAt)->setTimezone($now->getTimezone()) : $now;
        } catch (\Throwable) {
            $time = $now;
        }

        return $time->min($now);
    }
}
