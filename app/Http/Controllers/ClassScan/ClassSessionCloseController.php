<?php

namespace App\Http\Controllers\ClassScan;

use App\Actions\CloseClassSession;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClassSessionCloseController extends Controller
{
    /**
     * The teacher finished scanning: lock the session and mark the rest Alpa.
     */
    public function store(Request $request, CloseClassSession $closeClassSession): RedirectResponse
    {
        /** @var AttendanceSession $attendanceSession */
        $attendanceSession = $request->attributes->get('attendanceSession');

        $absentCount = $closeClassSession->handle($attendanceSession);

        return redirect()->route('class.scanner')->with('success', $absentCount > 0
            ? "Absensi selesai. {$absentCount} siswa yang belum absen ditandai Alpa."
            : 'Absensi selesai. Semua siswa sudah tercatat.');
    }
}
