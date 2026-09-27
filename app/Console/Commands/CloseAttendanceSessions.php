<?php

namespace App\Console\Commands;

use App\Actions\CloseClassSession;
use App\Models\AttendanceSession;
use App\Support\AttendanceWindow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('attendance:close-sessions')]
#[Description('Tutup sesi scan kelas yang lupa diselesaikan guru dan tandai Alpa siswa yang belum absen')]
class CloseAttendanceSessions extends Command
{
    /**
     * Sessions from earlier days are always closed. Today's sessions are
     * closed once the scan window has ended (only when the window is enforced,
     * so demos can keep scanning in the evening).
     */
    public function handle(AttendanceWindow $window, CloseClassSession $closeClassSession): int
    {
        $sessions = AttendanceSession::query()
            ->with('classroom')
            ->whereNull('closed_at')
            ->where(function ($query) use ($window): void {
                $query->whereDate('date', '<', today());

                if ($window->isEnforced() && now()->greaterThanOrEqualTo($window->closesAt(now()))) {
                    $query->orWhereDate('date', today());
                }
            })
            ->get();

        foreach ($sessions as $session) {
            $absentCount = $closeClassSession->handle($session);
            $this->line("Kelas {$session->classroom->name} ({$session->date->toDateString()}) ditutup, {$absentCount} siswa Alpa.");
        }

        $this->info("{$sessions->count()} sesi ditutup.");

        return self::SUCCESS;
    }
}
