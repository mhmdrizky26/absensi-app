<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\DutySchedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DutyScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The whole weekly schedule at once: per weekday the guru on duty.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'days' => ['present', 'array'],
            'days.*' => ['array'],
            'days.*.*' => ['integer', Rule::exists('users', 'id')->where('role', Role::Guru->value)->where('is_active', true)],
        ];
    }

    /**
     * @return array<int, list<int>> weekday => user ids, for every school weekday
     */
    public function schedule(): array
    {
        $days = (array) $this->validated('days');

        return collect(DutySchedule::WEEKDAYS)
            ->keys()
            ->mapWithKeys(fn (int $weekday): array => [$weekday => array_values(array_unique(array_map('intval', (array) ($days[$weekday] ?? []))))])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'days.*.*.exists' => 'Pilih akun guru yang masih aktif.',
        ];
    }
}
