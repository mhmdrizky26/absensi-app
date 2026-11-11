<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GateStation;
use Illuminate\Http\RedirectResponse;

class GateStationRevocationController extends Controller
{
    /**
     * Revoke a lost or broken tablet: it is logged out and its code stops working.
     */
    public function store(GateStation $gateStation): RedirectResponse
    {
        $gateStation->revoke();

        return back()->with('success', "{$gateStation->name} dicabut. Tab itu langsung keluar dan kodenya tidak bisa dipakai.");
    }

    /**
     * Let the code work again. The tablet has to log in again.
     */
    public function destroy(GateStation $gateStation): RedirectResponse
    {
        $gateStation->restore();

        return back()->with('success', "{$gateStation->name} diaktifkan lagi. Login di tab dengan kode {$gateStation->code}.");
    }
}
