<?php

namespace App\Http\Requests\Admin;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\GuardianContact;
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
            'guardians' => collect($this->input('guardians', []))
                ->map(fn (array $guardian): array => [
                    ...$guardian,
                    'phone' => GuardianContact::normalizePhone($guardian['phone'] ?? null) ?? trim((string) ($guardian['phone'] ?? '')),
                ])
                ->all(),
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
            'guardians' => ['array', 'max:3'],
            'guardians.*.name' => ['nullable', 'string', 'max:100'],
            'guardians.*.relation' => ['nullable', Rule::in(array_keys(GuardianContact::RELATIONS))],
            'guardians.*.phone' => ['required', 'regex:/^628\d{7,11}$/', 'distinct'],
            'guardians.*.consented' => ['boolean'],
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
            'guardians.*.phone.required' => 'Nomor WA orang tua wajib diisi.',
            'guardians.*.phone.regex' => 'Nomor WA tidak valid. Contoh: 0812 3456 7890.',
            'guardians.*.phone.distinct' => 'Nomor WA yang sama diisi dua kali.',
        ];
    }
}
