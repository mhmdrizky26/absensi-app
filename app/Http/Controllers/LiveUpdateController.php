<?php

namespace App\Http\Controllers;

use App\Support\LiveUpdates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveUpdateController extends Controller
{
    /**
     * The current data version and this user's menu counts, polled by open pages.
     */
    public function __invoke(Request $request, LiveUpdates $liveUpdates): JsonResponse
    {
        return response()->json([
            'version' => $liveUpdates->version(),
            'counts' => ['pendingDispensations' => $liveUpdates->pendingDispensations($request->user())],
        ]);
    }
}
