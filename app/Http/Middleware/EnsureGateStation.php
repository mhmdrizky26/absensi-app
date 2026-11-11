<?php

namespace App\Http\Middleware;

use App\Models\GateStation;
use App\Support\AttendanceWindow;
use App\Support\GateNetwork;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a gate tablet through when its login cookie still matches a station
 * that is not revoked, from the school network when that lock is on, while
 * the gate is open. Once jam masuk has passed, on days without school, or
 * once the day is finished, the tablet is logged out (queued scans may still
 * be sent for a short while). The station is shared with controllers as the
 * "gateStation" attribute.
 */
class EnsureGateStation
{
    public const COOKIE = 'gate_station';

    /**
     * Long enough to outlast any school day; the gate schedule logs the
     * tablet out every day anyway.
     */
    public const COOKIE_MINUTES = 60 * 24;

    public function __construct(private GateNetwork $network, private AttendanceWindow $window) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $station = self::stationFrom($request);

        if (! $station) {
            return $this->deny($request, 'Tab ini belum login atau sudah dikeluarkan admin. Masukkan kode tab lagi.');
        }

        if (! $this->network->allows($request->ip())) {
            return $this->deny($request, 'Tab gerbang hanya bisa dipakai dari jaringan WiFi sekolah.');
        }

        $closedReason = $this->window->gateClosedReason(now());
        $sendingQueue = $request->routeIs('gate.scan', 'gate.status') && $this->window->acceptsQueuedScans(now());

        if ($closedReason && ! $sendingQueue) {
            return $this->deny($request, $closedReason, isClosed: true);
        }

        $request->attributes->set('gateStation', $station);

        return $next($request);
    }

    /**
     * The station the request's login cookie belongs to, or null.
     */
    public static function stationFrom(Request $request): ?GateStation
    {
        [$id, $token] = array_pad(explode('|', (string) $request->cookie(self::COOKIE), 2), 2, '');

        if (! ctype_digit($id) || $token === '') {
            return null;
        }

        $station = GateStation::query()->whereNull('revoked_at')->find((int) $id);

        return $station && hash_equals($station->login_token, $token) ? $station : null;
    }

    public static function loginCookie(GateStation $station): SymfonyCookie
    {
        return Cookie::make(self::COOKIE, "{$station->id}|{$station->login_token}", self::COOKIE_MINUTES);
    }

    /**
     * Log the tablet out. A closed gate is shown on the code screen as a
     * "Scan sudah ditutup" dialog; other reasons as an error.
     */
    private function deny(Request $request, string $message, bool $isClosed = false): Response
    {
        $request->session()->flash($isClosed ? 'gateClosed' : 'error', $message);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401)->withoutCookie(self::COOKIE);
        }

        return redirect()->route('code.login')->withoutCookie(self::COOKIE);
    }
}
