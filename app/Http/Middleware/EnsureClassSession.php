<?php

namespace App\Http\Middleware;

use App\Models\AttendanceSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a phone through only after it entered a class code today. The
 * session is shared with controllers as the "attendanceSession" attribute.
 */
class EnsureClassSession
{
    public const SESSION_KEY = 'class_session_id';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $attendanceSession = AttendanceSession::query()
            ->with('classroom.academicYear')
            ->find($request->session()->get(self::SESSION_KEY));

        if (! $attendanceSession || ! $attendanceSession->date->isToday()) {
            $request->session()->forget(self::SESSION_KEY);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sesi kelas berakhir. Masukkan kode kelas lagi.'], 401);
            }

            return redirect()->route('class.login')->with('error', 'Sesi kelas berakhir. Masukkan kode kelas lagi.');
        }

        $request->attributes->set('attendanceSession', $attendanceSession);

        return $next($request);
    }
}
