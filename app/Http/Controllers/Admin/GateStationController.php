<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GateStationRequest;
use App\Models\GateStation;
use App\Support\GateNetwork;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GateStationController extends Controller
{
    /**
     * The gate tablets with whether each is online and still has scans to send.
     */
    public function index(Request $request, GateNetwork $network): Response
    {
        return Inertia::render('Admin/GateStations/Index', [
            'stations' => GateStation::query()
                ->orderByRaw('LENGTH(code), code')
                ->get()
                ->map(fn (GateStation $station): array => [
                    'id' => $station->id,
                    'name' => $station->name,
                    'code' => $station->code,
                    'isRevoked' => $station->isRevoked(),
                    'isOnline' => ! $station->isRevoked() && $station->isOnline(),
                    'lastSeenAt' => $station->last_seen_at?->toIso8601String(),
                    'pendingScans' => $station->pending_scans,
                ]),
            'suggestion' => GateStation::nextSuggestion(),
            'network' => [
                'allowedIps' => implode("\n", $network->allowedIps()),
                'currentIp' => $request->ip(),
            ],
        ]);
    }

    public function store(GateStationRequest $request): RedirectResponse
    {
        $station = GateStation::create($request->validated());

        return back()->with('success', "{$station->name} dibuat dengan kode {$station->code}.");
    }

    /**
     * A new code logs out the tablet, which must log in again with the new code.
     */
    public function update(GateStationRequest $request, GateStation $gateStation): RedirectResponse
    {
        $codeChanged = $gateStation->code !== $request->validated('code');
        $gateStation->update($request->validated());

        if ($codeChanged) {
            $gateStation->rotateLoginToken();

            return back()->with('success', "Kode {$gateStation->name} diganti menjadi {$gateStation->code}. Tab perlu login lagi dengan kode baru.");
        }

        return back()->with('success', "{$gateStation->name} diperbarui.");
    }

    /**
     * Scans keep their mark; only the link to the tablet is cleared.
     */
    public function destroy(GateStation $gateStation): RedirectResponse
    {
        $gateStation->delete();

        return back()->with('success', "{$gateStation->name} dihapus.");
    }
}
