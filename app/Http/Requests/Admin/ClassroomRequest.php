<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Classroom;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The academic year the classroom belongs to: its own when editing,
     * otherwise the active one.
     */
    public function academicYearId(): ?int
    {
        /** @var Classroom|null $classroom */
        $classroom = $this->route('classroom');

        return $classroom?->academic_year_id ?? AcademicYear::active()?->id;
    }

    protected function prepareForValidation(): void
    {
        $grade = (int) $this->input('grade');
        $section = trim((string) $this->input('section'));

        if (array_key_exists($grade, Classroom::GRADES) && $section !== '') {
            $this->merge(['name' => Classroom::nameFor($grade, $section)]);
        }

        $this->merge(['access_code' => strtoupper(preg_replace('/\s+/', '', (string) $this->input('access_code'))) ?: null]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $academicYearId = $this->academicYearId();

        return [
            'grade' => ['required', 'integer', Rule::in(array_keys(Classroom::GRADES))],
            'section' => ['required', 'string', 'regex:/^[A-Za-z]{1,2}$/'],
            'name' => [
                'required',
                Rule::unique('classrooms', 'name')
                    ->where('academic_year_id', $academicYearId)
                    ->ignore($this->route('classroom')),
            ],
            'access_code' => [
                'nullable',
                'regex:/^[A-Z0-9]{'.Classroom::ACCESS_CODE_MIN.','.Classroom::ACCESS_CODE_MAX.'}$/',
                Rule::unique('classrooms', 'access_code')->where('academic_year_id', $academicYearId)->ignore($this->route('classroom')),
            ],
            'homeroom_teacher_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', Role::WaliKelas->value)->where('is_active', true),
                Rule::unique('classrooms', 'homeroom_teacher_id')
                    ->where('academic_year_id', $academicYearId)
                    ->ignore($this->route('classroom')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'section.regex' => 'Rombel berupa 1–2 huruf, misalnya A.',
            'access_code.regex' => 'Kode kelas berupa '.Classroom::ACCESS_CODE_MIN.'–'.Classroom::ACCESS_CODE_MAX.' huruf atau angka, tanpa spasi.',
            'access_code.unique' => 'Kode :input sudah dipakai kelas lain di tahun ajaran ini. Pilih kode lain.',
            'name.unique' => 'Kelas :input sudah ada di tahun ajaran ini.',
            'homeroom_teacher_id.exists' => 'Pilih guru dengan peran wali kelas yang masih aktif.',
            'homeroom_teacher_id.unique' => 'Guru ini sudah menjadi wali kelas lain di tahun ajaran ini.',
        ];
    }
}
