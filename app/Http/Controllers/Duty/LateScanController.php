<?php

namespace App\Http\Controllers\Duty;

use App\Actions\SetAttendanceStatus;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceDay;
use App\Models\Student;
use App\Models\User;
use App\Support\AttendanceWindow;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LateScanController extends Controller
{
    /**
     * Scan absen, for students the gate tablets did not record: those who
     * forgot their card (guru piket and guru sapa pagi), and those who arrive
     * after jam masuk (guru piket only).
     */
    public function create(Request $request, AttendanceWindow $window): Response
    {
        $recorded = Attendance::query()
            ->with(['student:id,name,nis', 'classroom:id,name'])
            ->whereDate('date', today())
            ->where('source', AttendanceSource::DutyScan)
            ->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::Late])
            ->latest('recorded_at')
            ->limit(100)
            ->get()
            ->map(fn (Attendance $attendance): array => $this->present($attendance->student, $attendance->classroom->name, $attendance));

        return Inertia::render('Duty/LateScan', [
            'recorded' => $recorded,
            'opensAt' => $window->isEnforced() ? $window->opensAt(today())->toIso8601String() : null,
            'closesAt' => $window->isEnforced() ? $window->closesAt(today())->toIso8601String() : null,
            'isSchoolDay' => ! $window->isEnforced() || $window->isSchoolDay(today()),
            'isFinalized' => AttendanceDay::forDate(today()) !== null,
            'scanUntil' => $this->greetingScanUntil($request->user(), $window)?->toIso8601String(),
        ]);
    }

    /**
     * Guru sapa pagi (not also guru piket today) record only until jam masuk;
     * later arrivals are the guru piket's. Null when there is no such limit.
     */
    private function greetingScanUntil(User $user, AttendanceWindow $window): ?CarbonImmutable
    {
        if (! $window->isEnforced() || $user->hasRole(Role::Admin) || $user->isOnDuty()) {
            return null;
        }

        return $window->closesAt(today());
    }

    /**
     * Mark one student, from a card scan (payload) or a name search
     * (student_id): Hadir before jam masuk (forgot the card), Terlambat after.
     */
    public function store(Request $request, AttendanceWindow $window, SetAttendanceStatus $setAttendanceStatus): JsonResponse
    {
        $request->validate([
            'payload' => ['nullable', 'required_without:student_id', 'string', 'max:64'],
            'student_id' => ['nullable', 'integer'],
        ]);

        $student = $request->filled('payload')
            ? Student::findByQrPayload((string) $request->input('payload'))
            : Student::find($request->integer('student_id'));

        if (! $student) {
            return $this->rejected($request->filled('payload') ? 'Kartu tidak dikenal atau sudah dicabut.' : 'Siswa tidak ditemukan.');
        }

        if ($student->status !== StudentStatus::Active) {
            return $this->rejected("{$student->name} berstatus {$student->status->label()}, bukan siswa aktif.");
        }

        $classroom = AcademicYear::active() ? $student->classroomIn(AcademicYear::active()) : null;

        if (! $classroom) {
            return $this->rejected("{$student->name} belum punya kelas di tahun ajaran aktif.");
        }

        if ($window->isEnforced() && ! $window->isSchoolDay(today())) {
            return $this->rejected('Hari ini bukan hari sekolah.');
        }

        if ($window->isEnforced() && now()->lessThan($window->opensAt(today()))) {
            return $this->rejected('Absen baru dibuka pukul '.$window->opensAt(today())->format('H:i').', sama dengan tab gerbang.');
        }

        $scanUntil = $this->greetingScanUntil($request->user(), $window);

        if ($scanUntil && now()->greaterThanOrEqualTo($scanUntil)) {
            return $this->rejected('Scan sapa pagi ditutup pukul '.$scanUntil->format('H:i').'. Siswa yang datang terlambat dicatat guru piket.');
        }

        if (AttendanceDay::forDate(today())) {
            return $this->rejected('Absen hari ini sudah diselesaikan. Perubahan dilakukan wali kelas lewat koreksi rekap.');
        }

        $existing = Attendance::query()->where('student_id', $student->id)->whereDate('date', today())->first();

        if ($existing?->status->isAtSchool()) {
            return response()->json([
                'result' => 'duplicate',
                'message' => "Sudah tercatat {$existing->status->label()} pukul {$existing->recorded_at->format('H:i')}.",
                'student' => $this->present($student, $classroom->name, $existing),
            ]);
        }

        $isLate = $window->isEnforced() && now()->greaterThanOrEqualTo($window->closesAt(today()));
        $time = now()->format('H:i');

        $attendance = $setAttendanceStatus->handle($student, today(), $isLate ? AttendanceStatus::Late : AttendanceStatus::Present, AttendanceSource::DutyScan, $request->user(), [
            'note' => $isLate ? "Datang pukul {$time}." : 'Lupa membawa kartu, dicatat lewat scan absen.',
        ]);

        return response()->json([
            'result' => 'recorded',
            'message' => $isLate ? "Dicatat terlambat pukul {$time}." : "Dicatat hadir pukul {$time} (lupa kartu).",
            'student' => $this->present($student, $classroom->name, $attendance),
        ]);
    }

    private function rejected(string $message): JsonResponse
    {
        return response()->json(['result' => 'rejected', 'message' => $message]);
    }

    /**
     * @return array{id: int, name: string, nis: string, classroom: string, status: string, statusLabel: string, recordedAt: string}
     */
    private function present(Student $student, string $classroomName, Attendance $attendance): array
    {
        return [
            'id' => $student->id,
            'name' => $student->name,
            'nis' => $student->nis,
            'classroom' => $classroomName,
            'status' => $attendance->status->value,
            'statusLabel' => $attendance->status->label(),
            'recordedAt' => $attendance->recorded_at->toIso8601String(),
        ];
    }
}
