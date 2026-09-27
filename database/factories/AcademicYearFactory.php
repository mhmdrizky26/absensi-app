<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startYear = fake()->unique()->numberBetween(2000, 2090);

        return [
            'name' => $startYear.'/'.($startYear + 1),
            'odd_semester_starts_on' => Carbon::create($startYear, 7, 14),
            'even_semester_starts_on' => Carbon::create($startYear + 1, 1, 5),
            'ends_on' => Carbon::create($startYear + 1, 6, 27),
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => true]);
    }
}
