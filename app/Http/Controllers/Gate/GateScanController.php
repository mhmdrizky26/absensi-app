<?php

namespace App\Http\Controllers\Gate;

use App\Actions\RecordGateScans;
use App\Http\Controllers\Controller;
use App\Models\GateStation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GateScanController extends Controller
{
    /**
     * Receive a batch of queued card scans from a gate tablet.
     */
    public function store(Request $request, RecordGateScans $recordGateScans): JsonResponse
    {
        $validated = $request->validate([
            'scans' => ['required', 'array', 'max:200'],
            'scans.*.ref' => ['required', 'string', 'max:40'],
            'scans.*.payload' => ['required', 'string', 'max:64'],
            'scans.*.scanned_at' => ['nullable', 'date'],
        ]);

        /** @var GateStation $station */
        $station = $request->attributes->get('gateStation');

        return response()->json([
            'results' => $recordGateScans->handle($station, $validated['scans']),
        ]);
    }
}
