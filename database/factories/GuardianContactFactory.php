<?php

namespace Database\Factories;

use App\Models\GuardianContact;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuardianContact>
 */
class GuardianContactFactory extends Factory
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
            'name' => fake()->name(),
            'relation' => fake()->randomElement(array_keys(GuardianContact::RELATIONS)),
            'phone' => '628'.fake()->unique()->numerify('##########'),
            'consented_at' => now(),
        ];
    }

    public function withoutConsent(): static
    {
        return $this->state(fn (array $attributes) => ['consented_at' => null]);
    }
}
