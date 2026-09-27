<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\Dispensation;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dispensation>
 */
class DispensationFactory extends Factory
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
            'starts_on' => today(),
            'ends_on' => today(),
            'reason' => 'Lomba '.fake()->word(),
        ];
    }
}
