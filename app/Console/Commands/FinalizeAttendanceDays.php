<?php

namespace App\Console\Commands;

use App\Actions\FinalizeAttendanceDay;
use App\Models\Attendance;
use App\Models\AttendanceDay;
use App\Models\GateStation;
use App\Models\OnlineDay;
use App\Support\AttendanceWindow;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('attendance:finalize-days')]
#[Description('Selesaikan absen hari sekolah yang lupa diselesaikan guru piket dan tandai Alpa siswa yang belum absen')]
class FinalizeAttendanceDays extends Command
{
    /**
     * Days this far back are still finished if the server was off at night.
     */
    private const CATCH_UP_DAYS = 7;

    /**
     * Only days since the first gate tablet was added count: earlier days
     * were recorded per class. A day without a single mark is skipped, since
     * marking the whole school Alpa means the gate was not used that day.
     * Today is finished from the time set in Pengaturan (finalize_at).
     * Sekolah daring days are finished from the start of the day.
     */
    public function handle(AttendanceWindow $window, FinalizeAttendanceDay $finalize): int
    {
        $this->finishOnlineDays($finalize);

        $firstStation = GateStation::query()->min('created_at');

        if ($firstStation === null) {
            $this->info('Belum ada tab gerbang, tidak ada yang diselesaikan.');

            return self::SUCCESS;
        }

        $now = CarbonImmutable::now();
        $from = CarbonImmutable::parse($firstStation)->startOfDay()->max($now->subDays(self::CATCH_UP_DAYS)->startOfDay());
        [$hour, $minute] = array_map('intval', explode(':', (string) config('attendance.finalize_at')));
        $to = $now->greaterThanOrEqualTo($now->setTime($hour, $minute)) ? $now->startOfDay() : $now->subDay()->startOfDay();

        if ($to->lessThan($from)) {
            return self::SUCCESS;
        }

        $finished = AttendanceDay::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')
            ->map(fn (mixed $date): string => substr((string) $date, 0, 10))
            ->flip();

        foreach ($window->schoolDaysBetween($from, $to) as $day) {
            if ($finished->has($day->toDateString())) {
                continue;
            }

            if (! Attendance::query()->whereDate('date', $day)->exists()) {
                $this->warn("{$day->toDateString()}: belum ada absen sama sekali, dilewati.");

                continue;
            }

            $absentCount = $finalize->handle($day)->absent_count;
            $this->line("{$day->toDateString()} diselesaikan, {$absentCount} siswa Alpa.");
        }

        return self::SUCCESS;
    }

    /**
     * Sekolah daring days up to today that are not finished yet: every
     * student without a mark becomes Hadir.
     */
    private function finishOnlineDays(FinalizeAttendanceDay $finalize): void
    {
        $today = CarbonImmutable::today();

        OnlineDay::query()
            ->whereBetween('date', [$today->subDays(self::CATCH_UP_DAYS)->toDateString(), $today->toDateString()])
            ->orderBy('date')
            ->get()
            ->reject(fn (OnlineDay $onlineDay): bool => AttendanceDay::forDate($onlineDay->date) !== null)
            ->each(function (OnlineDay $onlineDay) use ($finalize): void {
                $marked = $finalize->finishOnline($onlineDay->date);
                $this->line("{$onlineDay->date->toDateString()} sekolah daring, {$marked} siswa dicatat Hadir.");
            });
    }
}
