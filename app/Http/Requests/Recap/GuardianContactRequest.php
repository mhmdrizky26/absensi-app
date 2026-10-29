<?php

namespace App\Http\Requests\Recap;

use App\Models\AcademicYear;
use App\Models\GuardianContact;
use App\Models\Student;
use App\Support\RecapScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GuardianContactRequest extends FormRequest
{
    public const MAX_CONTACTS = 3;

    /**
     * Guru BK may add numbers for any student; a wali kelas only for students
     * of the class they lead. Checked before validation.
     */
    public function authorize(RecapScope $scope): bool
    {
        $academicYear = AcademicYear::active();
        $classroom = $academicYear ? $this->route('student')->classroomIn($academicYear) : null;

        return $classroom !== null && $scope->canCorrect($this->user(), $classroom);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => GuardianContact::normalizePhone($this->input('phone')) ?? trim((string) $this->input('phone'))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Student $student */
        $student = $this->route('student');

        return [
            'name' => ['nullable', 'string', 'max:100'],
            'relation' => ['nullable', Rule::in(array_keys(GuardianContact::RELATIONS))],
            'phone' => ['required', 'regex:/^628\d{7,11}$/', Rule::unique('guardian_contacts', 'phone')->where('student_id', $student->id)],
            'consented' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->route('student')->guardianContacts()->count() >= self::MAX_CONTACTS) {
                    $validator->errors()->add('phone', 'Siswa ini sudah punya '.self::MAX_CONTACTS.' nomor orang tua. Hapus salah satu lewat menu Siswa (admin).');
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
            'phone.required' => 'Nomor WA orang tua wajib diisi.',
            'phone.regex' => 'Nomor WA tidak valid. Contoh: 0812 3456 7890.',
            'phone.unique' => 'Nomor ini sudah tersimpan untuk siswa ini.',
        ];
    }
}
