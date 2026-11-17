<?php

namespace App\Http\Controllers;

use App\Support\LiveUpdates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveUpdateController extends Controller
{
    /**
     * The current data versions, polled by open pages every few seconds.
     * This user's menu counts are only counted again when the page did not
     * know the latest data version yet, so a quiet poll costs one cache read.
     */
    public function __invoke(Request $request, LiveUpdates $liveUpdates): JsonResponse
    {
        $versions = $liveUpdates->versions();
        $dataChanged = $request->query('data') !== $versions[LiveUpdates::DATA];

        return response()->json([
            'versions' => $versions,
            'counts' => $dataChanged ? ['pendingDispensations' => $liveUpdates->pendingDispensations($request->user())] : null,
        ]);
    }
}
