<?php

namespace Database\Factories;

use App\Models\GateStation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GateStation>
 */
class GateStationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999);

        return [
            'name' => "Tab {$number}",
            'code' => "TAB{$number}",
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now()]);
    }
}
