<?php

namespace App\Http\Controllers\Duty;

use App\Actions\FinalizeAttendanceDay;
use App\Http\Controllers\Controller;
use App\Models\AttendanceDay;
use App\Support\AttendanceWindow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FinalizeDayController extends Controller
{
    /**
     * "Selesaikan absen hari ini" by the guru piket, once the late arrivals
     * are recorded: students still without a mark become Alpa.
     */
    public function store(Request $request, AttendanceWindow $window, FinalizeAttendanceDay $finalize): RedirectResponse
    {
        $today = today();

        if (AttendanceDay::forDate($today)) {
            return back()->with('error', 'Absen hari ini sudah diselesaikan.');
        }

        if ($window->isEnforced() && ! $window->isSchoolDay($today)) {
            return back()->with('error', 'Hari ini bukan hari sekolah, jadi tidak ada absen yang perlu diselesaikan.');
        }

        if ($window->isEnforced() && now()->lessThan($window->closesAt($today))) {
            return back()->with('error', 'Gerbang belum ditutup. Absen bisa diselesaikan setelah jam masuk pukul '.$window->closesAt($today)->format('H:i').'.');
        }

        $day = $finalize->handle($today, $request->user());

        return back()->with('success', $day->absent_count > 0
            ? "Absen hari ini selesai. {$day->absent_count} siswa yang belum absen dicatat Alpa."
            : 'Absen hari ini selesai. Semua siswa sudah tercatat.');
    }

    /**
     * "Buka lagi absen hari ini", only in demo mode so a practice day can be
     * repeated: the automatic Alpa marks are removed and scanning reopens.
     */
    public function destroy(AttendanceWindow $window, FinalizeAttendanceDay $finalize): RedirectResponse
    {
        if ($window->isEnforced()) {
            return back()->with('error', 'Absen yang sudah diselesaikan hanya bisa dibuka lagi di mode demo. Koreksi dilakukan wali kelas lewat rekap.');
        }

        if (! AttendanceDay::forDate(today())) {
            return back()->with('error', 'Absen hari ini belum diselesaikan.');
        }

        $removed = $finalize->reopen(today());

        return back()->with('success', "Absen hari ini dibuka lagi. {$removed} tanda Alpa otomatis dihapus; tab gerbang dan Absen piket menerima absen lagi.");
    }
}
