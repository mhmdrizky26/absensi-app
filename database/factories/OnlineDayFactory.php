<?php

namespace Database\Factories;

use App\Models\OnlineDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnlineDay>
 */
class OnlineDayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d'),
            'description' => 'Sekolah daring '.fake()->words(2, true),
        ];
    }
}
