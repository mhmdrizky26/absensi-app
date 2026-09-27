<?php

namespace App\Http\Controllers\ClassScan;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureClassSession;
use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Support\AttendanceWindow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClassLoginController extends Controller
{
    /**
     * Wrong codes allowed per minute from one IP address. Only failures count,
     * because every teacher at school shares the same public IP.
     */
    private const MAX_FAILED_ATTEMPTS = 10;

    public function create(AttendanceWindow $window): Response
    {
        $today = now();

        return Inertia::render('Scan/Login', [
            'opensAt' => $window->opensAt($today)->format('H:i'),
            'closesAt' => $window->closesAt($today)->format('H:i'),
            'closedReason' => $window->closedReason($today),
        ]);
    }

    public function store(Request $request, AttendanceWindow $window): RedirectResponse
    {
        $request->merge(['code' => Str::upper(preg_replace('/\s+/', '', (string) $request->input('code')))]);
        $request->validate(['code' => ['required', 'string', 'between:'.Classroom::ACCESS_CODE_MIN.','.Classroom::ACCESS_CODE_MAX]], [
            'code.required' => 'Masukkan kode kelas.',
            'code.between' => 'Kode kelas terdiri dari '.Classroom::ACCESS_CODE_MIN.'–'.Classroom::ACCESS_CODE_MAX.' karakter.',
        ]);

        $throttleKey = 'class-code|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_FAILED_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'code' => 'Terlalu banyak kode salah. Coba lagi dalam '.RateLimiter::availableIn($throttleKey).' detik.',
            ]);
        }

        $classroom = Classroom::query()
            ->where('access_code', $request->input('code'))
            ->where('academic_year_id', AcademicYear::active()?->id)
            ->first();

        if (! $classroom) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['code' => 'Kode kelas tidak dikenal. Periksa lagi atau tanyakan ke admin.']);
        }

        if ($reason = $window->closedReason(now())) {
            throw ValidationException::withMessages(['code' => $reason]);
        }

        $attendanceSession = AttendanceSession::query()->firstOrCreate(
            ['classroom_id' => $classroom->id, 'date' => today()->toDateString()],
            ['opened_at' => now(), 'ip_address' => $request->ip(), 'user_agent' => Str::limit((string) $request->userAgent(), 250, '')],
        );

        $request->session()->regenerate();
        $request->session()->put(EnsureClassSession::SESSION_KEY, $attendanceSession->id);

        return redirect()->route('class.scanner');
    }

    /**
     * Lock the scanner: this phone must enter the class code again.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(EnsureClassSession::SESSION_KEY);

        return redirect()->route('class.login');
    }
}
