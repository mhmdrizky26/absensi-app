<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The sapa pagi schedule: the same form as the duty schedule, but guru BK
 * greet students at the gate too.
 */
class GreetingScheduleRequest extends DutyScheduleRequest
{
    public const ROLES = [Role::Guru, Role::GuruBk];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'days.*.*' => ['integer', Rule::exists('users', 'id')->whereIn('role', array_map(fn (Role $role): string => $role->value, self::ROLES))->where('is_active', true)],
        ];
    }
}
