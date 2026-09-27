<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());
        $fakerGender = $gender === Gender::Male ? 'male' : 'female';

        return [
            'nis' => fake()->unique()->numerify('########'),
            'nisn' => fake()->unique()->numerify('00########'),
            'name' => fake()->firstName($fakerGender).' '.fake()->lastName($fakerGender),
            'gender' => $gender,
            'status' => StudentStatus::Active,
        ];
    }
}
