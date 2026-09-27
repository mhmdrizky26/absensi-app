<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Classroom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $grade = fake()->randomElement(array_keys(Classroom::GRADES));

        return [
            'academic_year_id' => AcademicYear::factory(),
            'grade' => $grade,
            'name' => Classroom::nameFor($grade, fake()->unique()->regexify('[A-Z]{2}')),
            'homeroom_teacher_id' => null,
        ];
    }
}
