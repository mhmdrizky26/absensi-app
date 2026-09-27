<?php

namespace App\Http\Requests\Admin;

use App\Models\AcademicYear;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AcademicYearRequest extends FormRequest
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
        /** @var AcademicYear|null $academicYear */
        $academicYear = $this->route('academicYear');

        return [
            'name' => [
                'required',
                'string',
                'regex:/^\d{4}\/\d{4}$/',
                Rule::unique('academic_years', 'name')->ignore($academicYear),
            ],
            'odd_semester_starts_on' => ['required', 'date'],
            'even_semester_starts_on' => ['required', 'date', 'after:odd_semester_starts_on'],
            'ends_on' => ['required', 'date', 'after:even_semester_starts_on'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('name')) {
                    return;
                }

                [$firstYear, $secondYear] = array_map('intval', explode('/', $this->string('name')));

                if ($secondYear !== $firstYear + 1) {
                    $validator->errors()->add('name', 'Tahun ajaran harus dua tahun berurutan, misalnya 2026/2027.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Tulis tahun ajaran seperti 2026/2027.',
        ];
    }
}
