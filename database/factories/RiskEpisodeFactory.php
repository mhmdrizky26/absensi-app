<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\RiskEpisode;
use App\Models\Student;
use App\Support\AttendanceRisk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiskEpisode>
 */
class RiskEpisodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'semester' => 'ganjil',
            'started_on' => today(),
            'level' => AttendanceRisk::LEVEL_WATCH,
            'reasons' => [['level' => AttendanceRisk::LEVEL_WATCH, 'text' => 'Alpa 5 kali semester ini.']],
        ];
    }
}
