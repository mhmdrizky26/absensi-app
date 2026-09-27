<?php

namespace App\Http\Requests\Admin;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nis' => trim((string) $this->input('nis')),
            'nisn' => trim((string) $this->input('nisn')) ?: null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $student = $this->route('student');

        return [
            'nis' => ['required', 'string', 'max:20', 'regex:/^[0-9A-Za-z.\/-]+$/', Rule::unique('students', 'nis')->ignore($student)],
            'nisn' => ['nullable', 'digits:10', Rule::unique('students', 'nisn')->ignore($student)],
            'name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'status' => ['required', Rule::enum(StudentStatus::class)],
            'classroom_id' => [
                'nullable',
                'integer',
                Rule::exists('classrooms', 'id')->where('academic_year_id', AcademicYear::active()?->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nis.regex' => 'NIS hanya boleh berisi angka, huruf, titik, garis miring, atau strip.',
            'classroom_id.exists' => 'Pilih kelas dari tahun ajaran yang sedang aktif.',
        ];
    }
}
