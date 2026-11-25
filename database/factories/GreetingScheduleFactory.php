<?php

namespace Database\Factories;

use App\Models\GreetingSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GreetingSchedule>
 */
class GreetingScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'weekday' => fake()->numberBetween(1, 5),
        ];
    }
}
