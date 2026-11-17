<?php

namespace App\Http\Controllers\Gate;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceDay;
use App\Models\GateStation;
use App\Models\Student;
use App\Support\AttendanceWindow;
use App\Support\GateRoster;
use App\Support\LiveUpdates;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GateScannerController extends Controller
{
    /**
     * A sync also returns marks changed shortly before the last one, in case
     * a save was still running then. Sending a mark twice does no harm.
     */
    private const SYNC_OVERLAP_SECONDS = 10;

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
            'marks' => (object) $this->marksSince(null),
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
     * Today's marks changed since the tablet's last sync, so a student who
     * scanned at another tab is known here within seconds. A student whose
     * mark no longer counts as at school comes back as null.
     */
    public function marks(Request $request, LiveUpdates $liveUpdates): JsonResponse
    {
        $request->validate(['since' => ['nullable', 'date'], 'version' => ['nullable', 'string', 'max:20']]);
        $syncedAt = now();
        $version = $liveUpdates->versions()[LiveUpdates::ATTENDANCE];
        $since = $request->date('since');

        // Tidak ada absen yang berubah sejak sinkron terakhir: tanpa query.
        if ($since !== null && $request->input('version') === $version) {
            return response()->json(['marks' => (object) [], 'syncedAt' => $since->toIso8601String(), 'version' => $version]);
        }

        return response()->json([
            'marks' => (object) $this->marksSince($since),
            'syncedAt' => $syncedAt->toIso8601String(),
            'version' => $version,
        ]);
    }

    /**
     * @return array<int, array{status: string, recordedAt: string, station: ?string}|null>
     */
    private function marksSince(?CarbonInterface $since): array
    {
        return Attendance::query()
            ->with('gateStation:id,name')
            ->whereBetween('date', [today(), today()->endOfDay()])
            ->when($since, fn (Builder $query) => $query->where('updated_at', '>=', $since->copy()->subSeconds(self::SYNC_OVERLAP_SECONDS)))
            ->get(['student_id', 'status', 'recorded_at', 'gate_station_id'])
            ->filter(fn (Attendance $attendance): bool => $since !== null || $attendance->status->isAtSchool())
            ->mapWithKeys(fn (Attendance $attendance): array => [
                $attendance->student_id => $attendance->status->isAtSchool() ? $attendance->gateMark() : null,
            ])
            ->all();
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
