<?php

namespace Database\Factories;

use App\Models\DutySubstitution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DutySubstitution>
 */
class DutySubstitutionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => today()->toDateString(),
            'absent_user_id' => User::factory(),
            'substitute_user_id' => User::factory(),
        ];
    }
}
