<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\DutySchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'role' => Role::Guru,
            'is_active' => true,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => Role::Admin]);
    }

    public function guru(): static
    {
        return $this->state(fn (array $attributes) => ['role' => Role::Guru]);
    }

    /**
     * A guru scheduled as guru piket on today's weekday.
     */
    public function guruPiket(): static
    {
        return $this->guru()->afterCreating(function (User $user): void {
            DutySchedule::factory()->create(['user_id' => $user->id, 'weekday' => today()->dayOfWeekIso]);
        });
    }

    /**
     * A guru meant to lead a class; set the classroom's homeroom_teacher_id
     * to make them wali kelas.
     */
    public function waliKelas(): static
    {
        return $this->guru();
    }

    public function guruBk(): static
    {
        return $this->state(fn (array $attributes) => ['role' => Role::GuruBk]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
