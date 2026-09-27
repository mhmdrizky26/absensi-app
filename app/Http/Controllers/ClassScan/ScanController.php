<?php

namespace App\Http\Controllers\ClassScan;

use App\Actions\RecordClassScans;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    /**
     * Receive a batch of queued marks from the class scanner.
     */
    public function store(Request $request, RecordClassScans $recordClassScans): JsonResponse
    {
        $validated = $request->validate([
            'scans' => ['required', 'array', 'max:200'],
            'scans.*.ref' => ['required', 'string', 'max:40'],
            'scans.*.method' => ['required', 'in:scan,manual'],
            'scans.*.payload' => ['nullable', 'string', 'max:64'],
            'scans.*.student_id' => ['nullable', 'integer'],
            'scans.*.scanned_at' => ['nullable', 'date'],
        ]);

        /** @var AttendanceSession $attendanceSession */
        $attendanceSession = $request->attributes->get('attendanceSession');

        return response()->json([
            'results' => $recordClassScans->handle($attendanceSession, $validated['scans']),
        ]);
    }
}
