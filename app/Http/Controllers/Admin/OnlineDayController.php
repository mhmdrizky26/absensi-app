<?php

namespace App\Http\Controllers\Admin;

use App\Actions\FinalizeAttendanceDay;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\OnlineDay;
use App\Support\AttendanceWindow;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OnlineDayController extends Controller
{
    /**
     * Mark a school day, or a run of them, as sekolah daring. Weekdays without
     * school and holidays in the range are skipped. Days up to today are
     * finished at once: every student without a mark becomes Hadir.
     */
    public function store(Request $request, AttendanceWindow $window, FinalizeAttendanceDay $finalize): RedirectResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'description' => ['required', 'string', 'max:150'],
        ], attributes: ['from' => 'Tanggal', 'to' => 'Sampai tanggal', 'description' => 'Keterangan']);

        $to = $request->date('to') ?? $request->date('from');

        if ($request->date('from')->diffInDays($to) > 60) {
            return back()->withErrors(['to' => 'Rentang hari daring paling lama 60 hari.']);
        }

        $added = 0;
        $marked = 0;

        foreach (CarbonPeriod::create($request->date('from'), $to) as $date) {
            if (! $window->isSchoolDay($date)) {
                continue;
            }

            $onlineDay = OnlineDay::query()->firstOrCreate(['date' => $date->toDateString()], ['description' => $validated['description']]);

            if (! $onlineDay->wasRecentlyCreated) {
                continue;
            }

            $added++;

            if ($date->lessThanOrEqualTo(today())) {
                $marked += $finalize->finishOnline($date);
            }
        }

        if ($added === 0) {
            return back()->withErrors(['from' => 'Tidak ada hari sekolah baru di tanggal itu (hari libur, bukan hari sekolah, atau sudah hari daring).']);
        }

        return back()->with('success', $marked > 0
            ? "{$added} hari daring ditambahkan. {$marked} tanda Hadir otomatis dicatat."
            : "{$added} hari daring ditambahkan. Siswa otomatis dicatat Hadir pada harinya.");
    }

    /**
     * Back to an ordinary school day. The automatic Hadir marks are removed;
     * a day that has passed is then finished as usual (unmarked = Alpa),
     * unless nobody else has a mark that day.
     */
    public function destroy(OnlineDay $onlineDay, FinalizeAttendanceDay $finalize): RedirectResponse
    {
        $date = $onlineDay->date;
        $onlineDay->delete();
        $removed = $finalize->cancelOnline($date);

        if ($date->lessThan(today()) && Attendance::query()->whereDate('date', $date)->exists()) {
            $finalize->handle($date);
        }

        return back()->with('success', "Hari daring {$date->translatedFormat('j F Y')} dibatalkan. {$removed} tanda Hadir otomatis dihapus.");
    }
}
