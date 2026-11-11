<?php

namespace App\Http\Requests\Admin;

use App\Support\AttendanceWindow;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The weekly schedule: for each weekday whether it is a school day, when the
 * gate opens and when it closes (jam masuk).
 */
class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'size:7'],
            'days.*.day' => ['required', 'integer', 'between:1,7', 'distinct'],
            'days.*.active' => ['required', 'boolean'],
            'days.*.opens_at' => ['exclude_unless:days.*.active,true', 'required', 'date_format:H:i'],
            'days.*.closes_at' => ['exclude_unless:days.*.active,true', 'required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $days = collect($this->input('days'));

                if ($days->where('active', true)->isEmpty()) {
                    $validator->errors()->add('days', 'Pilih minimal satu hari sekolah.');
                }

                foreach ($days as $index => $day) {
                    if ($day['active'] && $day['closes_at'] <= $day['opens_at']) {
                        $validator->errors()->add("days.{$index}.closes_at", 'Jam masuk harus setelah jam buka.');
                    }
                }
            },
        ];
    }

    /**
     * The schedule as AttendanceWindow stores it: school days only.
     *
     * @return array<int, array{opens_at: string, closes_at: string}>
     */
    public function schedule(): array
    {
        return collect($this->validated('days'))
            ->filter(fn (array $day): bool => (bool) $day['active'])
            ->mapWithKeys(fn (array $day): array => [(int) $day['day'] => ['opens_at' => $day['opens_at'], 'closes_at' => $day['closes_at']]])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'days' => 'Jadwal',
            'days.*.opens_at' => 'Jam buka',
            'days.*.closes_at' => 'Jam masuk',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'days' => collect($this->input('days', []))->map(fn (mixed $day): mixed => is_array($day) ? [...$day, 'active' => filter_var($day['active'] ?? false, FILTER_VALIDATE_BOOLEAN)] : $day)->all(),
        ]);
    }
}
