<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureGateStation;
use App\Models\GateStation;
use App\Support\AttendanceWindow;
use App\Support\GateNetwork;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "Masukkan kode" screen of the gate tablets: a tab code ("TAB1") opens
 * the gate scanner. Teachers log in with their own account instead.
 */
class CodeLoginController extends Controller
{
    /**
     * Wrong codes allowed per minute from one IP address. Only failures count,
     * because every device at school shares the same public IP.
     */
    private const MAX_FAILED_ATTEMPTS = 10;

    public function create(Request $request): Response|RedirectResponse
    {
        if (EnsureGateStation::stationFrom($request)) {
            return redirect()->route('gate.scanner');
        }

        return Inertia::render('Auth/CodeLogin');
    }

    public function store(Request $request, GateNetwork $network, AttendanceWindow $window): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:30']], ['code.required' => 'Masukkan kode tab.']);

        $throttleKey = 'code-login|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_FAILED_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'code' => 'Terlalu banyak kode salah. Coba lagi dalam '.RateLimiter::availableIn($throttleKey).' detik.',
            ]);
        }

        $station = GateStation::query()->where('code', GateStation::normalizeCode($request->input('code')))->first();

        if ($station) {
            return $this->openGate($request, $station, $network, $window);
        }

        RateLimiter::hit($throttleKey);

        throw ValidationException::withMessages(['code' => 'Kode tidak dikenal. Periksa lagi atau tanyakan ke admin.']);
    }

    /**
     * A gate tablet stays logged in until the gate closes (see
     * EnsureGateStation). After jam masuk it cannot log in, so nobody stays
     * on a tablet account: the code screen shows "Scan sudah ditutup".
     */
    private function openGate(Request $request, GateStation $station, GateNetwork $network, AttendanceWindow $window): RedirectResponse
    {
        if ($station->isRevoked()) {
            throw ValidationException::withMessages(['code' => "{$station->name} sedang dicabut. Minta admin mengaktifkannya lagi."]);
        }

        if (! $network->allows($request->ip())) {
            throw ValidationException::withMessages(['code' => 'Tab gerbang hanya bisa dipakai dari jaringan WiFi sekolah.']);
        }

        if ($closedReason = $window->gateClosedReason(now())) {
            return back()->with('gateClosed', $closedReason);
        }

        $station->markSeen(0);

        return redirect()->route('gate.scanner')->withCookie(EnsureGateStation::loginCookie($station));
    }
}
