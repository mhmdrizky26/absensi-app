<?php

namespace App\Http\Controllers\Gate;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceDay;
use App\Models\GateStation;
use App\Models\Student;
use App\Support\AttendanceWindow;
use App\Support\GateRoster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GateScannerController extends Controller
{
    /**
     * The gate tablet's scanner. It caches every active student so it keeps
     * scanning offline, and opens and closes itself on the day's schedule.
     */
    public function show(Request $request, AttendanceWindow $window, GateRoster $roster): Response
    {
        /** @var GateStation $station */
        $station = $request->attributes->get('gateStation');
        $station->markSeen();

        return Inertia::render('Gate/Scanner', [
            'station' => ['id' => $station->id, 'name' => $station->name],
            'today' => $this->today($window),
            'roster' => $roster->students()->map(fn (object $student): array => [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
                'hash' => $student->qr_revoked_at === null ? Student::qrHashOfToken($student->qr_token) : null,
                'classroom' => $student->classroom,
                'studyIn' => $student->study_classroom,
            ])->values(),
            'marks' => (object) Attendance::query()
                ->whereDate('date', today())
                ->get(['student_id', 'status', 'recorded_at'])
                ->filter(fn (Attendance $attendance): bool => $attendance->status->isAtSchool())
                ->mapWithKeys(fn (Attendance $attendance): array => [
                    $attendance->student_id => ['status' => $attendance->status->value, 'recordedAt' => $attendance->recorded_at->toIso8601String()],
                ])
                ->all(),
        ]);
    }

    /**
     * The tablet checks in every minute with how many scans it still has to
     * send, and learns whether the day or its schedule changed.
     */
    public function status(Request $request, AttendanceWindow $window): JsonResponse
    {
        $request->validate(['pending' => ['required', 'integer', 'min:0']]);

        /** @var GateStation $station */
        $station = $request->attributes->get('gateStation');
        $station->markSeen($request->integer('pending'));

        return response()->json(['today' => $this->today($window)]);
    }

    /**
     * @return array{date: string, serverNow: string, isEnforced: bool, isSchoolDay: bool, holiday: ?string, opensAt: string, closesAt: string, isFinalized: bool}
     */
    private function today(AttendanceWindow $window): array
    {
        $now = now();

        return [
            'date' => $now->toDateString(),
            'serverNow' => $now->toIso8601String(),
            'isEnforced' => $window->isEnforced(),
            'isSchoolDay' => $window->isSchoolDay($now),
            'holiday' => $window->holidayOn($now)?->description,
            'opensAt' => $window->opensAt($now)->toIso8601String(),
            'closesAt' => $window->closesAt($now)->toIso8601String(),
            'isFinalized' => AttendanceDay::forDate($now) !== null,
        ];
    }
}
