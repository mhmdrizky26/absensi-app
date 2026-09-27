<?php

namespace Database\Seeders;

use App\Enums\AttendanceSource;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Holiday;
use App\Models\User;
use App\Support\AttendanceWindow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Attendance history from the start of the active academic year until
 * yesterday, so recaps have something to show in a demo. Uses a fixed random
 * seed, so every run produces the same history.
 */
class DemoAttendanceSeeder extends Seeder
{
    private const EXCUSE_NOTES = ['S' => ['Demam', 'Sakit perut', 'Flu', 'Periksa ke dokter'], 'I' => ['Acara keluarga', 'Dispensasi lomba', 'Urusan keluarga']];

    public function run(AttendanceWindow $window): void
    {
        $academicYear = AcademicYear::active();

        if (! $academicYear) {
            return;
        }

        $this->seedHolidays();
        mt_srand(2026);

        $from = $academicYear->odd_semester_starts_on->toImmutable();
        $to = CarbonImmutable::yesterday();
        $days = $to->lessThan($from) ? [] : $window->schoolDaysBetween($from, $to);
        $piketId = User::query()->where('username', 'piket')->value('id');

        $classrooms = $academicYear->classrooms()->with(['students' => fn ($query) => $query->where('status', StudentStatus::Active)])->get();

        // A few students skip school far more often than the rest.
        $frequentlyAbsent = $classrooms->flatMap->students->pluck('id')->filter(fn (): bool => mt_rand(1, 100) <= 3)->flip();

        foreach ($days as $day) {
            $this->seedDay($day, $classrooms->all(), $frequentlyAbsent->all(), $piketId);
        }
    }

    private function seedHolidays(): void
    {
        foreach (['2026-08-17' => 'Hari Kemerdekaan RI', '2026-12-25' => 'Hari Raya Natal', '2027-01-01' => 'Tahun Baru Masehi'] as $date => $description) {
            Holiday::query()->firstOrCreate(['date' => $date], ['description' => $description]);
        }
    }

    /**
     * @param  list<Classroom>  $classrooms
     * @param  array<int, int>  $frequentlyAbsent
     */
    private function seedDay(CarbonImmutable $day, array $classrooms, array $frequentlyAbsent, ?int $piketId): void
    {
        $now = now();
        $attendances = [];

        foreach ($classrooms as $classroom) {
            $openedAt = $day->setTime(6, 45)->addMinutes(mt_rand(0, 25));
            $sessionId = DB::table('attendance_sessions')->insertGetId([
                'classroom_id' => $classroom->id,
                'date' => $day->toDateString(),
                'opened_at' => $openedAt,
                'closed_at' => $openedAt->addMinutes(mt_rand(8, 20)),
                'ip_address' => '10.0.0.'.mt_rand(10, 250),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($classroom->students as $student) {
                $status = $this->pickStatus(isset($frequentlyAbsent[$student->id]));

                $attendances[] = [
                    'student_id' => $student->id,
                    'classroom_id' => $classroom->id,
                    'date' => $day->toDateString(),
                    'status' => $status,
                    'source' => match ($status) {
                        'H' => mt_rand(1, 100) <= 96 ? AttendanceSource::ClassScan->value : AttendanceSource::ClassManual->value,
                        'T' => AttendanceSource::DutyScan->value,
                        'S', 'I' => AttendanceSource::Staff->value,
                        default => AttendanceSource::Automatic->value,
                    },
                    'recorded_at' => match ($status) {
                        'H' => $openedAt->addMinutes(mt_rand(0, 8)),
                        'T' => $day->setTime(7, 35)->addMinutes(mt_rand(0, 70)),
                        default => $openedAt->addMinutes(15),
                    },
                    'attendance_session_id' => $sessionId,
                    'recorded_by' => in_array($status, ['T', 'S', 'I'], true) ? $piketId : null,
                    'note' => match ($status) {
                        'S', 'I' => self::EXCUSE_NOTES[$status][array_rand(self::EXCUSE_NOTES[$status])],
                        'T' => 'Datang terlambat.',
                        default => null,
                    },
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($attendances, 1000) as $chunk) {
            DB::table('attendances')->insert($chunk);
        }
    }

    private function pickStatus(bool $frequentlyAbsent): string
    {
        $roll = mt_rand(1, 1000);

        if ($frequentlyAbsent) {
            return match (true) {
                $roll <= 120 => 'A',
                $roll <= 170 => 'T',
                $roll <= 200 => 'S',
                default => 'H',
            };
        }

        return match (true) {
            $roll <= 15 => 'A',
            $roll <= 45 => 'T',
            $roll <= 70 => 'S',
            $roll <= 85 => 'I',
            default => 'H',
        };
    }
}
