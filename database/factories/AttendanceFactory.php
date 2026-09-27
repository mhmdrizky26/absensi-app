<?php

namespace Database\Factories;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
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
            'classroom_id' => Classroom::factory(),
            'date' => today(),
            'status' => AttendanceStatus::Present,
            'source' => AttendanceSource::ClassScan,
            'recorded_at' => now(),
        ];
    }
}
